<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $permissions = [
            ['key' => 'members.view', 'name' => '查看會員', 'group' => 'members'],
            ['key' => 'members.edit', 'name' => '編輯會員', 'group' => 'members'],
            ['key' => 'members.suspend', 'name' => '停權/解停', 'group' => 'members'],
            ['key' => 'members.adjust_score', 'name' => '調整分數', 'group' => 'members'],
            ['key' => 'members.delete', 'name' => '刪除會員', 'group' => 'members'],
            ['key' => 'reports.view', 'name' => '查看回報', 'group' => 'reports'],
            ['key' => 'reports.process', 'name' => '處理回報', 'group' => 'reports'],
            ['key' => 'chat.view', 'name' => '查看聊天記錄', 'group' => 'chat'],
            ['key' => 'chat.export', 'name' => '匯出聊天記錄', 'group' => 'chat'],
            ['key' => 'settings.system', 'name' => '系統設定', 'group' => 'settings'],
            ['key' => 'settings.roles', 'name' => '角色管理', 'group' => 'settings'],
        ];

        foreach ($permissions as $p) {
            DB::table('admin_permissions')->updateOrInsert(
                ['key' => $p['key']],
                array_merge($p, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        $roleMap = [
            'super_admin' => ['members.view','members.edit','members.suspend','members.adjust_score','members.delete','reports.view','reports.process','chat.view','chat.export','settings.system','settings.roles'],
            'admin' => ['members.view','members.edit','members.suspend','members.adjust_score','reports.view','reports.process','chat.view','settings.system'],
            'cs' => ['members.view','reports.view','reports.process'],
        ];

        foreach ($roleMap as $role => $keys) {
            foreach ($keys as $key) {
                DB::table('admin_role_permissions')->updateOrInsert(
                    ['role' => $role, 'permission_key' => $key],
                    ['is_allowed' => true, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }
}
