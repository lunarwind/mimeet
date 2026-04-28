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

            // ── 重建 uid=1 官方示範帳號（每次 reset 都更新為最新預設值）──
            DB::table('users')->updateOrInsert(
                ['id' => 1],
                [
                    'email'            => 'admin@mimeet.club',
                    'password'         => bcrypt('SYSTEM_ACCOUNT_DO_NOT_LOGIN'),
                    'nickname'         => 'MiMeet 官方',
                    'gender'           => 'female',
                    'birth_date'       => '2000-04-04',
                    'location'         => '台北市',
                    'height'           => 172,
                    'weight'           => 55,
                    'occupation'       => '金融分析師',
                    'education'        => 'master',
                    'bio'              => "台北都會區OL，金融碩士，現職為金融分析師。理性與感性並存的氣質，既能在數據與市場之間精準判斷，也帶著一絲從容與優雅，讓人不自覺被吸引。\n\n工作上，冷靜、專注、精準；在生活裡，則多了一分柔和與細膩。習慣用理性掌控世界，卻也懂得在適當的時候，展現出屬於自己的溫柔與撫媚，讓人感受到一種恰到好處的距離。",
                    'email_verified'   => true,
                    'phone_verified'   => true,
                    'membership_level' => 3,
                    'credit_score'     => 100,
                    'status'           => 'active',
                    // F27 profile fields（2026-04-20 新增 9 欄）
                    'style'             => 'intellectual',
                    'dating_budget'     => 'luxury',
                    'dating_frequency'  => 'flexible',
                    'dating_type'       => json_encode(['dining', 'travel', 'mentorship']),
                    'relationship_goal' => 'long_term',
                    'smoking'           => 'never',
                    'drinking'          => 'social',
                    'car_owner'         => true,
                    'availability'      => json_encode(['weekend', 'flexible']),
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ],
            );
            $this->line('  ✓ uid=1 官方示範帳號已重建（admin@mimeet.club）');

            // ── 驗證 uid=1 資料正確 ──
            $u1 = DB::table('users')->where('id', 1)->first();
            $this->line("  ✓ 驗證：email={$u1->email}, gender={$u1->gender}, membership_level={$u1->membership_level}");
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

        // ── admin_users: 僅保留系統預設 super-admin ──────────────────────
        $superAdminEmail = env('SUPER_ADMIN_EMAIL', 'chuck@lunarwind.org');

        // 1) 先確保 chuck 存在（防止「reset 後沒有任何 admin」災難）
        $existingChuck = DB::table('admin_users')->where('email', $superAdminEmail)->first();
        if (!$existingChuck) {
            // 完全不存在 → 用 env 預設密碼建立
            DB::table('admin_users')->insert([
                'name'       => env('ADMIN_NAME', 'Super Admin'),
                'email'      => $superAdminEmail,
                'password'   => bcrypt(env('SUPER_ADMIN_PASSWORD', env('ADMIN_PASSWORD', 'ChangeMe@2026'))),
                'role'       => 'super_admin',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->warn("  ⚠ admin_users: {$superAdminEmail} not found, recreated with default password");
        } else {
            // 存在 → 確保 role=super_admin & is_active=true，但不動 password
            DB::table('admin_users')
                ->where('email', $superAdminEmail)
                ->update(['role' => 'super_admin', 'is_active' => true, 'updated_at' => now()]);
        }

        // 2) 再刪除其他 admin_users（順序不可顛倒）
        $deletedAdmins = DB::table('admin_users')
            ->where('email', '!=', $superAdminEmail)
            ->delete();
        $this->line("  ✓ admin_users (kept {$superAdminEmail}, deleted {$deletedAdmins} others)");

        // Ensure subscription plans exist
        if (DB::table('subscription_plans')->count() === 0) {
            $this->warn('  ⚠ subscription_plans table is empty — re-seeding...');
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SubscriptionPlanSeeder', '--force' => true]);
        } else {
            $this->line('  ✓ subscription_plans: ' . DB::table('subscription_plans')->count() . ' plans (preserved)');
        }

        // Ensure SEO meta defaults exist (A17)
        if (Schema::hasTable('seo_metas') && DB::table('seo_metas')->count() === 0) {
            $this->warn('  ⚠ seo_metas table is empty — re-seeding...');
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SeoMetaSeeder', '--force' => true]);
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
            ['users.id=1 (官方示範帳號)', DB::table('users')->where('id', 1)->exists()
                ? '✅ ' . DB::table('users')->where('id', 1)->value('email')
                : '❌ missing'],
            ['admin_users', DB::table('admin_users')->count() . ' account(s) — only ' . ($superAdminEmail ?? env('SUPER_ADMIN_EMAIL', 'chuck@lunarwind.org'))],
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
