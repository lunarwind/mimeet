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
            TestSubscriptionsSeeder::class,         // 含 payments SSOT
            TestPointOrdersSeeder::class,           // point_orders + payments
            TestPendingVerificationsSeeder::class,  // pending 女性照片驗證
            TestReportsSeeder::class,
            TestCreditScoreHistoriesSeeder::class,
            TestNotificationsSeeder::class,
        ]);
    }
}
