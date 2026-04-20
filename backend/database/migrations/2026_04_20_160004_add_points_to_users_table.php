<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('points_balance')->default(0)->after('credit_score')
                ->comment('F40 目前點數餘額');
            $table->timestamp('stealth_until')->nullable()->after('points_balance')
                ->comment('F42 隱身模式到期時間');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['points_balance', 'stealth_until']);
        });
    }
};
