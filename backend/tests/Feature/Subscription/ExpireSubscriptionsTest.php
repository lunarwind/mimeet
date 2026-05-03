<?php

namespace Tests\Feature\Subscription;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 議題 1（subscriptions:expire）+ 議題 2（trial auto_renew=false）整合測試。
 */
class ExpireSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlans(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
    }

    private function makeOrder(User $user, SubscriptionPlan $plan): Order
    {
        return Order::create([
            'order_number'   => 'TEST_' . uniqid(),
            'user_id'        => $user->id,
            'plan_id'        => $plan->id,
            'amount'         => $plan->price,
            'currency'       => 'TWD',
            'payment_method' => 'credit_card',
            'status'         => 'paid',
            'paid_at'        => now()->subDays(35),
            'expires_at'     => now()->subDays(35),
        ]);
    }

    private function makeSubscription(User $user, SubscriptionPlan $plan, array $overrides = []): Subscription
    {
        $order = $this->makeOrder($user, $plan);
        return Subscription::create(array_merge([
            'user_id'    => $user->id,
            'plan_id'    => $plan->id,
            'order_id'   => $order->id,
            'status'     => 'active',
            'auto_renew' => false,
            'started_at' => now()->subDays(35),
            'expires_at' => now()->subDays(5),
        ], $overrides));
    }

    // ─── Case 1: expired active sub → marked expired + user downgraded ───
    public function test_expired_active_subscription_is_processed(): void
    {
        $this->seedPlans();
        $monthly = SubscriptionPlan::where('slug', 'plan_monthly')->first();
        $user = User::factory()->create([
            'membership_level' => 3,
            'phone_verified'   => true,
            'gender'           => 'male',
        ]);
        $sub = $this->makeSubscription($user, $monthly);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertEquals('expired', $sub->fresh()->status);
        $this->assertEquals(1.0, (float) $user->fresh()->membership_level);
    }

    // ─── Case 2: future-expiring active sub → untouched ───
    public function test_active_unexpired_subscription_is_untouched(): void
    {
        $this->seedPlans();
        $monthly = SubscriptionPlan::where('slug', 'plan_monthly')->first();
        $user = User::factory()->create(['membership_level' => 3, 'phone_verified' => true]);

        $sub = $this->makeSubscription($user, $monthly, [
            'expires_at' => now()->addDays(10),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertEquals('active', $sub->fresh()->status);
        $this->assertEquals(3.0, (float) $user->fresh()->membership_level);
    }

    // ─── Case 3: trial expired → male/credit_card_verified user falls to Lv2 ───
    public function test_trial_expired_male_with_credit_card_falls_to_lv2(): void
    {
        $this->seedPlans();
        $trial = SubscriptionPlan::where('slug', 'plan_trial')->first();
        $user = User::factory()->create([
            'membership_level'         => 3,
            'phone_verified'           => true,
            'gender'                   => 'male',
            'credit_card_verified_at'  => now()->subDays(60),
        ]);
        $this->makeSubscription($user, $trial);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertEquals(2.0, (float) $user->fresh()->membership_level);
    }

    // ─── Case 4: regular sub expired → female with photo verification → Lv1.5 ───
    public function test_regular_expired_female_with_photo_falls_to_lv1_5(): void
    {
        $this->seedPlans();
        $monthly = SubscriptionPlan::where('slug', 'plan_monthly')->first();
        $user = User::factory()->create([
            'membership_level' => 3,
            'phone_verified'   => true,
            'gender'           => 'female',
        ]);
        UserVerification::create([
            'user_id'     => $user->id,
            'random_code' => 'ABC123',
            'status'      => 'approved',
        ]);
        $this->makeSubscription($user, $monthly);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertEquals(1.5, (float) $user->fresh()->membership_level);
    }

    // ─── Case 5: hasOtherActive guard ───
    public function test_user_with_other_active_subscription_is_not_downgraded(): void
    {
        $this->seedPlans();
        $monthly = SubscriptionPlan::where('slug', 'plan_monthly')->first();
        $weekly  = SubscriptionPlan::where('slug', 'plan_weekly')->first();
        $user = User::factory()->create(['membership_level' => 3, 'phone_verified' => true]);

        $expiredSub = $this->makeSubscription($user, $monthly);
        $activeSub  = $this->makeSubscription($user, $weekly, [
            'expires_at' => now()->addDays(5),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        // expired one still gets marked
        $this->assertEquals('expired', $expiredSub->fresh()->status);
        // but user keeps Lv3 because the other sub is still active
        $this->assertEquals(3.0, (float) $user->fresh()->membership_level);
        $this->assertEquals('active', $activeSub->fresh()->status);
    }

    // ─── Case 6: already-expired sub is not reprocessed ───
    public function test_already_expired_subscription_is_skipped(): void
    {
        $this->seedPlans();
        $monthly = SubscriptionPlan::where('slug', 'plan_monthly')->first();
        $user = User::factory()->create(['membership_level' => 3, 'phone_verified' => true]);

        $sub = $this->makeSubscription($user, $monthly, [
            'status' => 'expired', // already expired before command
        ]);

        $countBefore = \DB::table('notifications')->count();
        $this->artisan('subscriptions:expire')->assertSuccessful();
        $countAfter = \DB::table('notifications')->count();

        // No new notification because the row was already 'expired' (filtered out)
        $this->assertEquals($countBefore, $countAfter);
        $this->assertEquals('expired', $sub->fresh()->status);
    }

    // ─── Case 7: orphan sub (user soft-deleted) → handled gracefully ───
    public function test_orphan_subscription_is_marked_expired_without_error(): void
    {
        $this->seedPlans();
        $monthly = SubscriptionPlan::where('slug', 'plan_monthly')->first();
        $user = User::factory()->create(['membership_level' => 3, 'phone_verified' => true]);
        $sub = $this->makeSubscription($user, $monthly);

        // Soft-delete the user (User uses SoftDeletes)
        $user->delete();

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertEquals('expired', $sub->fresh()->status);
    }

    // ─── Case 8: subscription_expired notification is written ───
    public function test_subscription_expired_notification_is_sent(): void
    {
        $this->seedPlans();
        $monthly = SubscriptionPlan::where('slug', 'plan_monthly')->first();
        $user = User::factory()->create(['membership_level' => 3, 'phone_verified' => true]);
        $sub = $this->makeSubscription($user, $monthly);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type'    => 'subscription_expired',
        ]);

        $notification = \DB::table('notifications')
            ->where('user_id', $user->id)
            ->where('type', 'subscription_expired')
            ->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals($sub->id, $data['subscription_id'] ?? null);
    }

    // ─── Case 9: trial activation → auto_renew=false (議題 2) ───
    public function test_trial_activation_sets_auto_renew_false(): void
    {
        $this->seedPlans();
        $user = User::factory()->create(['membership_level' => 1, 'phone_verified' => true]);

        $orderResp = $this->actingAs($user)->postJson('/api/v1/subscriptions/orders', [
            'plan_id' => 'plan_trial',
        ]);
        $orderResp->assertStatus(201);
        $this->payEcpayCallback($orderResp)->assertOk();

        $sub = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($sub);
        $this->assertFalse((bool) $sub->auto_renew, 'Trial subscription must have auto_renew=false');
    }

    // ─── Case 10: regular activation → auto_renew=true (regression guard) ───
    public function test_regular_activation_keeps_auto_renew_true(): void
    {
        $this->seedPlans();
        $user = User::factory()->create(['membership_level' => 1, 'phone_verified' => true]);

        $orderResp = $this->actingAs($user)->postJson('/api/v1/subscriptions/orders', [
            'plan_id' => 'plan_monthly',
        ]);
        $orderResp->assertStatus(201);
        $this->payEcpayCallback($orderResp)->assertOk();

        $sub = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($sub);
        $this->assertTrue((bool) $sub->auto_renew, 'Regular subscription must have auto_renew=true');
    }

    // ─── PR-C 補強：base level 對男性的 Lv2 推導 ─────────────────

    private function makeBackgroundPayment(User $user): Payment
    {
        return Payment::create([
            'user_id'      => $user->id,
            'type'         => 'subscription',
            'order_no'     => 'OLD_' . uniqid(),
            'item_name'    => 'past subscription',
            'amount'       => 600,
            'currency'     => 'TWD',
            'status'       => 'paid',
            'gateway'      => 'ecpay',
            'environment'  => 'sandbox',
            'reference_id' => 1,
            'paid_at'      => now()->subDays(60),
        ]);
    }

    // ─── Case 17 (PR-C): 男性 + 訂閱到期 + 有 paid payment（無 cc_verified_at）→ 降至 Lv2 ───
    public function test_male_with_paid_payment_falls_to_lv2_on_expire(): void
    {
        $this->seedPlans();
        $monthly = SubscriptionPlan::where('slug', 'plan_monthly')->first();
        $user = User::factory()->create([
            'gender'                  => 'male',
            'membership_level'        => 3,
            'phone_verified'          => true,
            'credit_card_verified_at' => null,
        ]);
        $this->makeBackgroundPayment($user);
        $this->makeSubscription($user, $monthly);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        // 推導為 Lv2（base level 條件 B）
        $this->assertEquals(2.0, (float) $user->fresh()->membership_level);
    }

    // ─── Case 18 (PR-C): 男性 + 訂閱到期 + 無 paid payment + cc_verified_at=null → 降至 Lv1 ───
    public function test_male_without_payment_or_cc_falls_to_lv1_on_expire(): void
    {
        $this->seedPlans();
        $monthly = SubscriptionPlan::where('slug', 'plan_monthly')->first();
        $user = User::factory()->create([
            'gender'                  => 'male',
            'membership_level'        => 3,
            'phone_verified'          => true,
            'credit_card_verified_at' => null,
        ]);
        // 不建 background payment
        $this->makeSubscription($user, $monthly);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertEquals(1.0, (float) $user->fresh()->membership_level);
    }

    // ─── Case 19 (PR-C): 男性 + 訂閱到期 + cc_verified_at 設值 → 降至 Lv2（既有條件 A）───
    public function test_male_with_cc_verified_at_falls_to_lv2_on_expire(): void
    {
        $this->seedPlans();
        $monthly = SubscriptionPlan::where('slug', 'plan_monthly')->first();
        $user = User::factory()->create([
            'gender'                  => 'male',
            'membership_level'        => 3,
            'phone_verified'          => true,
            'credit_card_verified_at' => now()->subDays(60),
        ]);
        $this->makeSubscription($user, $monthly);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertEquals(2.0, (float) $user->fresh()->membership_level);
    }
}
