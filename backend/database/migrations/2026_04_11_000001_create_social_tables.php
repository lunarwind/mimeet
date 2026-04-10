<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_follows', function (Blueprint $table) {
            $table->unsignedBigInteger('follower_id');
            $table->unsignedBigInteger('following_id');
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['follower_id', 'following_id']);
            $table->index('following_id');
            $table->foreign('follower_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('following_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->unsignedBigInteger('blocker_id');
            $table->unsignedBigInteger('blocked_id');
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['blocker_id', 'blocked_id']);
            $table->index('blocked_id');
            $table->foreign('blocker_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('blocked_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('user_profile_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visitor_id');
            $table->unsignedBigInteger('visited_user_id');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['visited_user_id', 'created_at']);
            $table->index('visitor_id');
            $table->foreign('visitor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('visited_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profile_visits');
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('user_follows');
    }
};
