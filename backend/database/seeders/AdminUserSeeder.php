<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $admins = [
            ['name' => 'Super Admin', 'email' => env('SUPER_ADMIN_EMAIL', 'chuck@lunarwind.org'), 'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'ChangeMe@2026')), 'role' => 'super_admin'],
            ['name' => 'Admin', 'email' => env('ADMIN_EMAIL', 'admin@mimeet.tw'), 'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe@2026')), 'role' => 'admin'],
            ['name' => 'CS', 'email' => env('CS_EMAIL', 'cs@mimeet.tw'), 'password' => Hash::make(env('CS_PASSWORD', 'ChangeMe@2026')), 'role' => 'cs'],
        ];
        foreach ($admins as $admin) {
            DB::table('admin_users')->updateOrInsert(
                ['email' => $admin['email']],
                array_merge($admin, ['is_active' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
