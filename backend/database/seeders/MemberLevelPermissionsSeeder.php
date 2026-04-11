<?php
<<<<<<< HEAD
=======

>>>>>>> develop
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MemberLevelPermissionsSeeder extends Seeder
{
    public function run(): void
    {
<<<<<<< HEAD
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
=======
        // PRD-001 v1.2 §3.2 — 5 等級 × 10 功能預設值
        $permissions = [
            // Lv0 — 註冊會員
            [0.0, 'browse', 1, null],
            [0.0, 'basic_search', 1, null],
            [0.0, 'advanced_search', 0, null],
            [0.0, 'daily_message_limit', 1, '5'],
            [0.0, 'view_full_profile', 0, null],
            [0.0, 'post_moment', 0, '0'],
            [0.0, 'read_receipt', 0, null],
            [0.0, 'qr_date', 0, null],
            [0.0, 'vip_invisible', 0, null],
            [0.0, 'broadcast', 0, null],

            // Lv1 — 驗證會員
            [1.0, 'browse', 1, null],
            [1.0, 'basic_search', 1, null],
            [1.0, 'advanced_search', 1, null],
            [1.0, 'daily_message_limit', 1, '30'],
            [1.0, 'view_full_profile', 0, null],
            [1.0, 'post_moment', 0, '0'],
            [1.0, 'read_receipt', 0, null],
            [1.0, 'qr_date', 0, null],
            [1.0, 'vip_invisible', 0, null],
            [1.0, 'broadcast', 0, null],

            // Lv1.5 — 驗證女會員
            [1.5, 'browse', 1, null],
            [1.5, 'basic_search', 1, null],
            [1.5, 'advanced_search', 1, null],
            [1.5, 'daily_message_limit', 1, '100'],
            [1.5, 'view_full_profile', 1, null],
            [1.5, 'post_moment', 1, '3'],
            [1.5, 'read_receipt', 0, null],
            [1.5, 'qr_date', 0, null],
            [1.5, 'vip_invisible', 0, null],
            [1.5, 'broadcast', 0, null],

            // Lv2 — 進階驗證男會員
            [2.0, 'browse', 1, null],
            [2.0, 'basic_search', 1, null],
            [2.0, 'advanced_search', 1, null],
            [2.0, 'daily_message_limit', 1, '30'],
            [2.0, 'view_full_profile', 1, null],
            [2.0, 'post_moment', 1, '1'],
            [2.0, 'read_receipt', 0, null],
            [2.0, 'qr_date', 0, null],
            [2.0, 'vip_invisible', 0, null],
            [2.0, 'broadcast', 0, null],

            // Lv3 — 付費會員
            [3.0, 'browse', 1, null],
            [3.0, 'basic_search', 1, null],
            [3.0, 'advanced_search', 1, null],
            [3.0, 'daily_message_limit', 1, '0'], // 0 = 無限
            [3.0, 'view_full_profile', 1, null],
            [3.0, 'post_moment', 1, '3'],
            [3.0, 'read_receipt', 1, null],
            [3.0, 'qr_date', 1, null],
            [3.0, 'vip_invisible', 1, null],
            [3.0, 'broadcast', 1, null],
        ];

        foreach ($permissions as [$level, $key, $enabled, $value]) {
            DB::table('member_level_permissions')->updateOrInsert(
                ['level' => $level, 'feature_key' => $key],
                ['enabled' => $enabled, 'value' => $value],
>>>>>>> develop
            );
        }
    }
}
