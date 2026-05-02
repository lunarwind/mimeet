<?php

namespace Tests\Feature\Subscription;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * subscriptions:notify-expiring command 整合測試。
 */
class NotifyExpiringSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlans(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
    }

    private function makeSubscriptionExpiringIn(int $days, ?User $user = null): Subscription
    {
        $this->seedPlans();
        $user ??= User::factory()->create(['membership_level' => 3, 'phone_verified' => true]);
        $plan = SubscriptionPlan::where('slug', 'plan_monthly')->first();

        $order = Order::create([
            'order_number'   => 'TEST_' . uniqid(),
            'user_id'        => $user->id,
            'plan_id'        => $plan->id,
            'amount'         => $plan->price,
            'currency'       => 'TWD',
            'payment_method' => 'credit_card',
            'status'         => 'paid',
            'paid_at'        => now()->subDays(25),
            'expires_at'     => now()->subDays(25),
        ]);

        return Subscription::create([
            'user_id'    => $user->id,
            'plan_id'    => $plan->id,
            'order_id'   => $order->id,
            'status'     => 'active',
            'auto_renew' => true,
            'started_at' => now()->subDays(25),
            'expires_at' => now()->addDays($days),
        ]);
    }

    // ─── Case 11: sub expires in 2 days → user notified ───
    public function test_subscription_expiring_within_3_days_triggers_notification(): void
    {
        $sub = $this->makeSubscriptionExpiringIn(2);

        $this->artisan('subscriptions:notify-expiring')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $sub->user_id,
            'type'    => 'subscription_expiring',
        ]);

        $notification = \DB::table('notifications')
            ->where('user_id', $sub->user_id)
            ->where('type', 'subscription_expiring')
            ->first();
        $data = json_decode($notification->data, true);
        $this->assertEquals($sub->id, $data['subscription_id'] ?? null);
    }

    // ─── Case 12: sub expires in 5 days → no notification ───
    public function test_subscription_expiring_beyond_3_days_is_not_notified(): void
    {
        $sub = $this->makeSubscriptionExpiringIn(5);

        $this->artisan('subscriptions:notify-expiring')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $sub->user_id,
            'type'    => 'subscription_expiring',
        ]);
    }

    // ─── Case 13: same sub already notified → not duplicated ───
    public function test_already_notified_subscription_is_not_duplicated(): void
    {
        $sub = $this->makeSubscriptionExpiringIn(2);

        // Run once
        $this->artisan('subscriptions:notify-expiring')->assertSuccessful();
        $countAfterFirst = Notification::where('user_id', $sub->user_id)
            ->where('type', 'subscription_expiring')
            ->count();
        $this->assertEquals(1, $countAfterFirst);

        // Run again (simulating next-day cron firing)
        $this->artisan('subscriptions:notify-expiring')->assertSuccessful();
        $countAfterSecond = Notification::where('user_id', $sub->user_id)
            ->where('type', 'subscription_expiring')
            ->count();
        $this->assertEquals(1, $countAfterSecond, 'Notification must not duplicate for the same subscription');
    }
}
