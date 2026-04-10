<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GdprService;
use Illuminate\Console\Command;

class ProcessGdprDeletions extends Command
{
    protected $signature = 'gdpr:process-deletions {--purge-quarantine : Also purge expired quarantine files (Phase 2)}';
    protected $description = 'Anonymize users whose deletion request has expired (7-day cooling period) and optionally purge quarantined files older than 30 days';

    public function handle(GdprService $gdprService): int
    {
        // Phase 1: Anonymize users past 7-day cooling period
        $users = User::where('status', 'pending_deletion')
            ->where('delete_requested_at', '<=', now()->subDays(7))
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users to process.');
        } else {
            foreach ($users as $user) {
                $this->info("Anonymizing user #{$user->id} ({$user->email})...");
                $gdprService->anonymizeUser($user);
            }
            $this->info("Phase 1: Processed {$users->count()} GDPR deletion(s).");
        }

        // Phase 2: Purge quarantined files older than 30 days
        if ($this->option('purge-quarantine')) {
            $this->info('Phase 2: Purging expired quarantine files...');
            $deletedCount = $gdprService->purgeExpiredQuarantineFiles();
            $this->info("Phase 2: Purged {$deletedCount} quarantined file(s).");
        }

        return 0;
    }
}
