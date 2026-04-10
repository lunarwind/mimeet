<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('carrier_type', 10)->nullable()->after('payment_date');
            $table->string('carrier_num', 64)->nullable()->after('carrier_type');
            $table->string('love_code', 10)->nullable()->after('carrier_num');
            $table->string('ecpay_invoice_no', 20)->nullable()->after('love_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['carrier_type', 'carrier_num', 'love_code', 'ecpay_invoice_no']);
        });
    }
};
