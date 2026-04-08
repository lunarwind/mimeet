<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique()->default(\Illuminate\Support\Facades\DB::raw('(UUID())'));
            $table->unsignedBigInteger('user_a_id')->comment('smaller user_id');
            $table->unsignedBigInteger('user_b_id')->comment('larger user_id');
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->smallInteger('unread_count_a')->default(0);
            $table->smallInteger('unread_count_b')->default(0);
            $table->tinyInteger('deleted_by_a')->default(0);
            $table->tinyInteger('deleted_by_b')->default(0);
            $table->timestamps();

            $table->unique(['user_a_id', 'user_b_id']);
            $table->foreign('user_a_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_b_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
