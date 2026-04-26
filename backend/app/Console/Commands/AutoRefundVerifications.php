<?php

namespace App\Console\Commands;

use App\Jobs\RefundPaymentJob;
use App\Models\Payment;
use Illuminate\Console\Command;

/**
 * payments:auto-refund-verifications
 *
 * 每日 03:00 執行（Step 8）。
 * 找出已到 refund_scheduled_at、type=verification、status=paid 的訂單，
 * 批次派發 RefundPaymentJob。
 */
class AutoRefundVerifications extends Command
{
    protected $signature = 'payments:auto-refund-verifications';
    protected $description = '派發到期的信用卡驗證退款 Job（每日 03:00）';

    public function handle(): void
    {
        $count = 0;

        Payment::where('type', 'verification')
            ->where('status', 'paid')
            ->whereNull('refunded_at')
            ->where('requires_manual_review', false)  // 已標記人工處理的跳過
            ->where('refund_scheduled_at', '<=', now())
            ->chunk(50, function ($payments) use (&$count) {
                foreach ($payments as $payment) {
                    RefundPaymentJob::dispatch($payment->id);
                    $count++;
                    $this->line("  Dispatched refund for: {$payment->order_no}");
                }
            });

        $this->info("Dispatched {$count} refund job(s).");
    }
}
