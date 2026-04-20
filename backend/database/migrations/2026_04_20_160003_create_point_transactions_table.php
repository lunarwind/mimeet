<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['purchase', 'consume', 'refund', 'admin_gift', 'admin_deduct']);
            $table->integer('amount')->comment('正=入帳，負=消費');
            $table->unsignedInteger('balance_after')->comment('交易後餘額');
            $table->string('feature', 30)->nullable()->comment('stealth/reverse_msg/super_like/broadcast');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('關聯訂單/訊息 ID');
            $table->string('description', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};
