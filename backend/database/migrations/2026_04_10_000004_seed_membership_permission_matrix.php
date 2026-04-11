<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // JSON-based permission matrix in system_settings
        // Complementary to member_level_permissions table — provides a simple
        // "feature → allowed_levels[]" mapping for quick runtime checks.
        $matrix = [
            'browse' => [0, 1, 1.5, 2, 3],
            'basic_search' => [0, 1, 1.5, 2, 3],
            'advanced_search' => [1, 1.5, 2, 3],
            'daily_message_limit' => [0, 1, 1.5, 2, 3], // all levels, but limits differ
            'view_full_profile' => [1.5, 2, 3],
            'post_moment' => [1.5, 2, 3],
            'read_receipt' => [3],
            'qr_date' => [3],
            'vip_invisible' => [3],
            'broadcast' => [3],
        ];

        DB::table('system_settings')->updateOrInsert(
            ['key_name' => 'membership_permission_matrix'],
            [
                'value' => json_encode($matrix, JSON_UNESCAPED_UNICODE),
                'value_type' => 'json',
                'description' => '會員等級功能權限矩陣（feature_key → 允許的等級陣列）',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key_name', 'membership_permission_matrix')->delete();
    }
};
