<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\GdprService;
use Illuminate\Console\Command;

class ProcessGdprDeletions extends Command
{
    protected $signature = 'gdpr:process-deletions';
    protected $description = 'Anonymize users whose deletion request has exceeded the retention period, and purge quarantined files';

    public function handle(GdprService $gdprService): int
    {
        $retentionDays = (int) SystemSetting::get('data_retention_days', 180);
        $cooldownDays = 7; // GDPR deletion cooldown (always 7 days, separate from retention)

        // Phase 1: Anonymize users past the 7-day cooling period
        $pendingUsers = User::where('status', 'pending_deletion')
            ->where('delete_requested_at', '<=', now()->subDays($cooldownDays))
            ->get();

        if ($pendingUsers->isNotEmpty()) {
            foreach ($pendingUsers as $user) {
                $this->info("Anonymizing user #{$user->id} ({$user->email})...");
                $gdprService->anonymizeUser($user);
            }
            $this->info("Phase 1: Anonymized {$pendingUsers->count()} user(s).");
        } else {
            $this->info('Phase 1: No pending deletion users to process.');
        }

        // Phase 2: Purge quarantined files older than retention period
        $purgedCount = $gdprService->purgeQuarantinedFiles($retentionDays);
        $this->info("Phase 2: Purged {$purgedCount} quarantined file(s) older than {$retentionDays} days.");

        // Phase 3: Hard-delete recalled messages whose recalled_at is older than retention period.
        // 2026-05-17：改用 purgeOldRecalledMessages（依 recalled_at），取代 dead code purgeDeletedMessages。
        // 對應 DEV-001 §6.3.1 兩階段銷毀第二階段。
        $messagesDeleted = $gdprService->purgeOldRecalledMessages($retentionDays);
        $this->info("Phase 3: Permanently deleted {$messagesDeleted} recalled message(s) older than {$retentionDays} days.");

        // Phase 4: Trim old activity logs
        $logsDeleted = $gdprService->purgeOldActivityLogs($retentionDays);
        $this->info("Phase 4: Trimmed {$logsDeleted} activity log(s) older than {$retentionDays} days.");

        return 0;
    }
}
