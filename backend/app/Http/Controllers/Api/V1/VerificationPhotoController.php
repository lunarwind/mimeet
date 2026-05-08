<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserVerification;
use App\Services\UserActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VerificationPhotoController extends Controller
{
    /**
     * POST /api/v1/me/verification-photo/request
     * Generate a random code for photo verification (Lv1.5)
     */
    public function request(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->gender !== 'female') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_ELIGIBLE', 'message' => '此驗證僅限女性會員'],
            ], 422);
        }

        if ((float) $user->membership_level >= 1.5) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ALREADY_VERIFIED', 'message' => '已通過驗證'],
            ], 422);
        }

        return DB::transaction(function () use ($user) {
            // Lock the user row to serialize concurrent verification flows
            User::whereKey($user->id)->lockForUpdate()->first();

            // Block new code requests if a pending_review already exists
            $hasPendingReview = UserVerification::where('user_id', $user->id)
                ->where('status', 'pending_review')
                ->exists();

            if ($hasPendingReview) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VERIFICATION_PENDING_REVIEW',
                        'message' => '照片認證審核中，請等待管理員審核；若未通過，才能重新申請。',
                    ],
                ], 422);
            }

            // Expire any existing pending codes
            UserVerification::where('user_id', $user->id)
                ->where('status', 'pending_code')
                ->update(['status' => 'expired']);

            $code = strtoupper(Str::random(6));
            $expiresAt = now()->addMinutes(10);

            $verification = UserVerification::create([
                'user_id' => $user->id,
                'random_code' => $code,
                'status' => 'pending_code',
                'expires_at' => $expiresAt,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'verification_id' => $verification->id,
                    'random_code' => $code,
                    'expires_at' => $expiresAt->toISOString(),
                    'remaining_seconds' => 600,
                ],
            ]);
        });
    }

    /**
     * POST /api/v1/me/verification-photo/upload
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'photo_url' => 'required|string|max:500',
            'random_code' => 'required|string|max:10',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($request, $user) {
            User::whereKey($user->id)->lockForUpdate()->first();

            // Defense-in-depth: block upload if a pending_review already exists
            $hasPendingReview = UserVerification::where('user_id', $user->id)
                ->where('status', 'pending_review')
                ->exists();

            if ($hasPendingReview) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VERIFICATION_PENDING_REVIEW',
                        'message' => '照片認證審核中，請等待管理員審核；若未通過，才能重新申請。',
                    ],
                ], 422);
            }

            $verification = UserVerification::where('user_id', $user->id)
                ->where('random_code', $request->input('random_code'))
                ->where('status', 'pending_code')
                ->first();

            if (!$verification) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'VERIFICATION_NOT_FOUND', 'message' => '找不到驗證記錄'],
                ], 422);
            }

            if ($verification->expires_at->isPast()) {
                $verification->update(['status' => 'expired']);
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'VERIFICATION_EXPIRED', 'message' => '驗證碼已過期，請重新申請'],
                ], 422);
            }

            $verification->update([
                'photo_url' => $request->input('photo_url'),
                'status' => 'pending_review',
            ]);

            UserActivityLogService::logVerification($user->id, 'photo_lv15', $request);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'pending_review',
                    'message' => '照片已送出，審核通常在 24 小時內完成',
                    'submitted_at' => now()->toISOString(),
                ],
            ]);
        });
    }

    /**
     * GET /api/v1/me/verification-photo/status
     *
     * Priority 1: any pending_review (the locked state — must surface to frontend
     * even when newer pending_code records exist due to historical dirty data).
     * Priority 2: most recent record by created_at desc.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        $verification = UserVerification::where('user_id', $user->id)
            ->where('status', 'pending_review')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$verification) {
            $verification = UserVerification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if (!$verification) {
            return response()->json([
                'success' => true,
                'data' => ['status' => 'none'],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $verification->status,
                'submitted_at' => $verification->created_at->toISOString(),
                'reviewed_at' => $verification->reviewed_at?->toISOString(),
                'reject_reason' => $verification->reject_reason,
            ],
        ]);
    }
}
