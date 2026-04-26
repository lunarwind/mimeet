<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentHandlers\VerificationHandler;
use App\Services\PaymentHandlers\SubscriptionHandler;
use App\Services\PaymentHandlers\PointsHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 統一金流入口服務（Step 4）
 *
 * 三入口共用 initiate() 建立 Payment 紀錄 + 回傳 AIO 參數，
 * 統一 handleCallback() 驗簽 + 冪等 + 分派業務 Handler。
 *
 * 訂單前綴規則（≤ 20 chars for ECPay MerchantTradeNo）：
 *   verification → CCV_ + YmdHis(14) + 2 random = 20 chars
 *   subscription → MM   + YmdHis(14) + 4 random = 20 chars
 *   points       → PTS_ + YmdHis(14) + 2 random = 20 chars
 */
class UnifiedPaymentService
{
    public function __construct(
        private ECPayService     $ecpay,
        private PaymentService   $paymentService,   // 既有 service（subscription 用）
        private PointService     $pointService,     // 既有 service（points 用）
    ) {}

    // ─── 發起金流 ─────────────────────────────────────────────────────

    /**
     * 統一發起金流。建立 Payment 紀錄，回傳 AIO 表單參數 + URL。
     *
     * @param string $type         verification | subscription | points
     * @param User   $user
     * @param array{
     *   item_name: string,
     *   amount: int,
     *   reference_id?: int,      業務表 ID（已由 caller 建好）
     *   description?: string,
     * } $data
     * @return array{payment: Payment, aio_url: string, params: array}
     */
    public function initiate(string $type, User $user, array $data): array
    {
        // ── 雷點 1 防護：5 分鐘內同 user 同 type 已有 pending → 重用 ──
        $existing = Payment::where('user_id', $user->id)
            ->where('type', $type)
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(5))
            ->latest()
            ->first();

        if ($existing) {
            Log::info('[UnifiedPayment] Reusing pending payment', [
                'payment_id' => $existing->id,
                'order_no'   => $existing->order_no,
            ]);

            // 重算 params（MerchantTradeDate 更新為現在，CheckMacValue 重算）
            $params = $this->ecpay->buildAioParams([
                'order_no'    => $existing->order_no,
                'amount'      => $existing->amount,
                'item_name'   => $existing->item_name ?? $data['item_name'],
                'description' => $data['description'] ?? $data['item_name'],
            ]);

            return [
                'payment' => $existing,
                'aio_url' => $this->ecpay->getAioUrl(),
                'params'  => $params,
            ];
        }

        // ── 建立新 Payment 紀錄 ───────────────────────────────────────
        $orderNo = $this->generateOrderNo($type);

        $payment = Payment::create([
            'user_id'      => $user->id,
            'type'         => $type,
            'order_no'     => $orderNo,
            'item_name'    => $data['item_name'],
            'amount'       => (int) $data['amount'],
            'status'       => 'pending',
            'gateway'      => 'ecpay',
            'environment'  => $this->ecpay->getEnvironment(),
            'reference_id' => $data['reference_id'] ?? null,
        ]);

        $params = $this->ecpay->buildAioParams([
            'order_no'    => $orderNo,
            'amount'      => $data['amount'],
            'item_name'   => $data['item_name'],
            'description' => $data['description'] ?? $data['item_name'],
        ]);

        return [
            'payment' => $payment,
            'aio_url' => $this->ecpay->getAioUrl(),
            'params'  => $params,
        ];
    }

    // ─── Callback 統一處理 ────────────────────────────────────────────

    /**
     * 處理 ECPay server-to-server callback。
     * 必須回傳純文字 1|OK 或 0|Error 給 ECPay。
     *
     * 設計：
     * 1. 驗簽
     * 2. 找 Payment by order_no（MerchantTradeNo）
     * 3. 冪等：status==='paid' → 直接 return 1|OK（ECPay 失敗重試安全）
     * 4. 更新 Payment + dispatch Handler
     */
    public function handleCallback(array $params): string
    {
        // 1. 驗簽
        if (!$this->ecpay->verifyCallback($params)) {
            Log::warning('[UnifiedPayment] CheckMacValue mismatch', [
                'merchant_trade_no' => $params['MerchantTradeNo'] ?? '',
            ]);
            return '0|CheckMacValue Error';
        }

        $orderNo = $params['MerchantTradeNo'] ?? '';
        $rtnCode = (int) ($params['RtnCode'] ?? 0);

        // 2. 找 Payment
        $payment = Payment::where('order_no', $orderNo)->first();
        if (!$payment) {
            Log::warning('[UnifiedPayment] Payment not found', ['order_no' => $orderNo]);
            return '0|Order Not Found';
        }

        // 3. 冪等：已處理過的不重複執行
        if ($payment->status === 'paid') {
            return '1|OK';
        }
        if (in_array($payment->status, ['failed', 'cancelled'])) {
            // 已確認失敗的也直接回 OK（讓 ECPay 停止重試）
            return '1|OK';
        }

        $isPaid       = $rtnCode === 1;
        $cardCountry  = $params['card_issue_country'] ?? null;
        $tradeNo      = $params['TradeNo'] ?? null;

        DB::transaction(function () use ($payment, $params, $isPaid, $cardCountry, $tradeNo) {
            if (!$isPaid) {
                $payment->update([
                    'status'         => 'failed',
                    'failure_reason' => $params['RtnMsg'] ?? 'Payment failed',
                    'raw_callback'   => $params,
                ]);
                Log::info('[UnifiedPayment] Payment failed', [
                    'order_no' => $payment->order_no,
                    'rtn_msg'  => $params['RtnMsg'] ?? '',
                ]);
                return;
            }

            // 信用卡驗證：驗台灣卡，非台灣卡立刻退款
            if ($payment->type === 'verification' && $cardCountry && $cardCountry !== 'TW') {
                $payment->update([
                    'status'            => 'failed',
                    'gateway_trade_no'  => $tradeNo,
                    'card_country'      => $cardCountry,
                    'failure_reason'    => '非台灣發行之信用卡，自動退款',
                    'raw_callback'      => $params,
                    'paid_at'           => now(),
                ]);
                \App\Jobs\RefundPaymentJob::dispatch($payment->id);
                Log::warning('[UnifiedPayment] Non-TW card refund triggered', [
                    'order_no' => $payment->order_no,
                    'country'  => $cardCountry,
                ]);
                return;
            }

            // 付款成功：更新 Payment 紀錄
            $payment->update([
                'status'          => 'paid',
                'gateway_trade_no'=> $tradeNo,
                'card_country'    => $cardCountry ?? 'TW',
                'payment_method'  => $params['PaymentType'] ?? null,
                'paid_at'         => now(),
                'raw_callback'    => $params,
            ]);

            // verification 排程退款（3 工作日後）
            if ($payment->type === 'verification') {
                $refundAt = now()->addDays(3);
                $payment->update(['refund_scheduled_at' => $refundAt]);
                \App\Jobs\RefundCreditCardVerificationJob::dispatch($payment->id)
                    ->delay($refundAt);
            }

            // 分派業務 Handler
            $this->dispatchHandler($payment);
        });

        return '1|OK';
    }

    // ─── 訂單號生成 ───────────────────────────────────────────────────

    /**
     * 生成訂單號（≤ 20 chars，符合 ECPay MerchantTradeNo 限制）
     *
     * 格式規則（無底線，純英數符合 ECPay 規範）：
     *   verification → CCV + YmdHis(14) + 3 random = 20 chars
     *   subscription → MM  + YmdHis(14) + 4 random = 20 chars
     *   points       → PTS + YmdHis(14) + 3 random = 20 chars
     *
     * 範例：
     *   CCV202604261822ABCD3  (verification)
     *   MM202604261822ABCD4X  (subscription, 既有格式不變)
     *   PTS202604261822ABCD3  (points)
     *
     * 注意：legacy 資料（CCV_/PTS_ 含底線）已 cancelled，不影響新流程。
     */
    public function generateOrderNo(string $type): string
    {
        $dateTime = now()->format('YmdHis'); // 14 chars

        return match ($type) {
            'verification' => 'CCV' . $dateTime . strtoupper(Str::random(3)), // 3+14+3=20
            'subscription' => 'MM'  . $dateTime . strtoupper(Str::random(4)), // 2+14+4=20
            'points'       => 'PTS' . $dateTime . strtoupper(Str::random(3)), // 3+14+3=20
            default        => throw new \InvalidArgumentException("Unknown payment type: {$type}"),
        };
    }

    // ─── 內部：業務 Handler 分派 ──────────────────────────────────────

    private function dispatchHandler(Payment $payment): void
    {
        $handler = match ($payment->type) {
            'verification' => app(VerificationHandler::class),
            'subscription' => app(SubscriptionHandler::class),
            'points'       => app(PointsHandler::class),
            default        => null,
        };

        if (!$handler) {
            Log::error('[UnifiedPayment] Unknown payment type', ['type' => $payment->type]);
            return;
        }

        try {
            $handler->onPaid($payment);
        } catch (\Throwable $e) {
            Log::error('[UnifiedPayment] Handler failed', [
                'type'       => $payment->type,
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
            // Handler 失敗不影響 callback 回 1|OK（避免 ECPay 無限重試）
            // 需人工檢查 log 處理
        }
    }
}
