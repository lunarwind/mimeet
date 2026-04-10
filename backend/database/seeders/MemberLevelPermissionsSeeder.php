<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MemberLevelPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $permissions = [
            // Level 0 - Registered
            ['level' => 0, 'permission_key' => 'browse_explore', 'is_allowed' => true, 'config' => null],
            ['level' => 0, 'permission_key' => 'basic_search', 'is_allowed' => true, 'config' => null],
            ['level' => 0, 'permission_key' => 'view_profiles', 'is_allowed' => true, 'config' => null],
            ['level' => 0, 'permission_key' => 'send_messages', 'is_allowed' => false, 'config' => null],
            ['level' => 0, 'permission_key' => 'send_date_invite', 'is_allowed' => false, 'config' => null],
            ['level' => 0, 'permission_key' => 'view_visitors', 'is_allowed' => false, 'config' => null],
            ['level' => 0, 'permission_key' => 'stealth_mode', 'is_allowed' => false, 'config' => null],
            ['level' => 0, 'permission_key' => 'read_receipts', 'is_allowed' => false, 'config' => null],
            ['level' => 0, 'permission_key' => 'daily_message_limit', 'is_allowed' => true, 'config' => json_encode(['limit' => 5])],
            ['level' => 0, 'permission_key' => 'post_content', 'is_allowed' => false, 'config' => null],
            // Level 1 - Email+Phone verified
            ['level' => 1, 'permission_key' => 'browse_explore', 'is_allowed' => true, 'config' => null],
            ['level' => 1, 'permission_key' => 'basic_search', 'is_allowed' => true, 'config' => null],
            ['level' => 1, 'permission_key' => 'view_profiles', 'is_allowed' => true, 'config' => null],
            ['level' => 1, 'permission_key' => 'send_messages', 'is_allowed' => false, 'config' => null],
            ['level' => 1, 'permission_key' => 'send_date_invite', 'is_allowed' => false, 'config' => null],
            ['level' => 1, 'permission_key' => 'view_visitors', 'is_allowed' => false, 'config' => null],
            ['level' => 1, 'permission_key' => 'stealth_mode', 'is_allowed' => false, 'config' => null],
            ['level' => 1, 'permission_key' => 'read_receipts', 'is_allowed' => false, 'config' => null],
            ['level' => 1, 'permission_key' => 'daily_message_limit', 'is_allowed' => true, 'config' => json_encode(['limit' => 10])],
            ['level' => 1, 'permission_key' => 'post_content', 'is_allowed' => false, 'config' => null],
            // Level 2 - Advanced verified
            ['level' => 2, 'permission_key' => 'browse_explore', 'is_allowed' => true, 'config' => null],
            ['level' => 2, 'permission_key' => 'basic_search', 'is_allowed' => true, 'config' => null],
            ['level' => 2, 'permission_key' => 'view_profiles', 'is_allowed' => true, 'config' => null],
            ['level' => 2, 'permission_key' => 'send_messages', 'is_allowed' => true, 'config' => null],
            ['level' => 2, 'permission_key' => 'send_date_invite', 'is_allowed' => true, 'config' => null],
            ['level' => 2, 'permission_key' => 'view_visitors', 'is_allowed' => false, 'config' => null],
            ['level' => 2, 'permission_key' => 'stealth_mode', 'is_allowed' => false, 'config' => null],
            ['level' => 2, 'permission_key' => 'read_receipts', 'is_allowed' => false, 'config' => null],
            ['level' => 2, 'permission_key' => 'daily_message_limit', 'is_allowed' => true, 'config' => json_encode(['limit' => 30])],
            ['level' => 2, 'permission_key' => 'post_content', 'is_allowed' => true, 'config' => null],
            // Level 3 - Paid
            ['level' => 3, 'permission_key' => 'browse_explore', 'is_allowed' => true, 'config' => null],
            ['level' => 3, 'permission_key' => 'basic_search', 'is_allowed' => true, 'config' => null],
            ['level' => 3, 'permission_key' => 'view_profiles', 'is_allowed' => true, 'config' => null],
            ['level' => 3, 'permission_key' => 'send_messages', 'is_allowed' => true, 'config' => null],
            ['level' => 3, 'permission_key' => 'send_date_invite', 'is_allowed' => true, 'config' => null],
            ['level' => 3, 'permission_key' => 'view_visitors', 'is_allowed' => true, 'config' => null],
            ['level' => 3, 'permission_key' => 'stealth_mode', 'is_allowed' => true, 'config' => null],
            ['level' => 3, 'permission_key' => 'read_receipts', 'is_allowed' => true, 'config' => null],
            ['level' => 3, 'permission_key' => 'daily_message_limit', 'is_allowed' => true, 'config' => json_encode(['limit' => 999])],
            ['level' => 3, 'permission_key' => 'post_content', 'is_allowed' => true, 'config' => null],
            // Level 1.5
            ['level' => 1.5, 'permission_key' => 'browse_explore', 'is_allowed' => true, 'config' => null],
            ['level' => 1.5, 'permission_key' => 'basic_search', 'is_allowed' => true, 'config' => null],
            ['level' => 1.5, 'permission_key' => 'view_profiles', 'is_allowed' => true, 'config' => null],
            ['level' => 1.5, 'permission_key' => 'send_messages', 'is_allowed' => true, 'config' => null],
            ['level' => 1.5, 'permission_key' => 'send_date_invite', 'is_allowed' => false, 'config' => null],
            ['level' => 1.5, 'permission_key' => 'view_visitors', 'is_allowed' => false, 'config' => null],
            ['level' => 1.5, 'permission_key' => 'stealth_mode', 'is_allowed' => false, 'config' => null],
            ['level' => 1.5, 'permission_key' => 'read_receipts', 'is_allowed' => false, 'config' => null],
            ['level' => 1.5, 'permission_key' => 'daily_message_limit', 'is_allowed' => true, 'config' => json_encode(['limit' => 20])],
            ['level' => 1.5, 'permission_key' => 'post_content', 'is_allowed' => false, 'config' => null],
        ];

        foreach ($permissions as $p) {
            DB::table('member_level_permissions')->updateOrInsert(
                ['level' => $p['level'], 'permission_key' => $p['permission_key']],
                array_merge($p, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
