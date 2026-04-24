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
            // DEV-008 加分 key（後台可動態調整）
            ['key' => 'credit_add_email_verify', 'value' => '5', 'type' => 'integer', 'group' => 'credit', 'description' => 'Email 驗證完成加分'],
            ['key' => 'credit_add_phone_verify', 'value' => '5', 'type' => 'integer', 'group' => 'credit', 'description' => '手機驗證完成加分'],
            ['key' => 'credit_add_adv_verify_female', 'value' => '15', 'type' => 'integer', 'group' => 'credit', 'description' => '女性照片進階驗證通過加分'],
            ['key' => 'credit_add_date_gps', 'value' => '5', 'type' => 'integer', 'group' => 'credit', 'description' => 'QR 約會驗證（GPS 通過）加分'],
            ['key' => 'credit_add_date_no_gps', 'value' => '2', 'type' => 'integer', 'group' => 'credit', 'description' => 'QR 約會驗證（無 GPS）加分'],
            // DEV-008 扣分 key（負值）
            ['key' => 'credit_sub_report_user', 'value' => '-10', 'type' => 'integer', 'group' => 'credit', 'description' => '提交/收到一般檢舉扣分（負值）'],
            ['key' => 'credit_sub_additional_penalty', 'value' => '-5', 'type' => 'integer', 'group' => 'credit', 'description' => '檢舉屬實額外處分（負值）'],
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
