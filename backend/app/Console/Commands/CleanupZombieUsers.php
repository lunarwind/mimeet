<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GdprService;
use App\Support\Mask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupZombieUsers extends Command
{
    protected $signature = 'users:cleanup-zombies
        {--apply : Actually run anonymization (default is dry-run)}
        {--force : Skip interactive confirmation (required for non-interactive ssh execution)}';

    protected $description = 'Anonymize legacy zombie users (soft-deleted but identifiers not released).';

    public function handle(GdprService $gdprService): int
    {
        $zombies = User::withTrashed()
            ->whereNotNull('deleted_at')
            ->where(function ($q) {
                $q->where('status', '!=', 'deleted')
                  ->orWhere('email', 'NOT LIKE', 'deleted_%@removed.mimeet');
            })
            ->where('status', '!=', 'pending_deletion')
            ->get();

        $pendingDeletionCount = User::withTrashed()
            ->where('status', 'pending_deletion')
            ->where('delete_requested_at', '<=', now()->subDays(7))
            ->count();

        $count = $zombies->count();

        $this->line('─────────────────────────────────────────');
        $this->line("殭屍 user（本 command 將處理）：{$count} 筆");
        $this->line("pending_deletion 待處理（GDPR cron 7 天後處理，本 command 不碰）：{$pendingDeletionCount} 筆");
        $this->line('─────────────────────────────────────────');

        if ($count === 0) {
            $this->info('沒有殭屍 user 需要清理。');
            return self::SUCCESS;
        }

        $this->line('Sample（最多前 10 筆）：');
        foreach ($zombies->take(10) as $z) {
            $this->line(sprintf(
                '  #%d  email=%s  phone=%s  status=%s  deleted_at=%s',
                $z->id,
                Mask::email($z->email) ?? '(null)',
                Mask::phone($z->phone) ?? '(null)',
                $z->status,
                $z->deleted_at?->toDateTimeString() ?? '(null)'
            ));
        }

        if (!$this->option('apply')) {
            $this->warn('Dry-run 模式：未執行任何 anonymize。加 --apply --force 才會實際執行。');
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("真的要 anonymize {$count} 個殭屍 user 嗎？此操作不可逆。")) {
                $this->warn('已取消');
                return self::SUCCESS;
            }
        }

        $logDir = storage_path('logs/cleanup');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logPath = $logDir . '/zombies-' . now()->format('Ymd-His') . '.log';

        $anonymized = 0;
        $skipped = 0;
        foreach ($zombies as $z) {
            try {
                $gdprService->anonymizeUser($z, force: true);
                $anonymized++;
                $this->logToFile($logPath, sprintf("anonymized #%d (was %s)", $z->id, Mask::email($z->getOriginal('email')) ?? '(null)'));
            } catch (\Throwable $e) {
                $skipped++;
                $this->logToFile($logPath, sprintf("FAILED #%d: %s", $z->id, $e->getMessage()));
                $this->error("  failed #{$z->id}: " . $e->getMessage());
            }
        }

        $remainingPending = User::withTrashed()
            ->where('status', 'pending_deletion')
            ->where('delete_requested_at', '<=', now()->subDays(7))
            ->count();

        $this->line('─────────────────────────────────────────');
        $this->info("匿名化 {$anonymized} 筆、跳過 {$skipped} 筆");
        $this->info("pending_deletion 仍待 cron 處理：{$remainingPending} 筆");
        $this->line("Log: {$logPath}");
        $this->line('─────────────────────────────────────────');

        Log::info('[CleanupZombieUsers] complete', [
            'anonymized' => $anonymized,
            'skipped' => $skipped,
            'pending_deletion_before' => $pendingDeletionCount,
            'pending_deletion_after' => $remainingPending,
        ]);

        return self::SUCCESS;
    }

    private function logToFile(string $path, string $line): void
    {
        @file_put_contents($path, '[' . now()->toDateTimeString() . '] ' . $line . PHP_EOL, FILE_APPEND);
    }
}
