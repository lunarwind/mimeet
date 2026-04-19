<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('is_muted_by_a')->default(false)->after('unread_count_b')
                ->comment('user_a 是否靜音此對話（F22 Part A）');
            $table->boolean('is_muted_by_b')->default(false)->after('is_muted_by_a')
                ->comment('user_b 是否靜音此對話（F22 Part A）');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['is_muted_by_a', 'is_muted_by_b']);
        });
    }
};
