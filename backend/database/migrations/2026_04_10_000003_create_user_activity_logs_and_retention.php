<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. User activity logs table
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('action', 100)->comment('e.g. login, profile_view, message_sent, photo_upload');
            $table->json('metadata')->nullable()->comment('Action-specific data');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // 2. Add soft delete to messages (if not already using flag-based deletion)
        if (!Schema::hasColumn('messages', 'deleted_at')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
            });
        }

        // 3. Seed data retention setting
        DB::table('system_settings')->updateOrInsert(
            ['key_name' => 'data_retention_days'],
            [
                'value' => '180',
                'value_type' => 'integer',
                'description' => '資料保留天數（超過此天數的活動日誌與已刪除訊息將被永久清除）',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');

        if (Schema::hasColumn('messages', 'deleted_at')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('deleted_at');
            });
        }

        DB::table('system_settings')->where('key_name', 'data_retention_days')->delete();
    }
};
