<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // MySQL-specific column modifications — SQLite doesn't need them
        // (SQLite has no ENUM and columns are already flexible)
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // P1-A: Add system_issue to type ENUM
        DB::statement("
            ALTER TABLE reports
            MODIFY COLUMN type
            ENUM('fake_photo','harassment','spam','scam','inappropriate','other','appeal','system_issue')
            NOT NULL
        ");

        // P1-B: Make reported_user_id nullable (system issues have no target user)
        Schema::table('reports', function (Blueprint $table) {
            $table->unsignedBigInteger('reported_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE reports
            MODIFY COLUMN type
            ENUM('fake_photo','harassment','spam','scam','inappropriate','other','appeal')
            NOT NULL
        ");

        Schema::table('reports', function (Blueprint $table) {
            $table->unsignedBigInteger('reported_user_id')->nullable(false)->change();
        });
    }
};
