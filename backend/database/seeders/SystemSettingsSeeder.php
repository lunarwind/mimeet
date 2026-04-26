<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 系統基礎設定 ──────────────────────────────────────────
        $defaults = [
            ['key_name' => 'data_retention_days', 'value' => '365', 'value_type' => 'integer', 'description' => '用戶活動日誌保留天數'],
            ['key_name' => 'trial_plan_price',    'value' => '199', 'value_type' => 'integer', 'description' => '體驗方案價格'],
            ['key_name' => 'trial_plan_days',     'value' => '30',  'value_type' => 'integer', 'description' => '體驗方案天數'],
            ['key_name' => 'ecpay_is_sandbox',    'value' => 'true','value_type' => 'boolean', 'description' => '綠界測試模式'],
            ['key_name' => 'ecpay_merchant_id',   'value' => '3002607','value_type' => 'string','description' => '綠界商店代號'],
            ['key_name' => 'app_mode',            'value' => 'normal','value_type' => 'string','description' => '系統運作模式'],
            ['key_name' => 'sms_provider',        'value' => 'disabled','value_type' => 'string','description' => 'SMS 服務提供者'],
        ];

        foreach ($defaults as $setting) {
            SystemSetting::updateOrCreate(['key_name' => $setting['key_name']], $setting);
        }

        // ─── 誠信分數配分（DEV-008 §3，規格 = Code Default）──────────
        // ⚠️ 扣分 key 一律存正值，Service 內統一轉負（-getConfig(...)）
        // 本 seeder 是雙保險之一（另一層是 Service 的 default 參數）。
        // 後台「系統設定 → 誠信分數配分」可即時調整，儲存後快取失效即生效。

        $creditScoreSettings = [
            // 基準分數
            ['key_name' => 'credit_score_initial',           'value' => '60', 'description' => '帳號建立初始誠信分數'],
            ['key_name' => 'credit_score_unblock_threshold', 'value' => '30', 'description' => '自動停權後解停門檻（≥此分數可解停）'],

            // 加分項（完整觸發事件即生效）
            ['key_name' => 'credit_add_email_verify',        'value' => '5',  'description' => '完成 Email 驗證'],
            ['key_name' => 'credit_add_phone_verify',        'value' => '5',  'description' => '完成手機驗證'],
            ['key_name' => 'credit_add_adv_verify_male',     'value' => '15', 'description' => '男性信用卡進階驗證完成'],
            ['key_name' => 'credit_add_adv_verify_female',   'value' => '15', 'description' => '女性照片進階驗證審核通過'],
            ['key_name' => 'credit_add_date_gps',            'value' => '5',  'description' => 'QR 約會驗證成功（GPS ≤ 500m）'],
            ['key_name' => 'credit_add_date_no_gps',         'value' => '2',  'description' => 'QR 約會驗證成功（無 GPS）'],
            ['key_name' => 'credit_add_report_refund',       'value' => '10', 'description' => '檢舉不成立／用戶自取消，退還檢舉人扣分'],

            // 扣分項（正值！Service 內轉負）
            ['key_name' => 'credit_sub_date_noshow',         'value' => '10', 'description' => '約會爽約（管理員認定）'],
            ['key_name' => 'credit_sub_report_user',         'value' => '10', 'description' => '提交一般檢舉（雙方各扣此值）'],
            ['key_name' => 'credit_sub_report_anon',         'value' => '5',  'description' => '提交匿名室檢舉（雙方各扣此值，Phase 2）'],
            ['key_name' => 'credit_sub_report_penalty',      'value' => '5',  'description' => '檢舉屬實對被舉方額外處分'],
            ['key_name' => 'credit_sub_bad_content',         'value' => '5',  'description' => '管理員刪除違規內容時扣分'],
            ['key_name' => 'credit_sub_harassment',          'value' => '20', 'description' => '惡意騷擾（管理員認定）'],

            // 管理員裁量範圍（對稱 ±20）
            ['key_name' => 'credit_admin_reward_min',        'value' => '1',  'description' => '管理員手動獎勵最小值'],
            ['key_name' => 'credit_admin_reward_max',        'value' => '20', 'description' => '管理員手動獎勵最大值'],
            ['key_name' => 'credit_admin_penalty_min',       'value' => '1',  'description' => '管理員手動懲罰最小值（絕對值）'],
            ['key_name' => 'credit_admin_penalty_max',       'value' => '20', 'description' => '管理員手動懲罰最大值（絕對值，對稱 ±20）'],
        ];

        foreach ($creditScoreSettings as $setting) {
            SystemSetting::updateOrCreate(
                ['key_name' => $setting['key_name']],
                [
                    'value'      => $setting['value'],
                    'value_type' => 'integer',
                    'description'=> $setting['description'],
                ]
            );
        }
    }
}
