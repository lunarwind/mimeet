<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifySystemUser extends Command
{
    protected $signature = 'mimeet:verify-system-user
                            {--fix : Re-create id=1 if missing}';
    protected $description = 'Check that users.id=1 (system account) exists and is correct';

    public function handle(): int
    {
        $user = DB::table('users')->where('id', 1)->first();

        if (! $user) {
            $this->error('❌ users.id=1 does NOT exist');

            if ($this->option('fix')) {
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
                $this->info('✅ Re-created users.id=1');
                return self::SUCCESS;
            }

            $this->warn('Run with --fix to re-create it');
            return self::FAILURE;
        }

        $this->info('✅ users.id=1 exists');
        $this->table(['Field', 'Value'], [
            ['id', $user->id],
            ['email', $user->email],
            ['nickname', $user->nickname],
            ['status', $user->status],
            ['membership_level', $user->membership_level],
            ['credit_score', $user->credit_score],
            ['email_verified', $user->email_verified ? 'true' : 'false'],
            ['created_at', $user->created_at],
        ]);

        $issues = [];
        if ($user->status !== 'active') $issues[] = "status is '{$user->status}' (should be 'active')";
        if ($user->email !== 'system@mimeet.tw') $issues[] = "email is '{$user->email}' (expected 'system@mimeet.tw')";

        if (count($issues) > 0) {
            $this->warn('⚠️  Issues found:');
            foreach ($issues as $issue) {
                $this->line("  - {$issue}");
            }
        } else {
            $this->info('No issues found.');
        }

        return self::SUCCESS;
    }
}
