<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('order_no', 40)->unique();
            $table->unsignedInteger('amount')->default(100);
            $table->enum('status', ['pending', 'paid', 'refunded', 'failed', 'refund_failed'])->default('pending');
            $table->string('gateway', 20)->default('ecpay');
            $table->string('gateway_trade_no', 100)->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refund_initiated_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_trade_no', 100)->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('raw_callback')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_verifications');
    }
};
