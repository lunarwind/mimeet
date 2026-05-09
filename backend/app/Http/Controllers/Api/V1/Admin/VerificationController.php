<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserVerification;
use App\Services\CreditScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerificationController extends Controller
{
    /**
     * GET /api/v1/admin/verifications/pending
     */
    public function pending(Request $request): JsonResponse
    {
        $query = UserVerification::with('user:id,nickname,gender,avatar_url,membership_level,credit_score')
            ->where('status', 'pending_review')
            ->orderBy('created_at', 'asc');

        $perPage = $request->input('per_page', 20);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'meta' => [
                'page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/verifications — all verifications with status filter
     */
    public function index(Request $request): JsonResponse
    {
        $query = UserVerification::with('user:id,nickname,gender,avatar_url,membership_level,credit_score')
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->input('per_page', 20);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'meta' => [
                'page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * PATCH /api/v1/admin/verifications/{id}
     *
     * Wrapped in DB::transaction + lockForUpdate to serialize concurrent admin
     * reviews. Without the lock, two admins approving simultaneously could
     * trigger the credit adjustment twice (double-approve).
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'result' => 'required|in:approved,rejected',
            'reject_reason' => 'required_if:result,rejected|nullable|string|max:500',
        ]);

        $adminId = $request->user()->id;
        $result = $request->input('result');

        return DB::transaction(function () use ($id, $adminId, $request, $result) {
            $verification = UserVerification::whereKey($id)->lockForUpdate()->firstOrFail();

            // Only pending_review records can be reviewed
            if ($verification->status !== 'pending_review') {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VERIFICATION_ALREADY_REVIEWED',
                        'message' => '此驗證紀錄狀態為 ' . $verification->status . '，無法再次審核。',
                    ],
                ], 422);
            }

            if ($result === 'approved') {
                $verification->update([
                    'status' => 'approved',
                    'reviewed_by' => $adminId,
                    'reviewed_at' => now(),
                ]);

                $user = $verification->user;
                if ($user) {
                    $user->forceFill(['membership_level' => 1.5])->save();
                    CreditScoreService::adjust(
                        $user,
                        CreditScoreService::getConfig('credit_add_adv_verify_female', 15),
                        'adv_verify_female',
                        '女性驗證通過',
                        $adminId
                    );
                }

                return response()->json([
                    'success' => true,
                    'message' => '驗證已核准，用戶已升級至 Lv1.5',
                ]);
            }

            // Rejected
            $verification->update([
                'status' => 'rejected',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'reject_reason' => $request->input('reject_reason'),
            ]);

            return response()->json([
                'success' => true,
                'message' => '驗證已拒絕',
            ]);
        });
    }
}
