<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 修復付款流程 500 錯誤（Phase 2 regression）
 *
 * 根因：Phase 2 migration 把 orders/point_orders/credit_card_verifications
 *       的 payment_id 改為 NOT NULL，但建單流程是「先建業務表記錄，再建 Payment，
 *       最後 update payment_id」，導致 INSERT 噴 SQLSTATE HY000: 1364。
 *
 * 修復：改回 nullable（允許 NULL），保持 application-level 保證（Controller 一定
 *       在 initiate() 後呼叫 update(['payment_id' => ...])）。
 */
return new class extends Migration
{
    public function up(): void
    {
        // orders.payment_id: NOT NULL → nullable
        DB::statement("ALTER TABLE `orders`
            MODIFY COLUMN `payment_id` BIGINT UNSIGNED NULL");

        // 確保 FK 約束不受影響（MySQL nullable FK 合法）
        if (!$this->hasForeignKey('orders', 'orders_payment_id_foreign')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            });
        }

        // point_orders.payment_id: NOT NULL → nullable
        DB::statement("ALTER TABLE `point_orders`
            MODIFY COLUMN `payment_id` BIGINT UNSIGNED NULL");

        if (!$this->hasForeignKey('point_orders', 'point_orders_payment_id_foreign')) {
            Schema::table('point_orders', function (Blueprint $table) {
                $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            });
        }

        // credit_card_verifications.payment_id: NOT NULL → nullable
        DB::statement("ALTER TABLE `credit_card_verifications`
            MODIFY COLUMN `payment_id` BIGINT UNSIGNED NULL");

        if (!$this->hasForeignKey('credit_card_verifications', 'credit_card_verifications_payment_id_foreign')) {
            Schema::table('credit_card_verifications', function (Blueprint $table) {
                $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `orders`
            MODIFY COLUMN `payment_id` BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE `point_orders`
            MODIFY COLUMN `payment_id` BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE `credit_card_verifications`
            MODIFY COLUMN `payment_id` BIGINT UNSIGNED NOT NULL");
    }

    private function hasForeignKey(string $table, string $fkName): bool
    {
        $result = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $fkName],
        );
        return !empty($result);
    }
};
