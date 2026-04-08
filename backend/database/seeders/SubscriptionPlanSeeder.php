<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['slug' => 'plan_weekly', 'name' => '週方案', 'price' => 199, 'duration_days' => 7, 'features' => ['無限訊息', '查看誰瀏覽過你', '進階搜尋']],
            ['slug' => 'plan_monthly', 'name' => '月方案', 'price' => 599, 'duration_days' => 30, 'features' => ['無限訊息', '查看誰瀏覽過你', '進階搜尋', '優先曝光']],
            ['slug' => 'plan_quarterly', 'name' => '季方案', 'price' => 1499, 'duration_days' => 90, 'features' => ['無限訊息', '查看誰瀏覽過你', '進階搜尋', '優先曝光', '專屬徽章']],
            ['slug' => 'plan_yearly', 'name' => '年方案', 'price' => 4999, 'duration_days' => 365, 'features' => ['無限訊息', '查看誰瀏覽過你', '進階搜尋', '優先曝光', '專屬徽章', 'VIP 客服']],
            ['slug' => 'plan_trial', 'name' => '體驗方案', 'price' => 49, 'duration_days' => 3, 'features' => ['無限訊息', '進階搜尋'], 'is_trial' => true],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(
                ['slug' => $plan['slug']],
                array_merge(['currency' => 'TWD', 'membership_level' => 2, 'is_active' => true], $plan),
            );
        }
    }
}
