<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F42 隱身模式
 *
 * 三個 endpoint：
 *   GET    /me/stealth — 查狀態 + 費用預覽
 *   POST   /me/stealth — 啟用隱身（Lv3 免費 / 非 Lv3 扣點）
 *   DELETE /me/stealth — 提前關閉（不退點）
 *
 * 設計原則（重要）：
 *  - stealth_until 與 privacy_settings.show_in_search 兩套機制獨立，
 *    任一為真就視為隱藏（搜尋列表過濾時用 OR 連接）。
 *  - 隱身疊加：已在隱身中再啟用時從 stealth_until 往後延，不重置。
 *  - 不改 PointService 內部邏輯、不改訂閱系統。
 */
class StealthController extends Controller
{
    public function __construct(
        private readonly PointService $pointService,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $durationHours = (int) SystemSetting::get('stealth_duration_hours', 24);
        $cost = (int) SystemSetting::get('point_cost_stealth', 10);
        $isVipFree = $user->membership_level >= 3;

        $isActive = $user->isStealthActive();
        $remaining = 0;
        if ($isActive && $user->stealth_until) {
            $remaining = max(0, (int) now()->diffInSeconds($user->stealth_until, false));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'is_active' => $isActive,
                'stealth_until' => $user->stealth_until?->toISOString(),
                'remaining_seconds' => $remaining,
                'remaining_display' => $this->formatRemaining($remaining),
                'is_vip_free' => $isVipFree,
                'cost' => $isVipFree ? 0 : $cost,
                'duration_hours' => $durationHours,
                'current_balance' => (int) $user->points_balance,
            ],
        ]);
    }

    public function activate(Request $request): JsonResponse
    {
        $user = $request->user();
        $durationHours = (int) SystemSetting::get('stealth_duration_hours', 24);
        $cost = (int) SystemSetting::get('point_cost_stealth', 10);
        $isVipFree = $user->membership_level >= 3;

        // 非 VIP 需要先檢查餘額
        if (!$isVipFree && !$this->pointService->canAfford($user, $cost)) {
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

        // 疊加：未到期時從既有 stealth_until 延長；否則從 now() 起算
        $baseTime = ($user->stealth_until && $user->stealth_until->isFuture())
            ? $user->stealth_until->copy()
            : now();
        $stealthUntil = $baseTime->addHours($durationHours);

        $user->forceFill(['stealth_until' => $stealthUntil])->save();

        // 非 VIP 才扣點（在 update stealth_until 後扣，避免扣了點失敗）
        $pointsDeducted = 0;
        if (!$isVipFree) {
            $this->pointService->consume(
                $user,
                $cost,
                'stealth',
                "啟用隱身模式 {$durationHours} 小時",
            );
            $pointsDeducted = $cost;
        }

        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => $isVipFree ? 'VIP 會員免費啟用隱身' : '隱身模式已啟用',
            'data' => [
                'is_active' => true,
                'stealth_until' => $stealthUntil->toISOString(),
                'points_deducted' => $pointsDeducted,
                'points_balance' => (int) $user->points_balance,
                'is_vip_free' => $isVipFree,
            ],
        ]);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isStealthActive()) {
            return response()->json([
                'success' => true,
                'message' => '目前未啟用隱身',
                'data' => ['is_active' => false, 'stealth_until' => null],
            ]);
        }

        $user->forceFill(['stealth_until' => null])->save();

        return response()->json([
            'success' => true,
            'message' => '已提前關閉隱身（不退點）',
            'data' => [
                'is_active' => false,
                'stealth_until' => null,
            ],
        ]);
    }

    private function formatRemaining(int $seconds): string
    {
        if ($seconds <= 0) return '00:00:00';
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}
