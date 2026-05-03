<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY membership_level DECIMAL(3,1) NOT NULL DEFAULT 0 COMMENT '0/1/1.5/2/3，支援 Lv1.5 驗證女會員'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY membership_level TINYINT NOT NULL DEFAULT 0');
    }
};
