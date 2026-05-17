<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ── 1. 先刪除所有舊格式 ECPay key（single source of truth）────
        DB::table('system_settings')
            ->where(function ($q) {
                $q->where('key_name', 'like', 'ecpay.%')        // dot notation（舊）
                  ->orWhere('key_name', 'ecpay_is_sandbox')      // 舊布林 key
                  ->orWhere('key_name', 'ecpay_merchant_id')     // 無環境前綴
                  ->orWhere('key_name', 'ecpay_invoice_merchant_id')  // 舊發票 key
                  ->orWhere('key_name', 'ecpay_invoice_hash_key')
                  ->orWhere('key_name', 'ecpay_invoice_hash_iv')
                  ->orWhere('key_name', 'app.mode');             // dot notation
            })
            ->delete();

        // ── 2. 系統基礎設定 ──────────────────────────────────────────
        $basics = [
            ['key_name' => 'data_retention_days', 'value' => '180',     'value_type' => 'integer', 'description' => '資料保留天數（180 天，DEV-001 §6.3.1）'],
            ['key_name' => 'trial_plan_price',    'value' => '199',     'value_type' => 'integer', 'description' => '體驗方案價格'],
            ['key_name' => 'trial_plan_days',     'value' => '30',      'value_type' => 'integer', 'description' => '體驗方案天數'],
            ['key_name' => 'app_mode',            'value' => 'testing', 'value_type' => 'string',  'description' => '應用模式（testing / production）— 影響 Email/SMS 是否真送'],
            ['key_name' => 'sms_provider',        'value' => 'disabled','value_type' => 'string',  'description' => 'SMS 服務提供者'],
        ];
        foreach ($basics as $s) {
            SystemSetting::updateOrCreate(['key_name' => $s['key_name']], array_merge($s, ['created_at' => $now, 'updated_at' => $now]));
        }

        // ── 3. ECPay 環境開關（單一真實來源）────────────────────────
        SystemSetting::updateOrCreate(['key_name' => 'ecpay_environment'], [
            'value' => 'sandbox', 'value_type' => 'string',
            'description' => '綠界環境（sandbox / production）— 同時控制金流與發票',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // ── 4. 金流憑證 — sandbox（綠界官方測試特店 3002607）─────────
        $sandboxPayment = [
            ['key_name' => 'ecpay_sandbox_merchant_id', 'value' => '3002607',                              'is_encrypted' => false,
             'description' => '綠界金流 sandbox MerchantID（官方測試特店）'],
            ['key_name' => 'ecpay_sandbox_hash_key',    'value' => Crypt::encryptString('pwFHCqoQZGmho4w6'), 'is_encrypted' => true,
             'description' => '綠界金流 sandbox HashKey（加密）'],
            ['key_name' => 'ecpay_sandbox_hash_iv',     'value' => Crypt::encryptString('EkRm7iFT261dpevs'), 'is_encrypted' => true,
             'description' => '綠界金流 sandbox HashIV（加密）'],
        ];
        foreach ($sandboxPayment as $s) {
            DB::table('system_settings')->updateOrInsert(
                ['key_name' => $s['key_name']],
                ['value' => $s['value'], 'value_type' => 'secret', 'is_encrypted' => $s['is_encrypted'],
                 'description' => $s['description'], 'created_at' => $now, 'updated_at' => $now],
            );
        }

        // ── 5. 金流憑證 — production（預設空，後台填）───────────────
        $productionPayment = [
            ['key_name' => 'ecpay_production_merchant_id', 'description' => '綠界金流正式 MerchantID'],
            ['key_name' => 'ecpay_production_hash_key',    'description' => '綠界金流正式 HashKey（加密）'],
            ['key_name' => 'ecpay_production_hash_iv',     'description' => '綠界金流正式 HashIV（加密）'],
        ];
        foreach ($productionPayment as $s) {
            SystemSetting::updateOrCreate(['key_name' => $s['key_name']], [
                'value' => '', 'value_type' => 'secret', 'is_encrypted' => false,
                'description' => $s['description'], 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ── 6. 發票開關 ───────────────────────────────────────────────
        $invoiceCommon = [
            ['key_name' => 'ecpay_invoice_enabled',             'value' => '0',      'value_type' => 'boolean', 'description' => '是否啟用電子發票開立'],
            ['key_name' => 'ecpay_invoice_donation_love_code',  'value' => '168001', 'value_type' => 'string',  'description' => '預設捐贈愛心碼'],
        ];
        foreach ($invoiceCommon as $s) {
            SystemSetting::updateOrCreate(['key_name' => $s['key_name']], array_merge($s, ['created_at' => $now, 'updated_at' => $now]));
        }

        // ── 7. 發票憑證 — sandbox（綠界官方測試特店 2000132）─────────
        $sandboxInvoice = [
            ['key_name' => 'ecpay_invoice_sandbox_merchant_id', 'value' => '2000132',                              'is_encrypted' => false,
             'description' => '綠界發票 sandbox MerchantID（官方測試特店 2000132）'],
            ['key_name' => 'ecpay_invoice_sandbox_hash_key',    'value' => Crypt::encryptString('ejCk326UnaZWKisg'), 'is_encrypted' => true,
             'description' => '綠界發票 sandbox HashKey（加密）'],
            ['key_name' => 'ecpay_invoice_sandbox_hash_iv',     'value' => Crypt::encryptString('q9jcZX8Ib9LM8wYk'), 'is_encrypted' => true,
             'description' => '綠界發票 sandbox HashIV（加密）'],
        ];
        foreach ($sandboxInvoice as $s) {
            DB::table('system_settings')->updateOrInsert(
                ['key_name' => $s['key_name']],
                ['value' => $s['value'], 'value_type' => 'secret', 'is_encrypted' => $s['is_encrypted'],
                 'description' => $s['description'], 'created_at' => $now, 'updated_at' => $now],
            );
        }

        // ── 8. 發票憑證 — production（預設空，後台填）───────────────
        $productionInvoice = [
            ['key_name' => 'ecpay_invoice_production_merchant_id', 'description' => '綠界發票正式 MerchantID'],
            ['key_name' => 'ecpay_invoice_production_hash_key',    'description' => '綠界發票正式 HashKey（加密）'],
            ['key_name' => 'ecpay_invoice_production_hash_iv',     'description' => '綠界發票正式 HashIV（加密）'],
        ];
        foreach ($productionInvoice as $s) {
            SystemSetting::updateOrCreate(['key_name' => $s['key_name']], [
                'value' => '', 'value_type' => 'secret', 'is_encrypted' => false,
                'description' => $s['description'], 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ── 9. 誠信分數配分（DEV-008 §3）────────────────────────────
        $creditScoreSettings = [
            ['key_name' => 'credit_score_initial',           'value' => '60', 'description' => '帳號建立初始誠信分數'],
            ['key_name' => 'credit_score_unblock_threshold', 'value' => '30', 'description' => '自動停權後解停門檻（≥此分數可解停）'],
            ['key_name' => 'credit_add_email_verify',        'value' => '5',  'description' => '完成 Email 驗證'],
            ['key_name' => 'credit_add_phone_verify',        'value' => '5',  'description' => '完成手機驗證'],
            ['key_name' => 'credit_add_adv_verify_male',     'value' => '15', 'description' => '男性信用卡進階驗證完成'],
            ['key_name' => 'credit_add_adv_verify_female',   'value' => '15', 'description' => '女性照片進階驗證審核通過'],
            ['key_name' => 'credit_add_date_gps',            'value' => '5',  'description' => 'QR 約會驗證成功（GPS ≤ 500m）'],
            ['key_name' => 'credit_add_date_no_gps',         'value' => '2',  'description' => 'QR 約會驗證成功（無 GPS）'],
            ['key_name' => 'credit_add_report_refund',       'value' => '10', 'description' => '檢舉不成立／用戶自取消，退還檢舉人扣分'],
            ['key_name' => 'credit_sub_date_noshow',         'value' => '10', 'description' => '約會爽約（管理員認定）'],
            ['key_name' => 'credit_sub_report_user',         'value' => '10', 'description' => '提交一般檢舉（雙方各扣此值）'],
            ['key_name' => 'credit_sub_report_anon',         'value' => '5',  'description' => '提交匿名室檢舉（Phase 2）'],
            ['key_name' => 'credit_sub_report_penalty',      'value' => '5',  'description' => '檢舉屬實對被舉方額外處分'],
            ['key_name' => 'credit_sub_bad_content',         'value' => '5',  'description' => '管理員刪除違規內容時扣分'],
            ['key_name' => 'credit_sub_harassment',          'value' => '20', 'description' => '惡意騷擾（管理員認定）'],
            ['key_name' => 'credit_admin_reward_min',        'value' => '1',  'description' => '管理員手動獎勵最小值'],
            ['key_name' => 'credit_admin_reward_max',        'value' => '20', 'description' => '管理員手動獎勵最大值'],
            ['key_name' => 'credit_admin_penalty_min',       'value' => '1',  'description' => '管理員手動懲罰最小值（絕對值）'],
            ['key_name' => 'credit_admin_penalty_max',       'value' => '20', 'description' => '管理員手動懲罰最大值（絕對值）'],
        ];
        foreach ($creditScoreSettings as $s) {
            SystemSetting::updateOrCreate(
                ['key_name' => $s['key_name']],
                ['value' => $s['value'], 'value_type' => 'integer', 'description' => $s['description']],
            );
        }
    }
}
