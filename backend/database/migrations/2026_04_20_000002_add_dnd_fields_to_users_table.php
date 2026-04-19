<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('dnd_enabled')->default(false)->after('privacy_settings')
                ->comment('F22 Part B：全域免打擾開關');
            $table->time('dnd_start')->nullable()->after('dnd_enabled')
                ->comment('免打擾開始時間，如 22:00');
            $table->time('dnd_end')->nullable()->after('dnd_start')
                ->comment('免打擾結束時間，如 08:00；start > end 代表跨午夜');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dnd_enabled', 'dnd_start', 'dnd_end']);
        });
    }
};
