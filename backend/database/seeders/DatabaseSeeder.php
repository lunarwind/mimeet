<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // System data (all environments)
        $this->call(SubscriptionPlanSeeder::class);
        $this->call(MemberLevelPermissionsSeeder::class);
        $this->call(AdminPermissionsSeeder::class);
        $this->call(SystemSettingsSeeder::class);

        // Admin account in admin_users table (separate from frontend users)
        \App\Models\AdminUser::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'chuck@lunarwind.org')],
            [
                'password' => bcrypt(env('ADMIN_PASSWORD', 'ChangeMe@2026')),
                'name' => env('ADMIN_NAME', 'Super Admin'),
                'role' => 'super_admin',
                'is_active' => true,
            ],
        );

        // Test data seeders 已於 2026-05-09 PR-Dataset-Cleanup 全部移除。
        // 若 local 開發需要假資料,請改用 individual factory 或自行新增 dev-only seeder。
    }
}
