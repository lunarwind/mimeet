<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\Message;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GdprService
{
    public function requestDeletion(User $user, string $password): void
    {
        if (!Hash::check($password, $user->password)) {
            throw new \Exception('PASSWORD_INCORRECT');
        }
        if ($user->status === 'pending_deletion') {
            throw new \Exception('DELETION_PENDING');
        }

        $user->forceFill([
            'status' => 'pending_deletion',
            'delete_requested_at' => now(),
        ])->save();

        Log::info("[GDPR] User #{$user->id} requested deletion");
    }

    public function cancelDeletion(User $user): void
    {
        if ($user->status !== 'pending_deletion') {
            throw new \Exception('NO_PENDING_DELETION');
        }

        $user->forceFill([
            'status' => 'active',
            'delete_requested_at' => null,
        ])->save();

        Log::info("[GDPR] User #{$user->id} cancelled deletion request");
    }

    /**
     * Phase 1: Anonymize PII and quarantine media files.
     * Media files are moved to the quarantine disk rather than deleted immediately.
     */
    public function anonymizeUser(User $user): void
    {
        if ($user->status === 'deleted') {
            return;
        }

        $email = $user->email;

        // Move user media files to quarantine before anonymizing
        $this->quarantineUserMedia($user);

        DB::transaction(function () use ($user) {
<<<<<<< HEAD
            // Use DB::table to bypass fillable protection for admin-only fields
            DB::table('users')->where('id', $user->id)->update([
=======
            // Quarantine user's uploaded files before anonymizing
            $this->quarantineUserFiles($user);

            $user->forceFill([
>>>>>>> develop
                'email' => "deleted_{$user->id}@removed.mimeet",
                'phone' => null,
                'nickname' => '已刪除用戶',
                'avatar_url' => null,
                'profile' => null,
                'privacy_settings' => null,
                'preferences' => null,
                'password' => Hash::make(Str::random(32)),
                'status' => 'deleted',
                'deleted_at' => now(),
            ])->saveQuietly();

            FcmToken::where('user_id', $user->id)->delete();
            $user->tokens()->delete();
        });

        Log::info("[GDPR] User #{$user->id} anonymized (original email: {$email})");
    }

<<<<<<< HEAD
    /**
     * Move user media files to quarantine disk for deferred deletion.
     */
    private function quarantineUserMedia(User $user): void
    {
        $sourceDisk = Storage::disk('private');
        $quarantineDisk = Storage::disk('quarantine');
        $userMediaPath = "users/{$user->id}";

        if (!$sourceDisk->exists($userMediaPath)) {
            Log::info("[GDPR] No media files found for user #{$user->id}");
            return;
        }

        $files = $sourceDisk->allFiles($userMediaPath);
        $quarantineBase = "user_{$user->id}/" . now()->format('Ymd_His');

        foreach ($files as $file) {
            try {
                $contents = $sourceDisk->get($file);
                $quarantineDisk->put("{$quarantineBase}/{$file}", $contents);
                $sourceDisk->delete($file);
            } catch (\Exception $e) {
                Log::warning("[GDPR] Failed to quarantine file {$file} for user #{$user->id}: {$e->getMessage()}");
            }
        }

        Log::info("[GDPR] Quarantined " . count($files) . " media files for user #{$user->id}");
    }

    /**
     * Phase 2: Delete quarantined files that are older than 30 days.
     * Called by a scheduled command.
     */
    public function purgeExpiredQuarantineFiles(): int
    {
        $quarantineDisk = Storage::disk('quarantine');
        $cutoff = now()->subDays(30);
        $deletedCount = 0;

        $directories = $quarantineDisk->directories();
        foreach ($directories as $userDir) {
            $timestampDirs = $quarantineDisk->directories($userDir);
            foreach ($timestampDirs as $timestampDir) {
                // Extract timestamp from directory name (format: Ymd_His)
                $dirName = basename($timestampDir);
                try {
                    $dirDate = \Carbon\Carbon::createFromFormat('Ymd_His', $dirName);
                } catch (\Exception $e) {
                    continue;
                }

                if ($dirDate && $dirDate->lt($cutoff)) {
                    $files = $quarantineDisk->allFiles($timestampDir);
                    foreach ($files as $file) {
                        $quarantineDisk->delete($file);
                        $deletedCount++;
                    }
                    $quarantineDisk->deleteDirectory($timestampDir);
                    Log::info("[GDPR] Purged quarantine directory: {$timestampDir}");
                }
            }

            // Clean up empty user directories
            if (empty($quarantineDisk->allFiles($userDir))) {
                $quarantineDisk->deleteDirectory($userDir);
            }
        }

        return $deletedCount;
=======
    // ═════════════════════════════════════════════════════════════════
    //  Quarantine — move files instead of deleting immediately
    // ═════════════════════════════════════════════════════════════════

    /**
     * Move a file from uploads/ to quarantine/ instead of deleting it.
     * Returns the new quarantine path.
     */
    public function quarantineFile(string $currentPath): ?string
    {
        $disk = Storage::disk('public');

        if (!$disk->exists($currentPath)) {
            return null;
        }

        // Build quarantine path: quarantine/{date}/{original-path}
        $quarantinePath = 'quarantine/' . now()->format('Y-m-d') . '/' . $currentPath;

        // Ensure directory exists and move
        $dir = dirname($quarantinePath);
        if (!$disk->exists($dir)) {
            $disk->makeDirectory($dir);
        }

        $disk->move($currentPath, $quarantinePath);

        Log::info("[Quarantine] Moved {$currentPath} → {$quarantinePath}");

        return $quarantinePath;
    }

    /**
     * Quarantine all uploaded files belonging to a user.
     */
    private function quarantineUserFiles(User $user): void
    {
        $disk = Storage::disk('public');
        $userDir = "photos/{$user->id}";

        if ($disk->exists($userDir)) {
            $files = $disk->allFiles($userDir);
            foreach ($files as $file) {
                $this->quarantineFile($file);
            }
            Log::info("[Quarantine] Moved " . count($files) . " file(s) for user #{$user->id}");
        }
    }

    // ═════════════════════════════════════════════════════════════════
    //  Purge — permanently delete after retention period
    // ═════════════════════════════════════════════════════════════════

    /**
     * Permanently delete quarantined files older than $days.
     */
    public function purgeQuarantinedFiles(int $days): int
    {
        $disk = Storage::disk('public');
        $cutoff = now()->subDays($days);
        $purged = 0;

        if (!$disk->exists('quarantine')) {
            return 0;
        }

        // Scan date-based directories: quarantine/2026-01-15/...
        foreach ($disk->directories('quarantine') as $dateDir) {
            $dirName = basename($dateDir);

            // Parse directory name as date
            try {
                $dirDate = \Carbon\Carbon::parse($dirName);
            } catch (\Exception) {
                continue;
            }

            if ($dirDate->lte($cutoff)) {
                $files = $disk->allFiles($dateDir);
                $purged += count($files);
                $disk->deleteDirectory($dateDir);
                Log::info("[Quarantine] Purged directory {$dateDir} ({$purged} files)");
            }
        }

        return $purged;
    }

    /**
     * Hard-delete soft-deleted messages older than $days.
     */
    public function purgeDeletedMessages(int $days): int
    {
        return Message::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays($days))
            ->forceDelete();
    }

    /**
     * Delete old activity logs beyond retention period.
     */
    public function purgeOldActivityLogs(int $days): int
    {
        return UserActivityLog::where('created_at', '<=', now()->subDays($days))->delete();
>>>>>>> develop
    }
}
