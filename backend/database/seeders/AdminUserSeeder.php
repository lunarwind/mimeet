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
            ['name' => 'Super Admin', 'email' => 'super@mimeet.tw', 'password' => Hash::make('mimeet2024'), 'role' => 'super_admin'],
            ['name' => 'Admin', 'email' => 'admin@mimeet.tw', 'password' => Hash::make('password'), 'role' => 'admin'],
            ['name' => 'CS', 'email' => 'cs@mimeet.tw', 'password' => Hash::make('password'), 'role' => 'cs'],
        ];
        foreach ($admins as $admin) {
            DB::table('admin_users')->updateOrInsert(
                ['email' => $admin['email']],
                array_merge($admin, ['is_active' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
