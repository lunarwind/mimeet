<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite 把 enum 翻譯成 VARCHAR + CHECK 約束；後續加 ENUM 值的 migration
        // 在 SQLite 全部 skip（MySQL-only 語法），CHECK 跟不上會擋住新值寫入。
        // 測試環境改用 string，丟掉 CHECK，讓 RefreshDatabase 後仍能插入任何 type。
        // Production（MySQL）行為不變：仍是嚴格 ENUM。
        $isMysql = DB::getDriverName() === 'mysql';

        Schema::create('notifications', function (Blueprint $table) use ($isMysql) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            if ($isMysql) {
                $table->enum('type', [
                    'new_message', 'new_favorite', 'profile_visited', 'ticket_replied',
                    'date_invitation', 'date_verified', 'subscription_expiring',
                    'subscription_activated', 'credit_score_changed', 'system',
                ]);
            } else {
                $table->string('type', 50);
            }
            $table->string('title', 100);
            $table->string('body', 300)->nullable();
            $table->json('data')->nullable();
            $table->tinyInteger('is_read')->default(0);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'is_read', 'created_at']);
        });

        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token', 500);
            $table->string('device_id', 200)->nullable();
            $table->enum('platform', ['android', 'ios', 'web'])->default('web');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fcm_tokens');
        Schema::dropIfExists('notifications');
    }
};
