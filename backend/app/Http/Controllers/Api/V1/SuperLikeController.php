<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PointService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F40-c 超級讚
 *   POST /api/v1/users/{id}/super-like
 *
 * 規則：
 *  - 不能對自己發
 *  - 同一對象 24h 冷卻（以 notifications.data.sender_id 為準）
 *  - 扣 system_settings.point_cost_super_like 點（預設 3）
 *  - 建 super_like 通知給對方
 *  - 不限會員等級（任何人有點數就能發）
 */
class SuperLikeController extends Controller
{
    public function __construct(
        private readonly PointService $pointService,
        private readonly NotificationService $notificationService,
    ) {}

    public function store(Request $request, int $userId): JsonResponse
    {
        $sender = $request->user();

        if ($sender->id === $userId) {
            return response()->json([
                'success' => false,
                'code' => 422,
                'message' => '不能對自己發送超級讚',
            ], 422);
        }

        $receiver = User::find($userId);
        if (!$receiver || $receiver->status === 'deleted') {
            return response()->json([
                'success' => false,
                'code' => 404,
                'message' => '找不到此用戶',
            ], 404);
        }

        // 24h 冷卻：檢查同一對象的 super_like 通知
        $recent = Notification::where('user_id', $receiver->id)
            ->where('type', 'super_like')
            ->where('created_at', '>', now()->subHours(24))
            ->get()
            ->first(function (Notification $n) use ($sender) {
                $data = is_string($n->data) ? json_decode($n->data, true) : ($n->data ?? []);
                return ($data['sender_id'] ?? null) === $sender->id;
            });

        if ($recent) {
            return response()->json([
                'success' => false,
                'code' => 422,
                'message' => '24 小時內已對此用戶發送過超級讚',
                'data' => [
                    'next_available_at' => Carbon::parse($recent->created_at)->addHours(24)->toISOString(),
                ],
            ], 422);
        }

        // 扣點
        $cost = $this->pointService->getFeatureCost('super_like');
        if ($cost <= 0) $cost = 3; // 保險預設

        if (!$this->pointService->canAfford($sender, $cost)) {
            return response()->json([
                'success' => false,
                'code' => 422,
                'message' => "點數不足：需要 {$cost} 點，目前 " . (int) $sender->points_balance . ' 點',
                'data' => [
                    'required' => $cost,
                    'current_balance' => (int) $sender->points_balance,
                ],
            ], 422);
        }

        $this->pointService->consume(
            $sender,
            $cost,
            'super_like',
            "對 {$receiver->nickname} 發送超級讚",
            $receiver->id,
        );

        // 建立通知
        $this->notificationService->notify(
            $receiver,
            'super_like',
            "{$sender->nickname} 對你發送了超級讚 ⭐",
            '點擊查看對方資料',
            [
                'sender_id' => $sender->id,
                'sender_nickname' => $sender->nickname,
            ],
        );

        return response()->json([
            'success' => true,
            'data' => [
                'points_deducted' => $cost,
                'points_balance' => (int) $sender->fresh()->points_balance,
                'message' => '已送出超級讚',
            ],
        ]);
    }
}
