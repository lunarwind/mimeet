<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SubscriptionPlanSeeder::class,
            AdminUserSeeder::class,
            AdminPermissionsSeeder::class,
            MemberLevelPermissionsSeeder::class,
            SystemSettingsSeeder::class,
        ]);

        // Create test users in local environment
        if (app()->environment('local')) {
            \App\Models\User::factory(10)->create();
        }
    }
}
