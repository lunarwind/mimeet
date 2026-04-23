<?php

namespace App\Observers;

use App\Mail\AccountAutoSuspendedMail;
use App\Mail\AccountReactivatedMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreditScoreObserver
{
    public function updated(User $user): void
    {
        if (!$user->wasChanged('credit_score')) {
            return;
        }

        $oldScore = $user->getOriginal('credit_score');
        $newScore = $user->credit_score;

        // Auto-suspend: score dropped to 0 (don't overwrite manual suspension)
        if ($newScore <= 0 && $oldScore > 0 && !in_array($user->status, ['auto_suspended', 'suspended', 'deleted'])) {
            DB::table('users')->where('id', $user->id)->update([
                'status' => 'auto_suspended',
                'suspended_at' => now(),
            ]);
            Cache::put("suspended_user:{$user->id}", true, now()->addYear());
            Log::info("[AutoSuspend] user #{$user->id} suspended, score={$newScore}");
            if ($user->email) {
                Mail::to($user->email)->queue(new AccountAutoSuspendedMail($user));
            }
        }

        // Auto-restore: score reached 30+ while auto-suspended
        if ($newScore >= 30 && $user->status === 'auto_suspended') {
            DB::table('users')->where('id', $user->id)->update(['status' => 'active']);
            Cache::forget("suspended_user:{$user->id}");
            Log::info("[AutoRestore] user #{$user->id} restored, score={$newScore}");
            if ($user->email) {
                Mail::to($user->email)->queue(new AccountReactivatedMail($user));
            }
        }
    }
}
