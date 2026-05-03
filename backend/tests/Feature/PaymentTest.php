<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'membership_level' => 1,
            'credit_score' => 60,
            'status' => 'active',
        ], $attrs));
    }

    private function seedPlans(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
    }

    public function test_can_list_plans(): void
    {
        $this->seedPlans();
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/subscriptions/plans');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data.plans');
    }

    public function test_can_create_order(): void
    {
        $this->seedPlans();
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/api/v1/subscriptions/orders', [
            'plan_id' => 'plan_monthly',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'payment_id',
                    'aio_url',
                    'params' => [
                        'MerchantTradeNo',
                        'TotalAmount',
                        'CheckMacValue',
                    ],
                    'order' => ['order_number', 'amount'],
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_mock_payment_activates_subscription(): void
    {
        $this->seedPlans();
        $user = $this->createUser(['membership_level' => 1]);

        $orderResponse = $this->actingAs($user)->postJson('/api/v1/subscriptions/orders', [
            'plan_id' => 'plan_monthly',
        ]);

        $cb = $this->payEcpayCallback($orderResponse);
        $cb->assertOk();
        $this->assertEquals('1|OK', $cb->getContent());

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $user->refresh();
        $this->assertEquals(3, $user->membership_level);
    }

    public function test_trial_can_only_be_used_once(): void
    {
        $this->seedPlans();
        $user = $this->createUser();

        $resp1 = $this->actingAs($user)->postJson('/api/v1/subscriptions/orders', [
            'plan_id' => 'plan_trial',
        ]);
        $resp1->assertStatus(201);
        $this->payEcpayCallback($resp1)->assertOk();

        $resp2 = $this->actingAs($user)->postJson('/api/v1/subscriptions/orders', [
            'plan_id' => 'plan_trial',
        ]);
        $resp2->assertStatus(422);
    }

    public function test_ecpay_notify_with_valid_payment(): void
    {
        $this->seedPlans();
        $user = $this->createUser();

        $orderResponse = $this->actingAs($user)->postJson('/api/v1/subscriptions/orders', [
            'plan_id' => 'plan_monthly',
        ]);
        $orderNumber = $orderResponse->json('data.order.order_number');

        $this->payEcpayCallback($orderResponse)->assertOk();

        $order = Order::where('order_number', $orderNumber)->first();
        $this->assertEquals('paid', $order->status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_subscription_notification_on_activation(): void
    {
        $this->seedPlans();
        $user = $this->createUser();

        $orderResponse = $this->actingAs($user)->postJson('/api/v1/subscriptions/orders', [
            'plan_id' => 'plan_monthly',
        ]);
        $this->payEcpayCallback($orderResponse)->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'subscription_activated',
        ]);
    }

    public function test_early_renewal_extends_from_existing_expiry(): void
    {
        $this->seedPlans();
        $user = $this->createUser();
        $plan = SubscriptionPlan::where('slug', 'plan_monthly')->first();

        $existingOrder = Order::create([
            'order_number' => 'MM_OLD_ORDER',
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'currency' => 'TWD',
            'payment_method' => 'credit_card',
            'status' => 'paid',
            'paid_at' => now()->subDays(25),
            'expires_at' => now(),
        ]);

        $oldExpiresAt = now()->addDays(5);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'order_id' => $existingOrder->id,
            'status' => 'active',
            'started_at' => now()->subDays(25),
            'expires_at' => $oldExpiresAt,
        ]);

        $orderResp = $this->actingAs($user)->postJson('/api/v1/subscriptions/orders', [
            'plan_id' => 'plan_monthly',
        ]);

        $this->payEcpayCallback($orderResp)->assertOk();

        $newSub = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        $this->assertNotNull($newSub);
        $this->assertEquals(
            $oldExpiresAt->startOfSecond()->toISOString(),
            $newSub->started_at->startOfSecond()->toISOString(),
            'New subscription should start at old expiry date'
        );
        $this->assertEquals(
            $oldExpiresAt->copy()->addDays($plan->duration_days)->startOfSecond()->toISOString(),
            $newSub->expires_at->startOfSecond()->toISOString(),
            'New subscription should expire old_expiry + duration_days'
        );

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => 'expired',
        ]);
    }

    public function test_new_subscription_uses_paid_at_when_no_existing(): void
    {
        $this->seedPlans();
        $user = $this->createUser();

        $orderResp = $this->actingAs($user)->postJson('/api/v1/subscriptions/orders', [
            'plan_id' => 'plan_monthly',
        ]);
        $orderNumber = $orderResp->json('data.order.order_number');

        $this->payEcpayCallback($orderResp)->assertOk();

        $order = Order::where('order_number', $orderNumber)->first();
        $sub = Subscription::where('user_id', $user->id)->where('status', 'active')->first();

        $this->assertNotNull($sub);
        $this->assertEquals(
            $order->paid_at->startOfSecond()->toISOString(),
            $sub->started_at->startOfSecond()->toISOString(),
        );
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->postJson('/api/v1/subscriptions/orders', [
            'plan_id' => 'plan_monthly',
        ]);
        $response->assertStatus(401);
    }
}
