<?php

namespace App\Jobs;

use App\Models\AdminOperationLog;
use App\Models\Payment;
use App\Services\ECPayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 退款 Job — 統一 payments 主表使用（Step 8）
 *
 * 冪等性設計（三層保護）：
 *   1. payment.status !== 'paid' → 直接 return（已退款/失敗/cancelled 不重複退）
 *   2. payment.refunded_at !== null → 直接 return（已退過）
 *   3. 退款成功後立即更新 status='refunded'，重複 dispatch 觸發第 1 層攔截
 *
 * 失敗告警：
 *   - 每次失敗遞增 refund_attempts
 *   - 累計 ≥ 3 次 → requires_manual_review=true + audit log
 *   - **不 throw 例外**（避免 queue 自動重試造成多次退款）
 *
 * tries = 1（不自動 retry，refund_attempts 追蹤人工重試次數）
 */
class RefundPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;  // 不自動重試，避免多次退款

    public function __construct(public int $paymentId) {}

    public function handle(ECPayService $ecpay): void
    {
        $payment = Payment::find($this->paymentId);

        // 冪等層 1：紀錄不存在
        if (!$payment) {
            Log::warning('[RefundPaymentJob] Payment not found', ['id' => $this->paymentId]);
            return;
        }

        // 冪等層 2：狀態不是 paid（已退款、已取消、已失敗）
        if ($payment->status !== 'paid') {
            Log::info('[RefundPaymentJob] Skip — status not paid', [
                'payment_id' => $payment->id,
                'status'     => $payment->status,
            ]);
            return;
        }

        // 冪等層 3：已有 refunded_at（已成功退過）
        if ($payment->refunded_at !== null) {
            Log::info('[RefundPaymentJob] Skip — already refunded', ['payment_id' => $payment->id]);
            return;
        }

        if (!$payment->gateway_trade_no) {
            Log::warning('[RefundPaymentJob] No gateway_trade_no, cannot refund', ['payment_id' => $payment->id]);
            $payment->increment('refund_attempts');
            $payment->update([
                'status'                => 'refund_failed',
                'refund_failure_reason' => '缺少 gateway_trade_no（ECPay TradeNo），無法發起退款',
            ]);
            $this->checkManualReview($payment);
            return;
        }

        Log::info('[RefundPaymentJob] Initiating refund', [
            'payment_id' => $payment->id,
            'order_no'   => $payment->order_no,
        ]);

        $result = $ecpay->doRefund(
            $payment->order_no,
            $payment->gateway_trade_no,
            $payment->amount,
        );

        if ($result['success']) {
            $payment->update([
                'status'          => 'refunded',
                'refunded_at'     => now(),
                'refund_trade_no' => $result['result']['TradeNo'] ?? $payment->gateway_trade_no,
            ]);
            Log::info('[RefundPaymentJob] Refund success', [
                'payment_id' => $payment->id,
                'order_no'   => $payment->order_no,
            ]);
        } else {
            $payment->increment('refund_attempts');
            $payment->update([
                'status'                => 'refund_failed',
                'refund_failure_reason' => $result['result']['RtnMsg'] ?? ($result['error'] ?? 'unknown'),
            ]);
            Log::error('[RefundPaymentJob] Refund failed', [
                'payment_id' => $payment->id,
                'order_no'   => $payment->order_no,
                'result'     => $result['result'] ?? [],
            ]);
            $this->checkManualReview($payment->fresh());
        }

        // 不 throw 例外 → queue 不會自動 retry
    }

    /**
     * 累計 ≥ 3 次失敗 → 標記需人工處理 + 寫 audit log
     */
    private function checkManualReview(Payment $payment): void
    {
        $attempts = (int) $payment->refund_attempts;
        if ($attempts < 3) {
            return;
        }

        $payment->update(['requires_manual_review' => true]);

        AdminOperationLog::create([
            'admin_id'        => null,
            'action'          => 'refund_manual_review_required',
            'resource_type'   => 'payment',
            'resource_id'     => $payment->id,
            'description'     => "退款連續失敗 {$attempts} 次，需人工處理：{$payment->order_no}",
            'ip_address'      => '127.0.0.1',
            'user_agent'      => 'system/RefundPaymentJob',
            'request_summary' => [
                'payment_id'   => $payment->id,
                'order_no'     => $payment->order_no,
                'attempts'     => $attempts,
                'last_reason'  => $payment->refund_failure_reason,
            ],
            'created_at' => now(),
        ]);

        Log::error('[RefundPaymentJob] MANUAL REVIEW REQUIRED', [
            'payment_id' => $payment->id,
            'order_no'   => $payment->order_no,
            'attempts'   => $attempts,
        ]);
    }
}
