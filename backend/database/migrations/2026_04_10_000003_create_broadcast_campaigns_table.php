<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('broadcast_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->enum('delivery_mode', ['notification', 'dm', 'both'])->default('notification');
            $table->enum('target_gender', ['male', 'female', 'all'])->default('all');
            $table->string('target_level')->default('all');
            $table->integer('target_credit_min')->default(0);
            $table->integer('target_credit_max')->default(100);
            $table->integer('target_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->enum('status', ['draft', 'sending', 'completed', 'failed'])->default('draft');
            $table->unsignedBigInteger('created_by');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('broadcast_campaigns'); }
};
