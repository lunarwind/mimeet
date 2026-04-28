<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 為 payments 主表加入 invoice_status 欄位，追蹤發票開立進度。
 *
 * SSOT：發票資料統一存在 payments 表（invoice_no / invoice_issued_at / invoice_random_number）。
 * invoice_status 用於追蹤異步 IssueInvoiceJob 的執行狀態。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('invoice_status', [
                'pending',         // Job 已 dispatch，等候 worker 處理或 retry 中
                'issued',          // 開立成功
                'failed',          // 重試 3 次後仍失敗，需 admin 介入
                'not_applicable',  // 此付款不開立（如業主決策）
            ])->default('pending')->after('invoice_random_number');

            $table->index('invoice_status');
        });

        // 已有 invoice_no 的記錄直接標記 issued
        \Illuminate\Support\Facades\DB::table('payments')
            ->whereNotNull('invoice_no')
            ->update(['invoice_status' => 'issued']);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('invoice_status');
        });
    }
};
