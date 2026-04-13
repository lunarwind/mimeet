<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ResetToCleanState extends Command
{
    protected $signature = 'mimeet:reset-clean {--force : Skip confirmation}';
    protected $description = 'Clear all business data, keep system settings and admin account';

    // Tables to truncate (child tables first for FK order)
    private const TRUNCATE_TABLES = [
        'report_followups',
        'report_images',
        'reports',
        'notifications',
        'credit_score_histories',
        'fcm_tokens',
        'messages',
        'conversations',
        'date_invitations',
        'subscriptions',
        'orders',
        'personal_access_tokens',
        'password_reset_tokens',
    ];

    // Tables to preserve completely
    // subscription_plans, system_settings, migrations

    public function handle(): int
    {
        if (!$this->option('force')) {
            $this->warn('⚠️  This will permanently delete all business data (users, chats, orders, etc.)');
            $this->warn('   Preserved: admin account (id=1), subscription_plans, system_settings');
            if (!$this->confirm('Continue?', false)) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
        }

        $this->info('Clearing database...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (self::TRUNCATE_TABLES as $table) {
                if ($this->tableExists($table)) {
                    DB::table($table)->truncate();
                    $this->line("  ✓ {$table}");
                }
            }

            // Users: keep id=1 (admin), delete rest
            $deleted = DB::table('users')->where('id', '!=', 1)->delete();
            $this->line("  ✓ users (kept id=1, deleted {$deleted})");
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Clear cache
        try {
            Artisan::call('cache:clear');
            $this->line('  ✓ cache cleared');
        } catch (\Exception) {
            // Cache clear may fail if Redis not available
        }

        // Reset auto-increment
        DB::statement('ALTER TABLE users AUTO_INCREMENT = 2');
        foreach (self::TRUNCATE_TABLES as $table) {
            if ($this->tableExists($table)) {
                try {
                    DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                } catch (\Exception) {
                    // Some tables may not have auto-increment
                }
            }
        }

        $this->newLine();
        $this->info('✅ Database reset to clean state');
        $this->info('   Admin: ' . env('ADMIN_EMAIL', 'chuck@lunarwind.org'));

        return self::SUCCESS;
    }

    private function tableExists(string $table): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable($table);
    }
}
