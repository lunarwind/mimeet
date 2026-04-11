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

        // Admin account in admin_users table (separate from frontend users)
        \App\Models\AdminUser::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@mimeet.tw')],
            [
                'password' => bcrypt(env('ADMIN_PASSWORD', 'ChangeMe@2026')),
                'name' => env('ADMIN_NAME', 'Super Admin'),
                'role' => 'super_admin',
                'is_active' => true,
            ],
        );

        // Test data (local only — requires fakerphp/faker dev dependency)
        if (app()->environment('local')) {
            $this->call(TestDataSeeder::class);
        }
    }
}
