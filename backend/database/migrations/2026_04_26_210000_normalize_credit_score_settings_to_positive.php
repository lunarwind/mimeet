<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 2026-04-26：誠信分數 credit_sub_* 由負值改正值
 *
 * DEV-008 §3 「設計原則：規格 = Code Default」：
 * - credit_sub_* 一律存正值，Service 內統一轉負（-getConfig(...)）
 * - credit_sub_additional_penalty 正名為 credit_sub_report_penalty
 * - 同時補入 credit_score_unblock_threshold 和 credit_add_report_refund
 *
 * 不可逆（down() 故意拋例外）。
 */
return new class extends Migration
{
    public function up(): void
    {
        // (1) 5 個負值改正值
        $negativeKeys = [
            'credit_sub_date_noshow',
            'credit_sub_report_user',
            'credit_sub_report_anon',
            'credit_sub_bad_content',
            'credit_sub_harassment',
        ];
        foreach ($negativeKeys as $key) {
            $row = DB::table('system_settings')->where('key_name', $key)->first();
            if ($row && (int) $row->value < 0) {
                DB::table('system_settings')->where('key_name', $key)->update([
                    'value' => (string) abs((int) $row->value),
                    'updated_at' => now(),
                ]);
            }
        }

        // (2) credit_sub_additional_penalty → credit_sub_report_penalty
        $old = DB::table('system_settings')->where('key_name', 'credit_sub_additional_penalty')->first();
        if ($old) {
            // Check if credit_sub_report_penalty already exists
            $exists = DB::table('system_settings')->where('key_name', 'credit_sub_report_penalty')->exists();
            if (!$exists) {
                DB::table('system_settings')->where('key_name', 'credit_sub_additional_penalty')
                    ->update([
                        'key_name' => 'credit_sub_report_penalty',
                        'value' => (string) abs((int) $old->value),
                        'description' => '檢舉屬實對被舉方額外處分（正值，Service 內轉負）',
                        'updated_at' => now(),
                    ]);
            } else {
                // credit_sub_report_penalty already exists, just delete old key
                DB::table('system_settings')->where('key_name', 'credit_sub_additional_penalty')->delete();
            }
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('This migration is not reversible by design.');
    }
};
