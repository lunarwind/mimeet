<?php

namespace App\Jobs;

use App\Models\CreditCardVerification;
use App\Services\CreditCardVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefundCreditCardVerificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 300; // 5 min between retries

    public function __construct(public int $verificationId) {}

    public function handle(CreditCardVerificationService $service): void
    {
        $verification = CreditCardVerification::find($this->verificationId);
        if (!$verification) {
            Log::warning('[RefundJob] Verification not found', ['id' => $this->verificationId]);
            return;
        }

        if ($verification->status !== 'paid') {
            // Already refunded or failed, skip
            return;
        }

        Log::info('[RefundJob] Initiating refund', ['order_no' => $verification->order_no]);
        $ok = $service->refund($verification);

        if (!$ok) {
            Log::error('[RefundJob] Refund failed, will retry', ['order_no' => $verification->order_no]);
            $this->fail(new \Exception("Refund failed for {$verification->order_no}"));
        }
    }
}
