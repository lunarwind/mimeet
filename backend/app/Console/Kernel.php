<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('gdpr:process-deletions')->dailyAt('03:00');
        $schedule->command('payments:auto-refund-verifications')->dailyAt('03:00');
        $schedule->command('payments:reconcile-ecpay')->everyFifteenMinutes();

        $schedule->command('subscriptions:expire')
            ->dailyAt('00:05')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/subscriptions-expire.log'));

        $schedule->command('subscriptions:notify-expiring')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/subscriptions-notify-expiring.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
