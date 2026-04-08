<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique()->default(\Illuminate\Support\Facades\DB::raw('(UUID())'));
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('sender_id');
            $table->enum('type', ['text', 'image', 'qr_invite'])->default('text');
            $table->text('content')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->json('meta')->nullable();
            $table->tinyInteger('is_read')->default(0);
            $table->timestamp('read_at')->nullable();
            $table->tinyInteger('is_recalled')->default(0);
            $table->timestamp('recalled_at')->nullable();
            $table->tinyInteger('is_deleted_by_sender')->default(0);
            $table->tinyInteger('is_deleted_by_receiver')->default(0);
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['conversation_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
