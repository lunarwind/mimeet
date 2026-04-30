<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill invoice_status for existing payments
 *
 * 背景：PaymentService::issueInvoiceForOrder() 與 issueInvoiceForPointOrder()
 * 在寫入 invoice_no 時遺漏更新 invoice_status，導致歷史記錄 invoice_status
 * 卡在預設值 pending，但實際發票已由 ECPay 開立成功（invoice_no 有值）。
 *
 * 業務規則：invoice_no 有值 ≡ ECPay 已開立 ≡ status 應為 issued。
 * 此 migration 將「invoice_no 有值且 invoice_status 不是 issued」的記錄修正為 issued。
 */
return new class extends Migration
{
    public function up(): void
    {
        $affected = DB::table('payments')
            ->whereNotNull('invoice_no')
            ->where('invoice_no', '!=', '')
            ->where('invoice_status', '!=', 'issued')
            ->update(['invoice_status' => 'issued']);

        Log::info('[Migration] Backfilled invoice_status', [
            'affected_rows' => $affected,
        ]);
    }

    public function down(): void
    {
        // 不可逆 — 一旦 backfill，無法判斷原本的 status 是 pending 還是 NULL
        // 這是預期行為：backfill 是修正錯誤狀態，不應該還原為錯誤狀態
    }
};
