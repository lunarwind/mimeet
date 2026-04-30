<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestSubscriptionsSeeder extends Seeder
{
    public function run(): void
    {
        // 注意：此 seeder 假設 fresh 模式（先呼叫 mimeet:reset）
        // 不勾 freshMode 重複匯入會產生重複 payments 記錄
        $paidUsers = User::where('membership_level', 3)->get();
        $plans = SubscriptionPlan::where('is_trial', false)->get();

        if ($plans->isEmpty()) { $this->command->warn('No subscription plans found'); return; }

        foreach ($paidUsers as $user) {
            $plan       = $plans->random();
            $startedAt  = now()->subDays(rand(1, 20));
            $orderNo    = 'MM' . now()->format('YmdHis') . strtoupper(Str::random(4));

            // 1. 先建 payment（SSOT）
            $payment = Payment::create([
                'user_id'        => $user->id,
                'type'           => 'subscription',
                'order_no'       => $orderNo,
                'item_name'      => 'MiMeet ' . $plan->name,
                'amount'         => $plan->price,
                'currency'       => 'TWD',
                'status'         => 'paid',
                'gateway'        => 'ecpay',
                'environment'    => 'sandbox',
                'payment_method' => 'credit_card',
                'paid_at'        => $startedAt,
                'invoice_no'     => 'GS' . str_pad((string) rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                'invoice_issued_at' => $startedAt,
            ]);
            // invoice_status 不在 fillable，透過 forceFill 設定
            $payment->forceFill(['invoice_status' => 'issued'])->save();

            // 2. 建 order（payment_id 不在 fillable，後續用 DB 補填）
            $order = Order::create([
                'order_number'            => $orderNo,
                'user_id'                 => $user->id,
                'plan_id'                 => $plan->id,
                'amount'                  => $plan->price,
                'currency'                => 'TWD',
                'payment_method'          => 'credit_card',
                'status'                  => 'paid',
                'ecpay_trade_no'          => 'TEST' . strtoupper(Str::random(10)),
                'ecpay_merchant_trade_no' => 'MM' . strtoupper(Str::random(12)),
                'paid_at'                 => $startedAt,
                'expires_at'              => $startedAt->copy()->addMinutes(30),
            ]);

            // 3. 補填雙向 FK（兩者都不在 fillable，直接寫 DB）
            DB::table('orders')->where('id', $order->id)->update(['payment_id' => $payment->id]);
            DB::table('payments')->where('id', $payment->id)->update(['reference_id' => $order->id]);

            // 4. 建 subscription
            Subscription::create([
                'user_id'    => $user->id,
                'plan_id'    => $plan->id,
                'order_id'   => $order->id,
                'status'     => 'active',
                'auto_renew' => (bool) rand(0, 1),
                'started_at' => $startedAt,
                'expires_at' => $startedAt->copy()->addDays($plan->duration_days),
            ]);
        }

        $this->command->info(sprintf(
            'Created %d orders, %d subscriptions, %d subscription payments',
            Order::count(),
            Subscription::count(),
            Payment::where('type', 'subscription')->count(),
        ));
    }
}
