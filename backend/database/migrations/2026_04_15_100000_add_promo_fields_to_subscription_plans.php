<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('original_price')->nullable()->after('price');
            $table->string('promo_type', 20)->default('none')->after('original_price');
            $table->decimal('promo_value', 8, 2)->nullable()->after('promo_type');
            $table->timestamp('promo_start_at')->nullable()->after('promo_value');
            $table->timestamp('promo_end_at')->nullable()->after('promo_start_at');
            $table->string('promo_note', 100)->nullable()->after('promo_end_at');
        });

        // Set original_price = price for existing rows
        DB::statement('UPDATE subscription_plans SET original_price = price WHERE original_price IS NULL');
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['original_price', 'promo_type', 'promo_value', 'promo_start_at', 'promo_end_at', 'promo_note']);
        });
    }
};
