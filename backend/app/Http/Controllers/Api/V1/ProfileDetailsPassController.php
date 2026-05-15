<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F40-d 詳細資料 24h 通行證
 *
 * 設計：
 *  - 時間制通行證（非 per-target）：扣 N 點 → details_pass_until = now + 24h
 *  - 已在通行證中 → 拒絕（方案 B），422 + 剩餘秒數
 *  - 看自己 / 付費中男性 / 已進階驗證女性 不需要通行證，本 endpoint 對他們沒意義
 *    但仍允許購買（未來訂閱到期後仍可用通行證撐到下次續訂）
 *
 *  POST /me/unlock-details — 啟用通行證
 */
class ProfileDetailsPassController extends Controller
{
    public function __construct(
        private readonly PointService $pointService,
    ) {}

    public function activate(Request $request): JsonResponse
    {
        $user = $request->user();
        $cost = (int) SystemSetting::get('point_cost_profile_details', 5);
        $durationHours = (int) SystemSetting::get('profile_details_duration_hours', 24);

        // 方案 B：已在通行證中 → 拒絕重複購買
        if ($user->isDetailsPassActive()) {
            $remaining = max(0, (int) now()->diffInSeconds($user->details_pass_until, false));
            return response()->json([
                'success' => false,
                'code' => 'DETAILS_PASS_ACTIVE',
                'message' => '通行證仍有效，請於到期後再購買。',
                'data' => [
                    'details_pass_until' => $user->details_pass_until?->toISOString(),
                    'remaining_seconds' => $remaining,
                ],
            ], 422);
        }

        // 餘額檢查
        if (!$this->pointService->canAfford($user, $cost)) {
            return response()->json([
                'success' => false,
                'code' => 'INSUFFICIENT_POINTS',
                'message' => "點數不足：需要 {$cost} 點，目前 " . (int) $user->points_balance . ' 點',
                'data' => [
                    'required' => $cost,
                    'current_balance' => (int) $user->points_balance,
                ],
            ], 422);
        }

        // 先設 pass，再扣點（避免扣了點 save 失敗的孤兒交易）
        $passUntil = now()->addHours($durationHours);
        $user->forceFill(['details_pass_until' => $passUntil])->save();

        $this->pointService->consume(
            $user,
            $cost,
            'profile_details',
            "啟用詳細資料 {$durationHours} 小時通行證",
        );

        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => '詳細資料通行證已啟用',
            'data' => [
                'details_pass_until' => $passUntil->toISOString(),
                'duration_hours' => $durationHours,
                'points_deducted' => $cost,
                'points_balance' => (int) $user->points_balance,
            ],
        ]);
    }
}
