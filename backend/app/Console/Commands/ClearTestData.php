<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearTestData extends Command
{
    protected $signature = 'mimeet:clear-test-data
                            {--force : Skip confirmation}';
    protected $description = 'Remove all @mimeet.test test accounts and their related data';

    public function handle(): int
    {
        $testUserIds = DB::table('users')
            ->where('email', 'like', '%@mimeet.test')
            ->pluck('id')
            ->toArray();

        if (empty($testUserIds)) {
            $this->info('No test accounts found.');
            return self::SUCCESS;
        }

        $this->info("Found " . count($testUserIds) . " test accounts (@mimeet.test)");

        if (! $this->option('force') && ! $this->confirm('Delete all test accounts and their data?', false)) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $tables = [
                'user_profile_visits' => ['visitor_id', 'visited_id'],
                'user_follows'        => ['follower_id', 'following_id'],
                'user_blocks'         => ['blocker_id', 'blocked_id'],
                'user_activity_logs'  => ['user_id'],
                'user_verifications'  => ['user_id'],
                'notifications'       => ['user_id'],
                'credit_score_histories' => ['user_id'],
                'fcm_tokens'          => ['user_id'],
                'reports'             => ['reporter_id'],
                'subscriptions'       => ['user_id'],
                'orders'              => ['user_id'],
                'personal_access_tokens' => ['tokenable_id'],
            ];

            foreach ($tables as $table => $columns) {
                if (! Schema::hasTable($table)) continue;
                foreach ($columns as $col) {
                    $deleted = DB::table($table)->whereIn($col, $testUserIds)->delete();
                    if ($deleted > 0) {
                        $this->line("  ✓ {$table}.{$col}: {$deleted} rows");
                    }
                }
            }

            // Conversations where either party is a test user
            if (Schema::hasTable('conversations')) {
                $convIds = DB::table('conversations')
                    ->whereIn('user_a_id', $testUserIds)
                    ->orWhereIn('user_b_id', $testUserIds)
                    ->pluck('id');

                if ($convIds->isNotEmpty()) {
                    $msgDeleted = DB::table('messages')->whereIn('conversation_id', $convIds)->delete();
                    $convDeleted = DB::table('conversations')->whereIn('id', $convIds)->delete();
                    $this->line("  ✓ conversations: {$convDeleted}, messages: {$msgDeleted}");
                }
            }

            // Delete the test users (skip id=1 safety)
            $userDeleted = DB::table('users')
                ->where('email', 'like', '%@mimeet.test')
                ->where('id', '!=', 1)
                ->delete();
            $this->line("  ✓ users: {$userDeleted}");
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info("✅ Cleared {$userDeleted} test accounts and related data");

        return self::SUCCESS;
    }
}
