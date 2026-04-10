<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_no', 20)->nullable()->after('ecpay_trade_no');
            $table->string('ecpay_payment_type', 50)->nullable()->after('invoice_no');
            $table->timestamp('payment_date')->nullable()->after('ecpay_payment_type');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_no', 'ecpay_payment_type', 'payment_date']);
        });
    }
};
