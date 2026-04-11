<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 11 permission keys (API-002 §10)
        $permissions = [
            ['key' => 'members.view', 'name' => '查看會員', 'module' => 'members'],
            ['key' => 'members.edit', 'name' => '編輯會員（調整分數/停權/驗證）', 'module' => 'members'],
            ['key' => 'members.delete', 'name' => '刪除帳號（super_admin only）', 'module' => 'members'],
            ['key' => 'reports.view', 'name' => '查看回報/檢舉', 'module' => 'reports'],
            ['key' => 'reports.process', 'name' => '處理回報/檢舉（含申訴審核）', 'module' => 'reports'],
            ['key' => 'chat.view', 'name' => '查看聊天記錄', 'module' => 'chat'],
            ['key' => 'payments.view', 'name' => '查看支付記錄', 'module' => 'payments'],
            ['key' => 'seo.manage', 'name' => 'SEO Meta + 廣告追蹤連結', 'module' => 'seo'],
            ['key' => 'settings.pricing', 'name' => '訂閱方案管理', 'module' => 'settings'],
            ['key' => 'settings.roles', 'name' => '角色權限管理（super_admin only）', 'module' => 'settings'],
            ['key' => 'broadcasts.manage', 'name' => '廣播訊息管理', 'module' => 'broadcasts'],
        ];

        foreach ($permissions as $perm) {
            DB::table('admin_permissions')->updateOrInsert(
                ['key' => $perm['key']],
                $perm,
            );
        }

        // Default role-permission matrix
        $rolePermissions = [
            'super_admin' => ['*'], // wildcard — all permissions
            'admin' => [
                'members.view', 'members.edit',
                'reports.view', 'reports.process',
                'chat.view', 'payments.view',
                'seo.manage', 'settings.pricing',
                'broadcasts.manage',
            ],
            'cs' => [
                'members.view',
                'reports.view', 'reports.process',
            ],
        ];

        foreach ($rolePermissions as $role => $keys) {
            foreach ($keys as $key) {
                DB::table('admin_role_permissions')->updateOrInsert(
                    ['role' => $role, 'permission_key' => $key],
                    [],
                );
            }
        }
    }
}
