<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 將 credit_score_histories.type 欄位值對齊 DEV-008 §10.3 規格。
 *
 * 對照映射（依決策 2026-04-25）：
 *
 * 單純改名（down() 可精準還原）：
 *   email_verified       → email_verify
 *   phone_verified       → phone_verify
 *   date_verified        → date_gps / date_no_gps（未分路的舊資料還原為 date_verified）
 *   report_penalty       → report_result_penalty
 *   report_dismissed     → report_result_refund
 *   report_cancelled     → report_result_refund
 *   appeal_approved      → appeal_refund
 *   verification_approved → adv_verify_female
 *
 * 合併（down() 為最佳推測還原，可能無法精準區分）：
 *   admin_adjust + admin_set → 依 delta 符號已在程式碼層分路為 admin_reward / admin_penalty；
 *                              DB 中舊資料若 admin_adjust 存在，不確定正負，統一還原為 admin_adjust
 *   report_filed + report_received → 合入 report_submit；
 *                              down() 統一還原為 report_filed（喪失 received 的區分）
 *
 * ⚠️ 截至 2026-04-25，DB 中幾乎無真實生產資料（只有 1 筆測試紀錄），
 *    遷移風險極低。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // 單純改名
            DB::table('credit_score_histories')
                ->where('type', 'email_verified')
                ->update(['type' => 'email_verify']);

            DB::table('credit_score_histories')
                ->where('type', 'phone_verified')
                ->update(['type' => 'phone_verify']);

            DB::table('credit_score_histories')
                ->where('type', 'date_verified')
                ->update(['type' => 'date_gps']); // 舊資料無 GPS 資訊，保守還原為 date_gps

            DB::table('credit_score_histories')
                ->where('type', 'report_penalty')
                ->update(['type' => 'report_result_penalty']); // 單純改名，可精準還原

            DB::table('credit_score_histories')
                ->where('type', 'report_dismissed')
                ->update(['type' => 'report_result_refund']); // 語意改名，可精準還原

            DB::table('credit_score_histories')
                ->where('type', 'report_cancelled')
                ->update(['type' => 'report_result_refund']); // 合入 report_result_refund（用戶自取消退分）

            DB::table('credit_score_histories')
                ->where('type', 'appeal_approved')
                ->update(['type' => 'appeal_refund']); // 單純改名，可精準還原

            DB::table('credit_score_histories')
                ->where('type', 'verification_approved')
                ->update(['type' => 'adv_verify_female']); // 單純改名，可精準還原

            // 管理員調分：admin_adjust / admin_set → admin_reward / admin_penalty
            // ⚠️ down() 僅能最佳推測還原（無法知道原始分正負）
            DB::table('credit_score_histories')
                ->whereIn('type', ['admin_adjust', 'admin_set'])
                ->update(['type' => 'admin_penalty']); // 保守還原為 admin_penalty

            // 提交檢舉雙方扣分：report_filed + report_received → report_submit
            // ⚠️ down() 僅能還原為 report_filed（喪失 received 區分）
            DB::table('credit_score_histories')
                ->whereIn('type', ['report_filed', 'report_received'])
                ->update(['type' => 'report_submit']);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            // 單純改名，可精準還原
            DB::table('credit_score_histories')
                ->where('type', 'email_verify')
                ->update(['type' => 'email_verified']);

            DB::table('credit_score_histories')
                ->where('type', 'phone_verify')
                ->update(['type' => 'phone_verified']);

            // date_gps / date_no_gps → 還原為 date_verified（無法區分原始 GPS 狀態）
            DB::table('credit_score_histories')
                ->whereIn('type', ['date_gps', 'date_no_gps'])
                ->update(['type' => 'date_verified']);

            // report_result_penalty → 此為單純改名，可精準還原
            DB::table('credit_score_histories')
                ->where('type', 'report_result_penalty')
                ->update(['type' => 'report_penalty']);

            // appeal_refund → 此為單純改名，可精準還原
            DB::table('credit_score_histories')
                ->where('type', 'appeal_refund')
                ->update(['type' => 'appeal_approved']);

            // adv_verify_female → 此為單純改名，可精準還原
            DB::table('credit_score_histories')
                ->where('type', 'adv_verify_female')
                ->update(['type' => 'verification_approved']);

            // ⚠️ report_result_refund 包含兩個來源：
            //    - 原 report_dismissed（檢舉不成立退還）
            //    - 原 report_cancelled（用戶取消退還）
            //    無法區分，統一還原為 report_dismissed
            DB::table('credit_score_histories')
                ->where('type', 'report_result_refund')
                ->update(['type' => 'report_dismissed']);

            // ⚠️ report_submit 包含原 report_filed + report_received，統一還原為 report_filed
            DB::table('credit_score_histories')
                ->where('type', 'report_submit')
                ->update(['type' => 'report_filed']);

            // ⚠️ admin_penalty 還原為 admin_adjust（無法知道原始分正負，合併後資訊遺失）
            DB::table('credit_score_histories')
                ->whereIn('type', ['admin_penalty', 'admin_reward'])
                ->update(['type' => 'admin_adjust']);
        });
    }
};
