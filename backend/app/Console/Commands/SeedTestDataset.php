<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SeedTestDataset extends Command
{
    protected $signature = 'mimeet:seed-test
                            {--fresh : Reset clean first, then seed}
                            {--force : Skip confirmation}';
    protected $description = 'Import test dataset (30 users + conversations + dates + etc.)';

    public function handle(): int
    {
        if (!$this->option('force')) {
            $msg = $this->option('fresh')
                ? 'This will CLEAR all data then import test dataset'
                : 'This will import test data on top of existing data';
            $this->warn($msg);
            if (!$this->confirm('Continue?', false)) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
        }

        if ($this->option('fresh')) {
            $this->info('Clearing database first...');
            Artisan::call('mimeet:reset-clean', ['--force' => true]);
            $this->line(Artisan::output());
        }

        $this->info('Importing test dataset...');
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\TestDataSeeder', '--force' => true]);

        $this->newLine();
        $this->info('✅ Test dataset imported');
        $this->table(['Table', 'Count'], $this->getStats());

        return self::SUCCESS;
    }

    private function getStats(): array
    {
        return [
            ['users (excl. admin)', \App\Models\User::where('id', '!=', 1)->count()],
            ['conversations', \App\Models\Conversation::count()],
            ['messages', \App\Models\Message::count()],
            ['date_invitations', \App\Models\DateInvitation::count()],
            ['orders', \App\Models\Order::count()],
            ['subscriptions', \App\Models\Subscription::count()],
            ['reports', \App\Models\Report::count()],
            ['credit_score_histories', \App\Models\CreditScoreHistory::count()],
            ['notifications', \App\Models\Notification::count()],
        ];
    }
}
