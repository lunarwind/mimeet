<?php

namespace App\Services\PaymentHandlers;

use App\Models\CreditCardVerification;
use App\Models\Payment;
use App\Services\CreditScoreService;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

/**
 * 信用卡身份驗證付款成功後的業務處理。
 *
 * 沿用 CreditCardVerificationService::processCallback() 的邏輯，不重寫：
 *  - users.credit_card_verified_at 寫入
 *  - membership_level 升為 2
 *  - 誠信分數 +credit_add_adv_verify_male（預設 15，從 SystemSetting 讀）
 *  - credit_card_verifications.status 更新為 paid
 */
class VerificationHandler
{
    public function onPaid(Payment $payment): void
    {
        $user = $payment->user;
        if (!$user) {
            Log::error('[VerificationHandler] User not found', ['payment_id' => $payment->id]);
            return;
        }

        // 冪等：已驗證則跳過（應由 handleCallback 冪等擋住，這裡雙保險）
        if ($user->credit_card_verified_at) {
            Log::info('[VerificationHandler] Already verified, skip', ['user_id' => $user->id]);
            return;
        }

        // 升 Lv2 + 寫入驗證時間
        $user->forceFill([
            'credit_card_verified_at' => now(),
            'membership_level'        => 2.0,
        ])->save();

        // 誠信分數 +15（從 SystemSetting 動態讀取，default 15）
        $points = (int) SystemSetting::get('credit_add_adv_verify_male', 15);
        CreditScoreService::adjust(
            $user,
            $points,
            'adv_verify_male',
            "信用卡進階驗證：{$payment->order_no}",
            null,
        );

        // 更新業務表狀態（reference_id → credit_card_verifications.id）
        if ($payment->reference_id) {
            CreditCardVerification::where('id', $payment->reference_id)
                ->update(['status' => 'paid', 'paid_at' => $payment->paid_at]);
        }

        Log::info('[VerificationHandler] User verified', [
            'user_id'    => $user->id,
            'order_no'   => $payment->order_no,
            'score_add'  => $points,
        ]);
    }
}
