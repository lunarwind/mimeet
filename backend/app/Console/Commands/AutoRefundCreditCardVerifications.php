<?php

namespace App\Console\Commands;

use App\Jobs\RefundCreditCardVerificationJob;
use App\Models\CreditCardVerification;
use Illuminate\Console\Command;

class AutoRefundCreditCardVerifications extends Command
{
    protected $signature = 'credit-card:auto-refund';
    protected $description = 'Dispatch refund jobs for paid verifications older than 3 business days';

    public function handle(): void
    {
        // Find paid verifications older than 3 days that haven't been refunded
        $verifications = CreditCardVerification::where('status', 'paid')
            ->where('paid_at', '<=', now()->subDays(3))
            ->get();

        foreach ($verifications as $v) {
            RefundCreditCardVerificationJob::dispatch($v->id);
            $this->info("Dispatched refund for: {$v->order_no}");
        }

        $this->info("Dispatched {$verifications->count()} refund job(s).");
    }
}
