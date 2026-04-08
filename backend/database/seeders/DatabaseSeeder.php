<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // System data (all environments)
        $this->call(SubscriptionPlanSeeder::class);

        // Admin account (from .env or defaults)
        \App\Models\User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@mimeet.tw')],
            [
                'password' => bcrypt(env('ADMIN_PASSWORD', 'ChangeMe@2026')),
                'nickname' => env('ADMIN_NAME', 'Super Admin'),
                'gender' => 'male',
                'membership_level' => 3,
                'credit_score' => 100,
                'status' => 'active',
                'email_verified' => true,
            ],
        );

        // Test data (local/staging only)
        if (app()->environment(['local', 'staging'])) {
            $this->call(TestDataSeeder::class);
        }
    }
}
