<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GdprService;
use Illuminate\Console\Command;

class ProcessGdprDeletions extends Command
{
    protected $signature = 'gdpr:process-deletions';
    protected $description = 'Anonymize users whose deletion request has expired (7-day cooling period)';

    public function handle(GdprService $gdprService): int
    {
        $users = User::where('status', 'pending_deletion')
            ->where('delete_requested_at', '<=', now()->subDays(7))
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users to process.');
            return 0;
        }

        foreach ($users as $user) {
            $this->info("Anonymizing user #{$user->id} ({$user->email})...");
            $gdprService->anonymizeUser($user);
        }

        $this->info("Processed {$users->count()} GDPR deletion(s).");
        return 0;
    }
}
