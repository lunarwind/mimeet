<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('gdpr:process-deletions')->dailyAt('03:00');
        $schedule->command('credit-card:auto-refund')->dailyAt('03:00');       // 舊流程（credit_card_verifications 表）
        $schedule->command('payments:auto-refund-verifications')->dailyAt('03:00'); // 新流程（payments 主表）
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
