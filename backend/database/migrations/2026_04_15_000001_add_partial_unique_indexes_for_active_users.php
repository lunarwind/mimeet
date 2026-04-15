<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL 不支援 partial unique index（WHERE 條件的唯一索引）
        // 唯一性驗證由 Application Layer（AuthController）的 DB query 負責
        // 此 migration 保留為文件紀錄，不執行任何 DDL
    }

    public function down(): void
    {
        // Nothing to rollback
    }
};
