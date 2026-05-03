<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * users:backfill-phone-hash
 *
 * 一次性 backfill 命令（不放 schedule）。Cleanup PR-5 部署後執行：
 *   docker exec mimeet-app php artisan users:backfill-phone-hash
 *
 * 為既有 user 計算 phone_hash（SHA-256 of E.164 normalized phone）。
 * 偵測到衝突（同 normalized phone 已存在於其他 user）會 skip + 標 manual review。
 */
class BackfillPhoneHashCommand extends Command
{
    protected $signature = 'users:backfill-phone-hash {--dry-run : Only show what would happen, do not write}';
    protected $description = '為既有 user 計算 phone_hash（一次性，PR-5 部署後執行）';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('[DRY RUN] No data will be written.');
        }

        $users = User::whereNull('phone_hash')->get();
        $total = $users->count();
        $this->info("Found {$total} user(s) without phone_hash.");

        $success = 0;
        $skippedEmpty = 0;
        $conflicts = 0;

        foreach ($users as $user) {
            $hash = User::computePhoneHash($user->phone);

            if (!$hash) {
                $this->line("  - skip user#{$user->id}: empty phone");
                $skippedEmpty++;
                continue;
            }

            // Defensive：偵測同一 normalized phone 已存在於其他 user
            $conflict = User::where('phone_hash', $hash)
                ->where('id', '!=', $user->id)
                ->first();

            if ($conflict) {
                $this->error("  ✗ CONFLICT user#{$user->id} vs user#{$conflict->id} (same normalized phone)");
                Log::warning('[BackfillPhoneHash] Conflict detected', [
                    'user_id'        => $user->id,
                    'conflict_with'  => $conflict->id,
                ]);
                $conflicts++;
                continue;
            }

            if (!$dryRun) {
                $user->phone_hash = $hash;
                $user->saveQuietly(); // 跳過 saving event 避免重複算
            }
            $this->line("  ✓ user#{$user->id} → " . substr($hash, 0, 12) . '…');
            $success++;
        }

        $this->info('');
        $this->info("Done. Success: {$success}, Skipped (empty): {$skippedEmpty}, Conflicts: {$conflicts}");

        return $conflicts > 0 ? self::FAILURE : self::SUCCESS;
    }
}
