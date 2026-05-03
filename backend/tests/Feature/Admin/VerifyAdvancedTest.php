<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cleanup PR-C：admin verify_advanced / unverify_advanced 寫入 cc_verified_at
 */
class VerifyAdvancedTest extends TestCase
{
    use RefreshDatabase;

    private function asAdmin(): array
    {
        $admin = AdminUser::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;
        return [$admin, ['Authorization' => "Bearer {$token}"]];
    }

    // ─── Case 10: admin verify_advanced 男性 → cc_verified_at 寫入 + level=2 ───
    public function test_verify_advanced_writes_cc_verified_at_for_male(): void
    {
        [, $headers] = $this->asAdmin();
        $user = User::factory()->create([
            'gender'                  => 'male',
            'membership_level'        => 1,
            'phone_verified'          => true,
            'credit_card_verified_at' => null,
        ]);

        $this->withHeaders($headers)
            ->patchJson("/api/v1/admin/members/{$user->id}/actions", [
                'action' => 'verify_advanced',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertEquals(2.0, (float) $user->membership_level);
        $this->assertNotNull($user->credit_card_verified_at);
    }

    // ─── Case 11: admin unverify_advanced 男性 → cc_verified_at 清空 + level=1 ───
    public function test_unverify_advanced_clears_cc_verified_at_for_male(): void
    {
        [, $headers] = $this->asAdmin();
        $user = User::factory()->create([
            'gender'                  => 'male',
            'membership_level'        => 2,
            'phone_verified'          => true,
            'credit_card_verified_at' => now(),
        ]);

        $this->withHeaders($headers)
            ->patchJson("/api/v1/admin/members/{$user->id}/actions", [
                'action' => 'unverify_advanced',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertEquals(1.0, (float) $user->membership_level);
        $this->assertNull($user->credit_card_verified_at);
    }

    // ─── Case 12: admin unverify_advanced 後，user 若有 paid payment → base level 仍為 Lv2（弱化版設計）───
    public function test_unverify_advanced_does_not_affect_base_level_when_user_has_paid_payment(): void
    {
        [, $headers] = $this->asAdmin();
        $user = User::factory()->create([
            'gender'                  => 'male',
            'membership_level'        => 2,
            'phone_verified'          => true,
            'credit_card_verified_at' => now()->subDays(60),
        ]);
        // user 之前曾有訂閱付款
        Payment::create([
            'user_id'      => $user->id,
            'type'         => 'subscription',
            'order_no'     => 'OLD_SUB_' . uniqid(),
            'item_name'    => 'old subscription',
            'amount'       => 600,
            'currency'     => 'TWD',
            'status'       => 'paid',
            'gateway'      => 'ecpay',
            'environment'  => 'sandbox',
            'reference_id' => 1,
            'paid_at'      => now()->subDays(40),
        ]);

        $this->withHeaders($headers)
            ->patchJson("/api/v1/admin/members/{$user->id}/actions", [
                'action' => 'unverify_advanced',
            ])
            ->assertOk();

        $user->refresh();
        // membership_level 確實降為 1（直接欄位）
        $this->assertEquals(1.0, (float) $user->membership_level);
        // cc_verified_at 確實清空
        $this->assertNull($user->credit_card_verified_at);
        // 但 base level 推導因有 paid payment 仍為 Lv2（弱化版設計）
        $this->assertEquals(2.0, $user->getBaseMembershipLevel());
    }
}
