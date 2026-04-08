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

        // Create test users
        if (app()->environment('local')) {
            \App\Models\User::factory(10)->create();
        }
    }
}
