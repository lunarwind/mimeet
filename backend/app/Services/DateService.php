<?php

namespace App\Services;

use App\Models\DateInvitation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DateService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function createInvitation(User $inviter, array $data): DateInvitation
    {
        $dateTime = Carbon::parse($data['date_time']);

        $invitation = DateInvitation::create([
            'inviter_id' => $inviter->id,
            'invitee_id' => $data['invitee_id'],
            'date_time' => $dateTime,
            'location_name' => $data['location_name'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'qr_token' => bin2hex(random_bytes(32)),
            'status' => 'pending',
            'expires_at' => $dateTime->copy()->addMinutes(30),
            'created_at' => now(),
        ]);

        // Notify invitee
        $invitee = User::find($data['invitee_id']);
        if ($invitee) {
            $this->notificationService->notifyDateInvitation($invitee, $invitation->id, $inviter);
        }

        return $invitation;
    }

    public function acceptInvitation(DateInvitation $inv, User $user): DateInvitation
    {
        if ($inv->invitee_id !== $user->id) {
            throw new \Exception('NOT_INVITEE');
        }
        if ($inv->status !== 'pending') {
            throw new \Exception('INVALID_STATUS');
        }

        $inv->update(['status' => 'accepted']);
        return $inv->fresh();
    }

    public function declineInvitation(DateInvitation $inv, User $user): DateInvitation
    {
        if ($inv->invitee_id !== $user->id) {
            throw new \Exception('NOT_INVITEE');
        }
        if ($inv->status !== 'pending') {
            throw new \Exception('INVALID_STATUS');
        }

        $inv->update(['status' => 'cancelled']);
        return $inv->fresh();
    }

    /**
     * @throws \Exception
     */
    public function verifyQrToken(string $token, User $scanner, ?float $lat, ?float $lng): array
    {
        // Use lockForUpdate to prevent race condition when both users scan simultaneously
        $inv = \Illuminate\Support\Facades\DB::transaction(function () use ($token) {
            return DateInvitation::where('qr_token', $token)->lockForUpdate()->first();
        }) ?? DateInvitation::where('qr_token', $token)->first();

        if (!$inv) {
            throw new \Exception('TOKEN_NOT_FOUND');
        }
        if ($inv->status === 'verified') {
            throw new \Exception('TOKEN_ALREADY_USED');
        }
        if ($inv->status === 'cancelled') {
            throw new \Exception('TOKEN_CANCELLED');
        }
        if ($inv->expires_at->isPast()) {
            $inv->update(['status' => 'expired']);
            throw new \Exception('TOKEN_EXPIRED');
        }

        $isInviter = $inv->inviter_id === $scanner->id;
        $isInvitee = $inv->invitee_id === $scanner->id;

        if (!$isInviter && !$isInvitee) {
            throw new \Exception('NOT_PARTICIPANT');
        }

        // Record scan + GPS
        $gpsVerified = false;
        if ($lat !== null && $lng !== null) {
            $distance = $this->calculateDistance($lat, $lng, (float) $inv->latitude, (float) $inv->longitude);
            $gpsVerified = $distance <= 500;
        }

        if ($isInviter) {
            $inv->update([
                'inviter_scanned_at' => now(),
                'inviter_gps_lat' => $lat,
                'inviter_gps_lng' => $lng,
                'inviter_gps_verified' => $gpsVerified,
            ]);
        } else {
            $inv->update([
                'invitee_scanned_at' => now(),
                'invitee_gps_lat' => $lat,
                'invitee_gps_lng' => $lng,
                'invitee_gps_verified' => $gpsVerified,
            ]);
        }

        $inv->refresh();

        // Check if both scanned
        if ($inv->inviter_scanned_at && $inv->invitee_scanned_at) {
            return $this->completeVerification($inv);
        }

        return [
            'status' => 'waiting',
            'message' => '等待對方掃碼',
        ];
    }

    private function completeVerification(DateInvitation $inv): array
    {
        $gpsPassed = $inv->inviter_gps_verified && $inv->invitee_gps_verified;

        // Cooldown: same pair within 24h
        $minId = min($inv->inviter_id, $inv->invitee_id);
        $maxId = max($inv->inviter_id, $inv->invitee_id);
        $cooldownKey = "date_score:{$minId}:{$maxId}";

        $score = 0;
        $alreadyScored = Cache::get($cooldownKey);

        if (!$alreadyScored) {
            $score = $gpsPassed
                ? (int) (config('mimeet.credit_add_date_gps', 5))
                : (int) (config('mimeet.credit_add_date_no_gps', 2));

            $inviter = User::find($inv->inviter_id);
            $invitee = User::find($inv->invitee_id);

            if ($inviter) {
                CreditScoreService::adjust($inviter, $score, 'date_verified', 'QR約會驗證');
            }
            if ($invitee) {
                CreditScoreService::adjust($invitee, $score, 'date_verified', 'QR約會驗證');
            }

            Cache::put($cooldownKey, 1, 86400);
        }

        $inv->update([
            'gps_verification_passed' => $gpsPassed,
            'status' => 'verified',
            'verified_at' => now(),
            'score_awarded' => $score,
        ]);

        // Notify both users
        if ($score > 0) {
            if ($inviter) {
                $this->notificationService->notifyDateVerified($inviter, $score);
            }
            if ($invitee) {
                $this->notificationService->notifyDateVerified($invitee, $score);
            }
        }

        return [
            'status' => 'completed',
            'score_awarded' => $score,
            'gps_passed' => $gpsPassed,
        ];
    }

    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000; // Earth radius in metres
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $deltaPhi = deg2rad($lat2 - $lat1);
        $deltaLambda = deg2rad($lng2 - $lng1);

        $a = sin($deltaPhi / 2) ** 2
            + cos($phi1) * cos($phi2) * sin($deltaLambda / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
