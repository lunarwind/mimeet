<?php

namespace App\Services;

use App\Models\CreditCardVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditCardVerificationService
{
    public function __construct(
        private ECPayService $ecpay
    ) {}

    /**
     * Create a new verification record and return ECPay payment URL.
     * Returns null if user already verified.
     */
    public function initiate(User $user): ?array
    {
        // Defense-in-depth: service層也守門，避免繞過 controller 直接呼叫
        if ($user->gender !== 'male') {
            throw new \DomainException('信用卡驗證為男性專屬功能');
        }
        if (((float) $user->membership_level) < 1) {
            throw new \DomainException('請先完成手機驗證（Lv1）才可發起信用卡驗證');
        }
        if ($user->credit_card_verified_at) {
            return null; // already verified
        }

        // Idempotent: expire stale pending records older than 1 hour
        CreditCardVerification::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subHour())
            ->update(['status' => 'failed', 'failure_reason' => 'expired']);

        // Check for active pending (created within 1 hour)
        $existing = CreditCardVerification::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subHour())
            ->first();

        if ($existing) {
            // Re-use existing pending record
            $orderNo = $existing->order_no;
        } else {
            $orderNo = 'CCV_' . now()->format('YmdHis') . '_' . str_pad($user->id, 6, '0', STR_PAD_LEFT);
            CreditCardVerification::create([
                'user_id' => $user->id,
                'order_no' => $orderNo,
                'amount' => 100,
                'status' => 'pending',
            ]);
        }

        $returnUrl = config('app.url') . '/api/v1/verification/credit-card/return';
        $notifyUrl = config('app.url') . '/api/v1/verification/credit-card/callback';

        $paymentUrl = $this->ecpay->getPaymentUrl(
            merchantTradeNo: $orderNo,
            amount: 100,
            itemName: 'MiMeet 信用卡身份驗證（NT$100，驗證後退還）',
            returnUrl: $returnUrl,
            notifyUrl: $notifyUrl,
        );

        return ['order_no' => $orderNo, 'payment_url' => $paymentUrl];
    }

    /**
     * Process ECPay server-side callback. Idempotent.
     */
    public function processCallback(array $data): bool
    {
        $orderNo = $data['MerchantTradeNo'] ?? '';
        $rtnCode = $data['RtnCode'] ?? '';
        $ecpayTradeNo = $data['TradeNo'] ?? '';

        $verification = CreditCardVerification::where('order_no', $orderNo)->first();
        if (!$verification) {
            Log::warning('[CreditCardVerification] Unknown order', ['order_no' => $orderNo]);
            return false;
        }

        // Idempotent: already processed
        if ($verification->status === 'paid') {
            return true;
        }

        $rawCallback = $data;
        unset($rawCallback['CheckMacValue']); // don't store the MAC

        if ($rtnCode === '1') {
            // Payment successful
            DB::transaction(function () use ($verification, $ecpayTradeNo, $data, $rawCallback) {
                $verification->update([
                    'status' => 'paid',
                    'gateway_trade_no' => $ecpayTradeNo,
                    'payment_method' => $data['PaymentType'] ?? null,
                    'card_last4' => isset($data['card4no']) ? substr($data['card4no'], -4) : null,
                    'paid_at' => now(),
                    'raw_callback' => $rawCallback,
                ]);

                $user = $verification->user;
                if ($user && !$user->credit_card_verified_at) {
                    $user->forceFill(['credit_card_verified_at' => now(), 'membership_level' => 2.0])->save();
                    $points = (int) CreditScoreService::getConfig('credit_add_adv_verify_male', 15);
                    CreditScoreService::adjust($user, $points, 'adv_verify_male', '男性信用卡驗證通過', null);
                }

                // Schedule auto-refund after 3 business days
                \App\Jobs\RefundCreditCardVerificationJob::dispatch($verification->id)
                    ->delay(now()->addDays(3));

                $verification->update(['refund_initiated_at' => now()->addDays(3)]);
            });
        } else {
            $verification->update([
                'status' => 'failed',
                'gateway_trade_no' => $ecpayTradeNo,
                'failure_reason' => $data['RtnMsg'] ?? 'payment failed',
                'raw_callback' => $rawCallback,
            ]);
        }

        return $rtnCode === '1';
    }

    /**
     * Trigger refund for a paid verification (used by admin or auto-refund job).
     */
    public function refund(CreditCardVerification $verification): bool
    {
        if ($verification->status !== 'paid') {
            return false;
        }

        $result = $this->ecpay->doRefund(
            $verification->order_no,
            $verification->gateway_trade_no,
            $verification->amount
        );

        if ($result['success']) {
            $verification->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_trade_no' => $result['result']['TradeNo'] ?? null,
            ]);
            return true;
        } else {
            $verification->update([
                'status' => 'refund_failed',
                'failure_reason' => 'refund_failed: ' . json_encode($result['result']),
            ]);
            return false;
        }
    }
}
