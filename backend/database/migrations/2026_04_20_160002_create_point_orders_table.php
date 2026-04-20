<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_orders', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('package_id')->constrained('point_packages');
            $table->unsignedInteger('points')->comment('基本 + 贈送，實際入帳點數');
            $table->unsignedInteger('amount')->comment('NT$ 金額');
            $table->string('payment_method', 20)->default('credit_card');
            $table->string('trade_no', 50)->unique()->comment('內部訂單編號（PTS_ 前綴）');
            $table->string('gateway_trade_no', 50)->nullable()->comment('綠界 TradeNo');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_orders');
    }
};
