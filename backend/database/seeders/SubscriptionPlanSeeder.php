<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        // ── Core plans (5) ──────────────────────────────────────
        $corePlans = [
            [
                'slug'             => 'plan_weekly',
                'name'             => '週費方案',
                'price'            => 149,
                'original_price'   => 149,
                'duration_days'    => 7,
                'membership_level' => 3,
                'is_trial'         => false,
                'is_active'        => true,
                'currency'         => 'TWD',
                'features'         => json_encode(['無限訊息', '查看誰瀏覽過你', '進階搜尋']),
            ],
            [
                'slug'             => 'plan_monthly',
                'name'             => '月費方案',
                'price'            => 399,
                'original_price'   => 399,
                'duration_days'    => 30,
                'membership_level' => 3,
                'is_trial'         => false,
                'is_active'        => true,
                'currency'         => 'TWD',
                'features'         => json_encode(['無限訊息', '查看誰瀏覽過你', '進階搜尋', '優先曝光']),
            ],
            [
                'slug'             => 'plan_quarterly',
                'name'             => '季費方案',
                'price'            => 1077,
                'original_price'   => 1197,
                'duration_days'    => 90,
                'membership_level' => 3,
                'is_trial'         => false,
                'is_active'        => true,
                'currency'         => 'TWD',
                'features'         => json_encode(['無限訊息', '查看誰瀏覽過你', '進階搜尋', '優先曝光', '專屬徽章']),
            ],
            [
                'slug'             => 'plan_yearly',
                'name'             => '年費方案',
                'price'            => 3832,
                'original_price'   => 4788,
                'duration_days'    => 365,
                'membership_level' => 3,
                'is_trial'         => false,
                'is_active'        => true,
                'currency'         => 'TWD',
                'features'         => json_encode(['無限訊息', '查看誰瀏覽過你', '進階搜尋', '優先曝光', '專屬徽章', 'VIP 客服']),
            ],
            [
                'slug'             => 'plan_trial',
                'name'             => '體驗方案',
                'price'            => 199,
                'original_price'   => 199,
                'duration_days'    => 30,
                'membership_level' => 3,
                'is_trial'         => true,
                'is_active'        => true,
                'currency'         => 'TWD',
                'features'         => json_encode(['無限訊息', '進階搜尋']),
            ],
        ];

        foreach ($corePlans as $plan) {
            DB::table('subscription_plans')->updateOrInsert(
                ['slug' => $plan['slug']],
                array_merge($plan, ['created_at' => now(), 'updated_at' => now()]),
            );
        }

        $this->command->info('  ✓ Core 5 plans seeded (plan_weekly/monthly/quarterly/yearly/trial)');

        // ── Flexible plans plan01~plan10 (inactive by default) ──
        for ($i = 1; $i <= 10; $i++) {
            $slug = 'plan' . str_pad($i, 2, '0', STR_PAD_LEFT);
            DB::table('subscription_plans')->updateOrInsert(
                ['slug' => $slug],
                [
                    'slug'             => $slug,
                    'name'             => $slug,
                    'price'            => 0,
                    'original_price'   => 0,
                    'duration_days'    => 30,
                    'membership_level' => 3,
                    'is_trial'         => false,
                    'is_active'        => false,
                    'currency'         => 'TWD',
                    'features'         => json_encode([]),
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ],
            );
        }

        $this->command->info('  ✓ Flexible 10 plans seeded (plan01~plan10, inactive)');
        $this->command->info('  Total: ' . DB::table('subscription_plans')->count() . ' plans');
    }
}
