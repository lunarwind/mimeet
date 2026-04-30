<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\PointOrder;
use App\Models\PointPackage;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 測試點數購買情境
 *
 * - 為前 10 位非官方用戶建立點數購買訂單（point_orders + payments）
 * - 對應建立點數異動紀錄（point_transactions）
 * - 部分用戶有消費紀錄，points_balance 反映實際結餘
 *
 * 假設 fresh 模式（先呼叫 mimeet:reset）
 */
class TestPointOrdersSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('id', '>', 1)
            ->where('email_verified', true)
            ->take(10)
            ->get();

        if ($users->isEmpty() || PointPackage::count() === 0) {
            $this->command->warn('[TestPointOrdersSeeder] 無可用用戶或 point_packages 為空，跳過');
            return;
        }

        $packages   = PointPackage::all();
        $counter    = 0;
        $consumeFeatures = ['chat_message', 'qr_invite', 'browse'];

        foreach ($users as $user) {
            $purchaseCount = rand(1, 3);
            $balance       = 0;

            for ($n = 0; $n < $purchaseCount; $n++) {
                $counter++;
                $package   = $packages->random();
                $points    = $package->points + ($package->bonus_points ?? 0);
                $tradeNo   = 'PTS' . date('Ymd') . str_pad((string) $counter, 5, '0', STR_PAD_LEFT) . strtoupper(Str::random(4));
                $paidAt    = now()->subDays(rand(1, 60));

                // 1. 先建 payment（SSOT）
                $payment = Payment::create([
                    'user_id'        => $user->id,
                    'type'           => 'points',
                    'order_no'       => $tradeNo,
                    'item_name'      => 'MiMeet 點數 ' . $points . ' 點',
                    'amount'         => $package->price,
                    'currency'       => 'TWD',
                    'status'         => 'paid',
                    'gateway'        => 'ecpay',
                    'environment'    => 'sandbox',
                    'payment_method' => 'credit_card',
                    'paid_at'        => $paidAt,
                    'invoice_no'     => 'GS' . str_pad((string) rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                    'invoice_issued_at' => $paidAt,
                ]);
                $payment->forceFill(['invoice_status' => 'issued'])->save();

                // 2. 建 point_order（payment_id 不在 fillable，後續補填）
                $pointOrder = PointOrder::create([
                    'uuid'           => Str::uuid()->toString(),
                    'user_id'        => $user->id,
                    'package_id'     => $package->id,
                    'points'         => $points,
                    'amount'         => $package->price,
                    'payment_method' => 'credit_card',
                    'trade_no'       => $tradeNo,
                    'gateway_trade_no' => 'TEST' . strtoupper(Str::random(10)),
                    'status'         => 'paid',
                    'paid_at'        => $paidAt,
                ]);

                // 3. 補填雙向 FK
                DB::table('point_orders')->where('id', $pointOrder->id)->update(['payment_id' => $payment->id]);
                DB::table('payments')->where('id', $payment->id)->update(['reference_id' => $pointOrder->id]);

                // 4. purchase 交易記錄
                $balance += $points;
                PointTransaction::create([
                    'user_id'      => $user->id,
                    'type'         => 'purchase',
                    'amount'       => $points,
                    'balance_after' => $balance,
                    'feature'      => null,
                    'reference_id' => $pointOrder->id,
                    'description'  => '購買 ' . $points . ' 點（' . $package->name . '）',
                    'created_at'   => $paidAt,
                ]);
            }

            // 5. 0-2 筆消費記錄
            $spendCount = rand(0, 2);
            for ($s = 0; $s < $spendCount; $s++) {
                $spendAmount = rand(50, 150);
                if ($spendAmount > $balance) break;

                $balance -= $spendAmount;
                PointTransaction::create([
                    'user_id'      => $user->id,
                    'type'         => 'consume',
                    'amount'       => -$spendAmount,
                    'balance_after' => $balance,
                    'feature'      => $consumeFeatures[array_rand($consumeFeatures)],
                    'reference_id' => null,
                    'description'  => '功能消費',
                    'created_at'   => now()->subDays(rand(1, 30)),
                ]);
            }

            // 6. 更新 user.points_balance
            $user->update(['points_balance' => $balance]);
        }

        $this->command->info(sprintf(
            '[TestPointOrdersSeeder] ✅ %d 位用戶 · %d 筆點數購買 · %d 筆 payments',
            $users->count(),
            PointOrder::count(),
            Payment::where('type', 'points')->count(),
        ));
    }
}
