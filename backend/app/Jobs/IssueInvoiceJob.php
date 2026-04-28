<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\ECPayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 非同步開立電子發票。
 *
 * 設計原則：
 * - 以 payments.id 為單一識別，三種付款類型（subscription/points/verification）共用
 * - payments.item_name 已儲存品項名稱，invoice payload 由此組出
 * - 不依賴 orders.payment_id（目前部分 order 仍為 NULL）
 * - 發票失敗不影響主付款流程（由呼叫方在 try/catch 外 dispatch）
 */
class IssueInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 60, 300]; // 10 秒 → 1 分鐘 → 5 分鐘
    }

    public function __construct(public readonly int $paymentId) {}

    public function handle(ECPayService $ecpay): void
    {
        $payment = Payment::with('user')->find($this->paymentId);

        if (!$payment) {
            Log::warning('[IssueInvoiceJob] Payment not found', ['payment_id' => $this->paymentId]);
            return;
        }

        // 冪等：已開立就不再開
        if ($payment->invoice_no) {
            return;
        }

        $user = $payment->user;

        $result = $ecpay->issueInvoice([
            'relate_number'  => $payment->order_no,
            'customer_email' => $user?->email ?? '',
            'customer_phone' => '',
            'sales_amount'   => $payment->amount,
            'items'          => [[
                'seq'    => 1,
                'name'   => $payment->item_name ?? $this->defaultItemName($payment->type),
                'count'  => 1,
                'word'   => '式',
                'price'  => $payment->amount,
                'amount' => $payment->amount,
            ]],
        ]);

        if ($result === null) {
            // isInvoiceEnabled() 回傳 false → 功能停用，不算失敗，不 retry
            Log::info('[IssueInvoiceJob] Skipped (invoice disabled)', [
                'payment_id' => $this->paymentId,
            ]);
            return;
        }

        $payment->update([
            'invoice_no'            => $result['invoice_no'],
            'invoice_issued_at'     => now(),
            'invoice_random_number' => $result['random_number'] ?? null,
            'invoice_status'        => 'issued',
        ]);

        Log::info('[IssueInvoiceJob] Success', [
            'payment_id' => $this->paymentId,
            'order_no'   => $payment->order_no,
            'invoice_no' => $result['invoice_no'],
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Payment::where('id', $this->paymentId)->update(['invoice_status' => 'failed']);

        Log::error('[IssueInvoiceJob] Failed after retries', [
            'payment_id' => $this->paymentId,
            'error'      => $e->getMessage(),
        ]);
    }

    private function defaultItemName(string $type): string
    {
        return match ($type) {
            'subscription'  => 'MiMeet 訂閱方案',
            'points'        => 'MiMeet 點數儲值',
            'verification'  => 'MiMeet 信用卡身份驗證',
            default         => 'MiMeet 服務',
        };
    }
}
