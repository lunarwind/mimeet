<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ecpay_payment_date', 30)->nullable()->after('ecpay_trade_no');
            $table->string('ecpay_payment_type', 50)->nullable()->after('ecpay_payment_date');
            $table->string('ecpay_payment_type_charge_fee', 20)->nullable()->after('ecpay_payment_type');
            $table->string('invoice_no', 20)->nullable()->after('ecpay_payment_type_charge_fee');
            $table->string('invoice_date', 30)->nullable()->after('invoice_no');
            $table->string('invoice_random_number', 10)->nullable()->after('invoice_date');
        });

        // Seed ECPay payment & invoice settings into system_settings
        $settings = [
            ['key_name' => 'ecpay.mode', 'value' => 'sandbox', 'value_type' => 'string', 'description' => '綠界模式：sandbox / production'],
            // Payment credentials (sandbox defaults)
            ['key_name' => 'ecpay.payment.merchant_id', 'value' => '3002607', 'value_type' => 'string', 'description' => '綠界金流 MerchantID（測試）'],
            ['key_name' => 'ecpay.payment.hash_key', 'value' => 'pwFHCqoQZGmho4w6', 'value_type' => 'secret', 'description' => '綠界金流 HashKey'],
            ['key_name' => 'ecpay.payment.hash_iv', 'value' => 'EkRm7iFT261dpevs', 'value_type' => 'secret', 'description' => '綠界金流 HashIV'],
            // Invoice credentials (sandbox defaults)
            ['key_name' => 'ecpay.invoice.merchant_id', 'value' => '2000132', 'value_type' => 'string', 'description' => '綠界發票 MerchantID（測試）'],
            ['key_name' => 'ecpay.invoice.hash_key', 'value' => 'ejCk326UnaZWKisg', 'value_type' => 'secret', 'description' => '綠界發票 HashKey'],
            ['key_name' => 'ecpay.invoice.hash_iv', 'value' => 'q9jcZX8Ib9LM8wYk', 'value_type' => 'secret', 'description' => '綠界發票 HashIV'],
            // Invoice defaults
            ['key_name' => 'ecpay.invoice.enabled', 'value' => '0', 'value_type' => 'boolean', 'description' => '是否開立電子發票'],
            ['key_name' => 'ecpay.invoice.donation_love_code', 'value' => '168001', 'value_type' => 'string', 'description' => '預設捐贈碼（愛心碼）'],
        ];

        foreach ($settings as $s) {
            DB::table('system_settings')->updateOrInsert(
                ['key_name' => $s['key_name']],
                array_merge($s, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'ecpay_payment_date', 'ecpay_payment_type', 'ecpay_payment_type_charge_fee',
                'invoice_no', 'invoice_date', 'invoice_random_number',
            ]);
        });

        DB::table('system_settings')->where('key_name', 'like', 'ecpay.%')->delete();
    }
};
