<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY membership_level DECIMAL(3,1) NOT NULL DEFAULT 0 COMMENT '0/1/1.5/2/3，支援 Lv1.5 驗證女會員'");
        }
        // SQLite: column is already numeric, DECIMAL(3,1) not supported natively
        // The column will accept decimal values as-is
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY membership_level TINYINT NOT NULL DEFAULT 0');
        }
    }
};
