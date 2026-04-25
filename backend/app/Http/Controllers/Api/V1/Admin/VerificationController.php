<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserVerification;
use App\Services\CreditScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'data' => ['verifications' => $paginated->items()],
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'total_pages' => $paginated->lastPage(),
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
            'data' => ['verifications' => $paginated->items()],
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'total_pages' => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * PATCH /api/v1/admin/verifications/{id}
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $verification = UserVerification::findOrFail($id);
        $adminId = $request->user()->id;

        $request->validate([
            'result' => 'required|in:approved,rejected',
            'reject_reason' => 'required_if:result,rejected|nullable|string|max:500',
        ]);

        $result = $request->input('result');

        if ($result === 'approved') {
            $verification->update([
                'status' => 'approved',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);

            // Upgrade to Lv1.5 and add credit
            $user = $verification->user;
            if ($user) {
                $user->forceFill(['membership_level' => 1.5])->save();
                CreditScoreService::adjust($user, CreditScoreService::getConfig('credit_add_adv_verify_female', 15), 'adv_verify_female', '女性驗證通過', $adminId);
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
    }
}
