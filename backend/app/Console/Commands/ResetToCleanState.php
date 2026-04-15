<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetToCleanState extends Command
{
    protected $signature = 'mimeet:reset
                            {--with-test-data : Also import 20 test users after reset}
                            {--force : Skip confirmation (for CI/CD)}';
    protected $description = 'Reset database to initial state (preserves id=1 system user + system config)';

    private const TRUNCATE_TABLES = [
        'user_profile_visits',
        'user_follows',
        'user_blocks',
        'user_activity_logs',
        'user_verifications',
        'broadcast_campaigns',
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

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('⚠️  This will permanently delete all business data (users, chats, orders, etc.)');
            $this->info('   Preserved: users.id=1 (系統帳號), admin_users, subscription_plans, system_settings');
            if (! $this->confirm('Continue?', false)) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
        }

        $this->info('Resetting database...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (self::TRUNCATE_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                    $this->line("  ✓ {$table}");
                }
            }

            // Users: keep id=1 (system), delete rest
            $deleted = DB::table('users')->where('id', '!=', 1)->delete();
            $this->line("  ✓ users (kept id=1, deleted {$deleted})");

            // Ensure id=1 exists (re-create if somehow missing)
            if (! DB::table('users')->where('id', 1)->exists()) {
                DB::table('users')->insert([
                    'id'               => 1,
                    'email'            => 'system@mimeet.tw',
                    'password'         => bcrypt('SYSTEM_ACCOUNT_DO_NOT_LOGIN'),
                    'nickname'         => 'MiMeet 官方',
                    'gender'           => 'male',
                    'email_verified'   => true,
                    'membership_level' => 3,
                    'credit_score'     => 100,
                    'status'           => 'active',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                $this->line('  ✓ re-created id=1 (was missing)');
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Reset auto-increment
        DB::statement('ALTER TABLE users AUTO_INCREMENT = 2');
        foreach (self::TRUNCATE_TABLES as $table) {
            if (Schema::hasTable($table)) {
                try {
                    DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                } catch (\Exception) {
                }
            }
        }

        // Clear cache
        try {
            Artisan::call('cache:clear');
        } catch (\Exception) {
        }

        $this->newLine();
        $this->info('✅ Database reset to initial state');

        // Show summary
        $this->table(['Item', 'Status'], [
            ['users.id=1 (系統帳號)', DB::table('users')->where('id', 1)->exists() ? '✅ exists' : '❌ missing'],
            ['admin_users', DB::table('admin_users')->count() . ' accounts'],
            ['subscription_plans', DB::table('subscription_plans')->count() . ' plans'],
            ['system_settings', DB::table('system_settings')->count() . ' entries'],
            ['user data', 'cleared'],
        ]);

        if ($this->option('with-test-data')) {
            $this->newLine();
            $this->info('Importing test data...');
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\TestDataSeeder', '--force' => true]);
            $userCount = DB::table('users')->where('id', '!=', 1)->count();
            $this->info("  ✓ {$userCount} test users imported");
        }

        return self::SUCCESS;
    }
}
