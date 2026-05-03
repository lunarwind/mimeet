<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite 不支援 ALTER COLUMN MODIFY，schema 由原始 create migration 決定
        }
        DB::statement('ALTER TABLE date_invitations MODIFY latitude DECIMAL(10,8) NULL');
        DB::statement('ALTER TABLE date_invitations MODIFY longitude DECIMAL(11,8) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE date_invitations MODIFY latitude DECIMAL(10,8) NOT NULL');
        DB::statement('ALTER TABLE date_invitations MODIFY longitude DECIMAL(11,8) NOT NULL');
    }
};
