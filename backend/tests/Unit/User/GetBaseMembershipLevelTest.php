<?php

namespace Tests\Unit\User;

use App\Models\Payment;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cleanup PR-C：base level 重新定義測試
 *
 * Lv2 條件（任一成立）：
 *   A. gender=male + credit_card_verified_at IS NOT NULL
 *   B. gender=male + exists(payments.paid_at IS NOT NULL)
 */
class GetBaseMembershipLevelTest extends TestCase
{
    use RefreshDatabase;

    private function makePayment(User $user, string $type, ?string $paidAt = null, string $status = 'paid'): Payment
    {
        return Payment::create([
            'user_id'          => $user->id,
            'type'             => $type,
            'order_no'         => strtoupper($type) . '_' . uniqid(),
            'item_name'        => 'test',
            'amount'           => 100,
            'currency'         => 'TWD',
            'status'           => $status,
            'gateway'          => 'ecpay',
            'environment'      => 'sandbox',
            'reference_id'     => 1,
            'paid_at'          => $paidAt === 'now' ? now() : $paidAt,
        ]);
    }

    // ─── Case 1: 男性 + cc_verified_at 設值 + 無 paid payment → Lv2 ───
    public function test_male_with_cc_verified_at_returns_lv2(): void
    {
        $user = User::factory()->create([
            'gender'                  => 'male',
            'phone_verified'          => true,
            'credit_card_verified_at' => now()->subDays(30),
        ]);

        $this->assertEquals(2.0, $user->getBaseMembershipLevel());
    }

    // ─── Case 2: 男性 + 無 cc + 有 paid subscription payment → Lv2 ───
    public function test_male_with_paid_subscription_payment_returns_lv2(): void
    {
        $user = User::factory()->create([
            'gender'                  => 'male',
            'phone_verified'          => true,
            'credit_card_verified_at' => null,
        ]);
        $this->makePayment($user, 'subscription', 'now');

        $this->assertEquals(2.0, $user->getBaseMembershipLevel());
    }

    // ─── Case 3: 男性 + 無 cc + 有 paid points payment → Lv2 ───
    public function test_male_with_paid_points_payment_returns_lv2(): void
    {
        $user = User::factory()->create([
            'gender'                  => 'male',
            'phone_verified'          => true,
            'credit_card_verified_at' => null,
        ]);
        $this->makePayment($user, 'points', 'now');

        $this->assertEquals(2.0, $user->getBaseMembershipLevel());
    }

    // ─── Case 4: 男性 + 無 cc + 有 verification payment（NT$100 驗證款）→ Lv2 ───
    public function test_male_with_paid_verification_payment_returns_lv2(): void
    {
        $user = User::factory()->create([
            'gender'                  => 'male',
            'phone_verified'          => true,
            'credit_card_verified_at' => null,
        ]);
        $this->makePayment($user, 'verification', 'now');

        $this->assertEquals(2.0, $user->getBaseMembershipLevel());
    }

    // ─── Case 5: 男性 + 無 cc + 無 paid payment + phone_verified → Lv1 ───
    public function test_male_without_payment_or_cc_falls_to_lv1(): void
    {
        $user = User::factory()->create([
            'gender'                  => 'male',
            'phone_verified'          => true,
            'credit_card_verified_at' => null,
        ]);

        $this->assertEquals(1.0, $user->getBaseMembershipLevel());
    }

    // ─── Case 6: 男性 + 只有 failed payment（paid_at=null）→ Lv1 ───
    public function test_male_with_only_failed_payment_falls_to_lv1(): void
    {
        $user = User::factory()->create([
            'gender'                  => 'male',
            'phone_verified'          => true,
            'credit_card_verified_at' => null,
        ]);
        $this->makePayment($user, 'subscription', null, 'failed');

        $this->assertEquals(1.0, $user->getBaseMembershipLevel());
    }

    // ─── Case 7: 女性 + 照片驗證 approved + 有 paid payment → Lv1.5 (女性不適用 Lv2 規則) ───
    public function test_female_with_photo_verification_returns_lv1_5_even_with_payment(): void
    {
        $user = User::factory()->create([
            'gender'         => 'female',
            'phone_verified' => true,
        ]);
        UserVerification::create([
            'user_id'     => $user->id,
            'random_code' => 'TEST01',
            'status'      => 'approved',
        ]);
        $this->makePayment($user, 'subscription', 'now');

        $this->assertEquals(1.5, $user->getBaseMembershipLevel());
    }

    // ─── Case 8: 女性 + 無照片 + phone_verified → Lv1 ───
    public function test_female_without_photo_returns_lv1(): void
    {
        $user = User::factory()->create([
            'gender'         => 'female',
            'phone_verified' => true,
        ]);

        $this->assertEquals(1.0, $user->getBaseMembershipLevel());
    }

    // ─── Case 9: phone_verified=false → Lv0 ───
    public function test_unverified_user_returns_lv0(): void
    {
        $user = User::factory()->create([
            'gender'         => 'male',
            'phone_verified' => false,
        ]);

        $this->assertEquals(0.0, $user->getBaseMembershipLevel());
    }
}
