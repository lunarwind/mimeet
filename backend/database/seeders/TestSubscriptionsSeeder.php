<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TestSubscriptionsSeeder extends Seeder
{
    public function run(): void
    {
        $paidUsers = User::where('membership_level', 3)->get();
        $plans = SubscriptionPlan::where('is_trial', false)->get();

        if ($plans->isEmpty()) { $this->command->warn('No subscription plans found'); return; }

        foreach ($paidUsers as $user) {
            $plan = $plans->random();
            $startedAt = now()->subDays(rand(1, 20));

            $order = Order::create([
                'order_number' => 'MM' . now()->format('YmdHis') . strtoupper(Str::random(4)),
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'currency' => 'TWD',
                'payment_method' => 'credit_card',
                'status' => 'paid',
                'ecpay_trade_no' => 'TEST' . strtoupper(Str::random(10)),
                'ecpay_merchant_trade_no' => 'MM' . strtoupper(Str::random(12)),
                'paid_at' => $startedAt,
                'expires_at' => $startedAt->copy()->addMinutes(30),
            ]);

            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'order_id' => $order->id,
                'status' => 'active',
                'auto_renew' => (bool) rand(0, 1),
                'started_at' => $startedAt,
                'expires_at' => $startedAt->copy()->addDays($plan->duration_days),
            ]);
        }

        $this->command->info('Created ' . Order::count() . ' orders, ' . Subscription::count() . ' subscriptions');
    }
}
