<?php

namespace App\Services;

use App\Models\CreditScoreHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditScoreService
{
    /**
     * Adjust a user's credit score with history logging.
     */
    public static function adjust(User $user, int $delta, string $type, string $reason, ?int $operatorId = null): void
    {
        $before = $user->credit_score;
        $after = max(0, min(100, $before + $delta));

        DB::transaction(function () use ($user, $delta, $type, $reason, $operatorId, $before, $after) {
            $user->forceFill(['credit_score' => $after])->save();

            CreditScoreHistory::create([
                'user_id' => $user->id,
                'delta' => $delta,
                'score_before' => $before,
                'score_after' => $after,
                'type' => $type,
                'reason' => $reason,
                'operator_id' => $operatorId,
            ]);
        });

        // Auto-suspend/restore is handled by CreditScoreObserver
    }

    /**
     * Get the current credit score (fresh from DB).
     */
    public static function getScore(User $user): int
    {
        return $user->fresh()->credit_score;
    }

    /**
     * Read a credit score config value from system_settings.
     * Falls back to $default if the key is not set.
     */
    public static function getConfig(string $key, int $default): int
    {
        return (int) \App\Models\SystemSetting::get("credit_score.{$key}", $default);
    }
}
