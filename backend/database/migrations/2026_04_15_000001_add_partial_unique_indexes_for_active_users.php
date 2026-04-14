<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 只對非 deleted 用戶強制 nickname 唯一
        // SQLite（測試環境）：支援 partial index
        // MySQL：Application layer validation（Rule::unique with where）已足夠
        if (config('database.default') === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX users_nickname_active_unique ON users (nickname) WHERE status != \'deleted\'');
            DB::statement('CREATE UNIQUE INDEX users_phone_active_unique ON users (phone) WHERE phone IS NOT NULL AND status != \'deleted\'');
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS users_nickname_active_unique');
            DB::statement('DROP INDEX IF EXISTS users_phone_active_unique');
        }
    }
};
