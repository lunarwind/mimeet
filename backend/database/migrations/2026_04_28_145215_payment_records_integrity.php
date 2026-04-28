<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2（A''）：支付紀錄完整性重建（乾淨版，DB 已清空）
 *
 * 1. payments.environment 移除 legacy 選項
 * 2. payments 補上 invoice_random_number 欄位
 * 3. payments.reference_id NOT NULL
 * 4. orders.payment_id NOT NULL + FK → payments
 * 5. orders 移除舊發票欄位（invoice_no / invoice_date / invoice_random_number）
 * 6. orders 新增 payment_provider（為未來多供應商擴充準備）
 * 7. point_orders.payment_id NOT NULL + FK → payments
 * 8. point_orders 新增 payment_provider
 * 9. credit_card_verifications.payment_id NOT NULL + FK → payments
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. payments.environment：移除 legacy ──────────────────────
        DB::statement("ALTER TABLE `payments`
            MODIFY COLUMN `environment`
            ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox'");

        // ── 2. payments：補 invoice_random_number + reference_id NOT NULL
        Schema::table('payments', function (Blueprint $table) {
            $table->string('invoice_random_number', 10)->nullable()->after('invoice_issued_at');
            $table->unsignedBigInteger('reference_id')->nullable(false)->change();
        });

        // ── 3. orders：payment_id NOT NULL + FK + 移除舊發票欄位 + 新增 payment_provider
        // 先移除舊發票欄位（SSOT 移至 payments）
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_no', 'invoice_date', 'invoice_random_number']);
        });
        // 再改 payment_id + 加 payment_provider
        DB::statement("ALTER TABLE `orders`
            MODIFY COLUMN `payment_id` BIGINT UNSIGNED NOT NULL");
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->string('payment_provider', 20)->default('ecpay')->after('status')->index();
        });

        // ── 4. point_orders：payment_id NOT NULL + FK + 新增 payment_provider
        DB::statement("ALTER TABLE `point_orders`
            MODIFY COLUMN `payment_id` BIGINT UNSIGNED NOT NULL");
        Schema::table('point_orders', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
            $table->string('payment_provider', 20)->default('ecpay')->after('status')->index();
        });

        // ── 5. credit_card_verifications：payment_id NOT NULL + FK
        DB::statement("ALTER TABLE `credit_card_verifications`
            MODIFY COLUMN `payment_id` BIGINT UNSIGNED NOT NULL");
        Schema::table('credit_card_verifications', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Reverse order
        Schema::table('credit_card_verifications', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->unsignedBigInteger('payment_id')->nullable()->change();
        });

        Schema::table('point_orders', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn('payment_provider');
            $table->unsignedBigInteger('payment_id')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn('payment_provider');
            $table->unsignedBigInteger('payment_id')->nullable()->change();
            $table->string('invoice_no', 20)->nullable();
            $table->string('invoice_date', 30)->nullable();
            $table->string('invoice_random_number', 10)->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('invoice_random_number');
            $table->unsignedBigInteger('reference_id')->nullable()->change();
        });

        DB::statement("ALTER TABLE `payments`
            MODIFY COLUMN `environment`
            ENUM('sandbox','production','legacy') NOT NULL DEFAULT 'sandbox'");
    }
};
