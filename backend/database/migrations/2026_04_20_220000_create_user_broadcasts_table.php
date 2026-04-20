<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('content')->comment('廣播內容，最多 200 字');
            $table->json('filters')->nullable()->comment('篩選條件');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('points_spent')->default(0);
            $table->enum('status', ['preview', 'sending', 'completed', 'failed'])->default('preview');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['sender_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_broadcasts');
    }
};
