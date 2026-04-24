<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'data_retention_days', 'value' => '365', 'type' => 'integer', 'group' => 'privacy', 'description' => '用戶活動日誌保留天數'],
            ['key' => 'credit_score_initial', 'value' => '60', 'type' => 'integer', 'group' => 'credit', 'description' => '新用戶初始誠信分數'],
            ['key' => 'credit_score_suspend_threshold', 'value' => '0', 'type' => 'integer', 'group' => 'credit', 'description' => '停權門檻分數'],
            ['key' => 'trial_plan_price', 'value' => '199', 'type' => 'integer', 'group' => 'subscription', 'description' => '體驗方案價格'],
            ['key' => 'trial_plan_days', 'value' => '30', 'type' => 'integer', 'group' => 'subscription', 'description' => '體驗方案天數'],
            ['key' => 'ecpay_is_sandbox', 'value' => 'true', 'type' => 'boolean', 'group' => 'payment', 'description' => '綠界測試模式'],
            ['key' => 'ecpay_merchant_id', 'value' => '3002607', 'type' => 'string', 'group' => 'payment', 'description' => '綠界商店代號'],
            ['key' => 'app_mode', 'value' => 'normal', 'type' => 'string', 'group' => 'system', 'description' => '系統運作模式'],
            ['key' => 'sms_provider', 'value' => 'disabled', 'type' => 'string', 'group' => 'sms', 'description' => 'SMS 服務提供者'],
        ];

        foreach ($defaults as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
