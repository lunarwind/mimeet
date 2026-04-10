<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY membership_level DECIMAL(3,1) DEFAULT 0');
    }
    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY membership_level TINYINT DEFAULT 0');
    }
};
