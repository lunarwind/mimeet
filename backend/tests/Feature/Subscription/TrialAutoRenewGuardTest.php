<?php

namespace Tests\Feature\Subscription;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guard tests for trial subscription auto-renew (audit-trial-autorenew-20260514)。
 * 對應 PRD-001:702 / API-001 §10.5 / §10.9 — 體驗方案不支援自動續訂。
 */
class TrialAutoRenewGuardTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlans(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
    }

    private function makeSubscription(User $user, SubscriptionPlan $plan, bool $autoRenew = false): Subscription
    {
        $order = Order::create([
            'order_number'   => 'TEST_' . uniqid(),
            'user_id'        => $user->id,
            'plan_id'        => $plan->id,
            'amount'         => $plan->price,
            'currency'       => 'TWD',
            'payment_method' => 'credit_card',
            'status'         => 'paid',
            'paid_at'        => now(),
            'expires_at'     => now()->addMinutes(15),
        ]);

        return Subscription::create([
            'user_id'    => $user->id,
            'plan_id'    => $plan->id,
            'order_id'   => $order->id,
            'status'     => 'active',
            'auto_renew' => $autoRenew,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(28),
        ]);
    }

    public function test_trial_subscription_cannot_enable_auto_renew(): void
    {
        $this->seedPlans();
        $user = User::factory()->create(['membership_level' => 3, 'phone_verified' => true]);
        $trialPlan = SubscriptionPlan::where('slug', 'plan_trial')->firstOrFail();
        $sub = $this->makeSubscription($user, $trialPlan, false);

        $resp = $this->actingAs($user)->patchJson('/api/v1/subscriptions/me', [
            'auto_renew' => true,
        ]);

        $resp->assertStatus(422)
            ->assertJsonPath('error_code', 'TRIAL_NOT_RENEWABLE');

        $this->assertFalse((bool) $sub->fresh()->auto_renew, 'DB auto_renew must remain false after 422');
    }

    public function test_non_trial_subscription_can_toggle_auto_renew(): void
    {
        $this->seedPlans();
        $user = User::factory()->create(['membership_level' => 3, 'phone_verified' => true]);
        $monthlyPlan = SubscriptionPlan::where('slug', 'plan_monthly')->firstOrFail();
        $sub = $this->makeSubscription($user, $monthlyPlan, false);

        $resp = $this->actingAs($user)->patchJson('/api/v1/subscriptions/me', [
            'auto_renew' => true,
        ]);

        $resp->assertOk()
            ->assertJsonPath('data.auto_renew', true);

        $this->assertTrue((bool) $sub->fresh()->auto_renew);
    }

    public function test_subscription_response_includes_is_trial(): void
    {
        $this->seedPlans();
        $user = User::factory()->create(['membership_level' => 3, 'phone_verified' => true]);
        $trialPlan = SubscriptionPlan::where('slug', 'plan_trial')->firstOrFail();
        $this->makeSubscription($user, $trialPlan, false);

        $resp = $this->actingAs($user)->getJson('/api/v1/subscriptions/me');

        $resp->assertOk()
            ->assertJsonPath('data.subscription.is_trial', true);
    }

    public function test_trial_subscription_can_still_disable_auto_renew(): void
    {
        // Regression guard：即使資料層出現 auto_renew=true 髒資料，用戶仍可關閉。
        $this->seedPlans();
        $user = User::factory()->create(['membership_level' => 3, 'phone_verified' => true]);
        $trialPlan = SubscriptionPlan::where('slug', 'plan_trial')->firstOrFail();
        $sub = $this->makeSubscription($user, $trialPlan, true); // 髒資料模擬

        $resp = $this->actingAs($user)->patchJson('/api/v1/subscriptions/me', [
            'auto_renew' => false,
        ]);

        $resp->assertOk();
        $this->assertFalse((bool) $sub->fresh()->auto_renew);
    }
}
