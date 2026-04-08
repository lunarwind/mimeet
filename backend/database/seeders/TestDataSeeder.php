<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TestUsersSeeder::class,
            TestConversationsSeeder::class,
            TestDateInvitationsSeeder::class,
            TestSubscriptionsSeeder::class,
            TestReportsSeeder::class,
            TestCreditScoreHistoriesSeeder::class,
            TestNotificationsSeeder::class,
        ]);
    }
}
