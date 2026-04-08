<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SubscriptionPlanSeeder::class);

        // Create admin user
        \App\Models\User::firstOrCreate(
            ['email' => 'admin@mimeet.tw'],
            [
                'password' => bcrypt('password'),
                'nickname' => '管理員',
                'gender' => 'male',
                'membership_level' => 3,
                'credit_score' => 100,
                'status' => 'active',
                'email_verified' => true,
            ],
        );

        // Test users are created via factory in tests only — not in seeder
        // To create test data manually: php artisan tinker → User::factory(10)->create()
    }
}
