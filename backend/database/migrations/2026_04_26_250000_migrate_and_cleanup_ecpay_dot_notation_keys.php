<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 清理 system_settings 中的舊 ecpay.* 點記法 key。
 *
 * 遷移順序（安全原則）：
 *   1. 複製舊 ecpay.invoice.* 值到新 ecpay_invoice_* key（若新 key 不存在）
 *   2. 刪除所有舊 ecpay.* key
 *
 * 舊 ecpay.payment.* / ecpay.mode 的值已在 Step 6 migration（2026_04_26_230000）
 * 遷移到 ecpay_sandbox_* / ecpay_environment，此處只清理殘留舊 key。
 */
return new class extends Migration
{
    /** 舊 ecpay.* key 完整清單 */
    private const OLD_KEYS = [
        'ecpay.mode',
        'ecpay.payment.merchant_id',
        'ecpay.payment.hash_key',
        'ecpay.payment.hash_iv',
        'ecpay.invoice.merchant_id',
        'ecpay.invoice.hash_key',
        'ecpay.invoice.hash_iv',
        'ecpay.invoice.enabled',
        'ecpay.invoice.donation_love_code',
    ];

    /** 發票 key 舊 → 新 對照 */
    private const INVOICE_KEY_MAP = [
        'ecpay.invoice.merchant_id'      => 'ecpay_invoice_merchant_id',
        'ecpay.invoice.hash_key'         => 'ecpay_invoice_hash_key',
        'ecpay.invoice.hash_iv'          => 'ecpay_invoice_hash_iv',
        'ecpay.invoice.enabled'          => 'ecpay_invoice_enabled',    // 已存在，但再 upsert 一次確保同步
        'ecpay.invoice.donation_love_code' => 'ecpay_invoice_donation_love_code',
    ];

    public function up(): void
    {
        $now = now()->toDateTimeString();

        // 1. 遷移舊發票 key 到新格式（upsert — 已有值則跳過，新 key 不存在則建立）
        foreach (self::INVOICE_KEY_MAP as $oldKey => $newKey) {
            $oldRow = DB::table('system_settings')->where('key_name', $oldKey)->first();
            if (!$oldRow) {
                continue;
            }

            $exists = DB::table('system_settings')->where('key_name', $newKey)->exists();
            if (!$exists) {
                DB::table('system_settings')->insert([
                    'key_name'    => $newKey,
                    'value'       => $oldRow->value ?? '',
                    'value_type'  => 'string',
                    'is_encrypted'=> false,
                    'description' => '（從舊 key ' . $oldKey . ' 遷移）',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }

        // 2. 刪除所有舊 ecpay.* key
        DB::table('system_settings')
            ->whereIn('key_name', self::OLD_KEYS)
            ->delete();

        // 3. 也刪除 [DEPRECATED] 標記的 payment key（Step 6 已標記但未刪）
        DB::table('system_settings')
            ->where('key_name', 'like', 'ecpay.%')
            ->delete();
    }

    public function down(): void
    {
        // 反操作僅作記錄，不在生產執行。
        throw new \RuntimeException('This migration is not reversible in production. Restore from backup if needed.');
    }
};
