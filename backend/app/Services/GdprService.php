<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

        $user->update([
            'status' => 'pending_deletion',
            'delete_requested_at' => now(),
        ]);

        Log::info("[GDPR] User #{$user->id} requested deletion");
    }

    public function cancelDeletion(User $user): void
    {
        if ($user->status !== 'pending_deletion') {
            throw new \Exception('NO_PENDING_DELETION');
        }

        $user->update([
            'status' => 'active',
            'delete_requested_at' => null,
        ]);

        Log::info("[GDPR] User #{$user->id} cancelled deletion request");
    }

    public function anonymizeUser(User $user): void
    {
        $email = $user->email;

        DB::transaction(function () use ($user) {
            $user->updateQuietly([
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
            ]);

            // Delete FCM tokens
            FcmToken::where('user_id', $user->id)->delete();

            // Revoke Sanctum tokens
            $user->tokens()->delete();

            // Note: user_verifications photos preserved for 1 year (legal requirement)
        });

        Log::info("[GDPR] User #{$user->id} anonymized (original email: {$email})");
    }
}
