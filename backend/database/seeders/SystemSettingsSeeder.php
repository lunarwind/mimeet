<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key_name' => 'data_retention_days', 'value' => '365', 'value_type' => 'integer', 'description' => '用戶活動日誌保留天數'],
            ['key_name' => 'credit_score_initial', 'value' => '60', 'value_type' => 'integer', 'description' => '新用戶初始誠信分數'],
            ['key_name' => 'credit_score_suspend_threshold', 'value' => '0', 'value_type' => 'integer', 'description' => '停權門檻分數'],
            // DEV-008 加分 key（後台可動態調整）
            ['key_name' => 'credit_add_email_verify', 'value' => '5', 'value_type' => 'integer', 'description' => 'Email 驗證完成加分'],
            ['key_name' => 'credit_add_phone_verify', 'value' => '5', 'value_type' => 'integer', 'description' => '手機驗證完成加分'],
            ['key_name' => 'credit_add_adv_verify_female', 'value' => '15', 'value_type' => 'integer', 'description' => '女性照片進階驗證通過加分'],
            ['key_name' => 'credit_add_adv_verify_male', 'value' => '15', 'value_type' => 'integer', 'description' => '男性信用卡進階驗證通過加分'],
            ['key_name' => 'credit_add_date_gps', 'value' => '5', 'value_type' => 'integer', 'description' => 'QR 約會驗證（GPS 通過）加分'],
            ['key_name' => 'credit_add_date_no_gps', 'value' => '2', 'value_type' => 'integer', 'description' => 'QR 約會驗證（無 GPS）加分'],
            // DEV-008 扣分 key（負值）
            ['key_name' => 'credit_sub_report_user', 'value' => '-10', 'value_type' => 'integer', 'description' => '提交/收到一般檢舉扣分（負值）'],
            ['key_name' => 'credit_sub_report_anon', 'value' => '-5', 'value_type' => 'integer', 'description' => '匿名聊天室檢舉扣分（負值，Phase 2）'],
            ['key_name' => 'credit_sub_date_noshow', 'value' => '-10', 'value_type' => 'integer', 'description' => '約會爽約扣分（負值）'],
            ['key_name' => 'credit_sub_bad_content', 'value' => '-5', 'value_type' => 'integer', 'description' => '發布不當內容扣分（負值）'],
            ['key_name' => 'credit_sub_harassment', 'value' => '-20', 'value_type' => 'integer', 'description' => '惡意騷擾扣分（負值）'],
            ['key_name' => 'credit_sub_additional_penalty', 'value' => '-5', 'value_type' => 'integer', 'description' => '檢舉屬實額外處分（負值）'],
            ['key_name' => 'trial_plan_price', 'value' => '199', 'value_type' => 'integer', 'description' => '體驗方案價格'],
            ['key_name' => 'trial_plan_days', 'value' => '30', 'value_type' => 'integer', 'description' => '體驗方案天數'],
            ['key_name' => 'ecpay_is_sandbox', 'value' => 'true', 'value_type' => 'boolean', 'description' => '綠界測試模式'],
            ['key_name' => 'ecpay_merchant_id', 'value' => '3002607', 'value_type' => 'string', 'description' => '綠界商店代號'],
            ['key_name' => 'app_mode', 'value' => 'normal', 'value_type' => 'string', 'description' => '系統運作模式'],
            ['key_name' => 'sms_provider', 'value' => 'disabled', 'value_type' => 'string', 'description' => 'SMS 服務提供者'],
        ];

        foreach ($defaults as $setting) {
            SystemSetting::updateOrCreate(
                ['key_name' => $setting['key_name']],
                $setting
            );
        }
    }
}
