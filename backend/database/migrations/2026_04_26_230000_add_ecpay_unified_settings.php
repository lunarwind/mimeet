<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 6：後台金流設定統一化
 *
 * 1. system_settings 加 is_encrypted 欄位
 * 2. 舊 ecpay.* dot-notation key 遷移到新 ecpay_* 格式
 * 3. 插入 7 個新 ECPay key（加密欄位用 Crypt::encryptString）
 * 4. 插入 ecpay_invoice_enabled key
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. 加 is_encrypted 欄位 ───────────────────────────────────
        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'is_encrypted')) {
                $table->boolean('is_encrypted')->default(false)->after('value')
                      ->comment('true = value 用 Crypt::encryptString 加密');
            }
        });

        // ── 2. 讀取舊 key 值 ─────────────────────────────────────────
        $oldMode     = DB::table('system_settings')->where('key_name', 'ecpay.mode')->value('value') ?? 'sandbox';
        $oldMid      = DB::table('system_settings')->where('key_name', 'ecpay.payment.merchant_id')->value('value') ?? '';
        $oldHashKey  = DB::table('system_settings')->where('key_name', 'ecpay.payment.hash_key')->value('value') ?? '';
        $oldHashIv   = DB::table('system_settings')->where('key_name', 'ecpay.payment.hash_iv')->value('value') ?? '';
        $oldInvEn    = DB::table('system_settings')->where('key_name', 'ecpay.invoice.enabled')->value('value') ?? '0';

        $isSandbox = ($oldMode !== 'production');

        // 決定把舊 payment 憑證放到哪個環境 slot
        $envSlot = $isSandbox ? 'sandbox' : 'production';

        // ── 3. 插入 / 更新 7 個新 ECPay key ─────────────────────────
        $now = now()->toDateTimeString();

        $ecpayKeys = [
            // 環境（明文）
            [
                'key_name'     => 'ecpay_environment',
                'value'        => $isSandbox ? 'sandbox' : 'production',
                'is_encrypted' => false,
                'description'  => 'ECPay 環境（sandbox / production）',
            ],
            // 沙箱憑證
            [
                'key_name'     => 'ecpay_sandbox_merchant_id',
                'value'        => $isSandbox ? $oldMid : '2000132',  // 舊沙箱值或公開測試值
                'is_encrypted' => false,
                'description'  => '沙箱 MerchantID（空值 fallback ECPay 公開測試值）',
            ],
            [
                'key_name'     => 'ecpay_sandbox_hash_key',
                'value'        => ($isSandbox && $oldHashKey !== '') ? self::tryEncrypt($oldHashKey) : '',
                'is_encrypted' => true,
                'description'  => '沙箱 HashKey（空值 fallback 公開測試值 5294y06JbISpM5x9）',
            ],
            [
                'key_name'     => 'ecpay_sandbox_hash_iv',
                'value'        => ($isSandbox && $oldHashIv !== '') ? self::tryEncrypt($oldHashIv) : '',
                'is_encrypted' => true,
                'description'  => '沙箱 HashIV（空值 fallback 公開測試值 v77hoKGq4kWxNNIS）',
            ],
            // 正式憑證
            [
                'key_name'     => 'ecpay_production_merchant_id',
                'value'        => (!$isSandbox) ? $oldMid : '',
                'is_encrypted' => false,
                'description'  => '正式 MerchantID（上線前必填）',
            ],
            [
                'key_name'     => 'ecpay_production_hash_key',
                'value'        => (!$isSandbox && $oldHashKey !== '') ? self::tryEncrypt($oldHashKey) : '',
                'is_encrypted' => true,
                'description'  => '正式 HashKey（加密儲存）',
            ],
            [
                'key_name'     => 'ecpay_production_hash_iv',
                'value'        => (!$isSandbox && $oldHashIv !== '') ? self::tryEncrypt($oldHashIv) : '',
                'is_encrypted' => true,
                'description'  => '正式 HashIV（加密儲存）',
            ],
            // 發票開關
            [
                'key_name'     => 'ecpay_invoice_enabled',
                'value'        => ($oldInvEn === '1' || $oldInvEn === 'true') ? '1' : '0',
                'is_encrypted' => false,
                'description'  => '是否啟用綠界電子發票（預設關閉）',
            ],
        ];

        foreach ($ecpayKeys as $key) {
            DB::table('system_settings')->updateOrInsert(
                ['key_name' => $key['key_name']],
                [
                    'value'        => $key['value'],
                    'is_encrypted' => $key['is_encrypted'],
                    'value_type'   => 'string',
                    'description'  => $key['description'],
                    'updated_at'   => $now,
                    'created_at'   => $now,
                ]
            );
        }

        // ── 4. 舊 key 保留不刪（ECPay 後台等舊整合可能仍讀取）────────
        // 舊 key（ecpay.mode / ecpay.payment.*）標記說明，不刪除
        DB::table('system_settings')
            ->whereIn('key_name', ['ecpay.mode', 'ecpay.payment.merchant_id', 'ecpay.payment.hash_key', 'ecpay.payment.hash_iv'])
            ->update(['description' => '[DEPRECATED] 已遷移至 ecpay_* 新格式 key，請勿再使用']);
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('is_encrypted');
        });
        // 刪除新 key
        DB::table('system_settings')->whereIn('key_name', [
            'ecpay_environment', 'ecpay_sandbox_merchant_id', 'ecpay_sandbox_hash_key',
            'ecpay_sandbox_hash_iv', 'ecpay_production_merchant_id',
            'ecpay_production_hash_key', 'ecpay_production_hash_iv', 'ecpay_invoice_enabled',
        ])->delete();
    }

    private static function tryEncrypt(string $value): string
    {
        if ($value === '') return '';
        try {
            return Crypt::encryptString($value);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ECPay Migration] Encrypt failed, storing empty', ['error' => $e->getMessage()]);
            return '';
        }
    }
};
