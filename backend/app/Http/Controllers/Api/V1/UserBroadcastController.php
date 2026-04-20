<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessUserBroadcast;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserBroadcast;
use App\Services\PointService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * F41 用戶廣播
 *   POST /broadcasts/preview — 預覽篩選結果 + 費用
 *   POST /broadcasts/send    — 確認發送（扣點 + Queue Job）
 *   GET  /broadcasts/my      — 我的廣播歷史
 */
class UserBroadcastController extends Controller
{
    public function __construct(
        private readonly PointService $pointService,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $validated = $this->validateRequest($request);
        $sender = $request->user();

        $maxRecipients = (int) SystemSetting::get('broadcast_user_max_recipients', 50);
        $dailyLimit = (int) SystemSetting::get('broadcast_user_daily_limit', 1);
        $costPerUser = (int) SystemSetting::get('point_cost_broadcast_per_user', 2);

        $dailyUsed = UserBroadcast::where('sender_id', $sender->id)
            ->whereIn('status', ['sending', 'completed'])
            ->whereDate('created_at', today())
            ->count();

        $recipients = $this->buildRecipientQuery($sender, $validated['filters'] ?? [])
            ->limit($maxRecipients)
            ->count();

        $totalCost = $recipients * $costPerUser;
        $currentBalance = (int) $sender->points_balance;

        return response()->json([
            'success' => true,
            'data' => [
                'recipient_count' => $recipients,
                'cost_per_user' => $costPerUser,
                'total_cost' => $totalCost,
                'current_balance' => $currentBalance,
                'can_afford' => $currentBalance >= $totalCost,
                'balance_after' => max(0, $currentBalance - $totalCost),
                'max_recipients' => $maxRecipients,
                'daily_limit' => $dailyLimit,
                'daily_used' => $dailyUsed,
                'daily_remaining' => max(0, $dailyLimit - $dailyUsed),
            ],
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $this->validateRequest($request);
        $sender = $request->user();

        $maxRecipients = (int) SystemSetting::get('broadcast_user_max_recipients', 50);
        $dailyLimit = (int) SystemSetting::get('broadcast_user_daily_limit', 1);
        $costPerUser = (int) SystemSetting::get('point_cost_broadcast_per_user', 2);

        // 每日上限
        $dailyUsed = UserBroadcast::where('sender_id', $sender->id)
            ->whereIn('status', ['sending', 'completed'])
            ->whereDate('created_at', today())
            ->count();

        if ($dailyUsed >= $dailyLimit) {
            return response()->json([
                'success' => false,
                'code' => 'DAILY_LIMIT_EXCEEDED',
                'message' => "每日廣播次數已達上限（{$dailyLimit} 次）",
                'data' => ['daily_limit' => $dailyLimit, 'daily_used' => $dailyUsed],
            ], 422);
        }

        // 查符合條件的用戶
        $recipientIds = $this->buildRecipientQuery($sender, $validated['filters'] ?? [])
            ->limit($maxRecipients)
            ->pluck('id')
            ->all();

        $recipientCount = count($recipientIds);
        if ($recipientCount === 0) {
            return response()->json([
                'success' => false,
                'code' => 'NO_RECIPIENTS',
                'message' => '沒有符合條件的對象',
            ], 422);
        }

        $totalCost = $recipientCount * $costPerUser;

        // 檢查餘額
        if (!$this->pointService->canAfford($sender, $totalCost)) {
            return response()->json([
                'success' => false,
                'code' => 'INSUFFICIENT_POINTS',
                'message' => "點數不足：需要 {$totalCost} 點，目前 " . (int) $sender->points_balance . ' 點',
                'data' => [
                    'required' => $totalCost,
                    'current_balance' => (int) $sender->points_balance,
                    'recipient_count' => $recipientCount,
                ],
            ], 422);
        }

        // 建立 broadcast 記錄
        $broadcast = UserBroadcast::create([
            'uuid' => (string) Str::uuid(),
            'sender_id' => $sender->id,
            'content' => $validated['content'],
            'filters' => $validated['filters'] ?? [],
            'recipient_count' => $recipientCount,
            'points_spent' => $totalCost,
            'status' => 'sending',
        ]);

        // 扣點
        $this->pointService->consume(
            $sender, $totalCost, 'broadcast',
            "廣播給 {$recipientCount} 人",
            $broadcast->id,
        );

        // Dispatch Job（失敗則同步執行）
        try {
            ProcessUserBroadcast::dispatch($broadcast->id, $recipientIds);
        } catch (\Throwable $e) {
            (new ProcessUserBroadcast($broadcast->id, $recipientIds))->handle();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'broadcast_id' => $broadcast->id,
                'recipient_count' => $recipientCount,
                'points_spent' => $totalCost,
                'points_balance' => (int) $sender->fresh()->points_balance,
                'message' => '廣播已送出，正在發送中...',
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $paginator = UserBroadcast::where('sender_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $paginator->getCollection()->map(fn (UserBroadcast $b) => [
                'id' => $b->id,
                'content' => $b->content,
                'filters' => $b->filters,
                'recipient_count' => $b->recipient_count,
                'points_spent' => $b->points_spent,
                'status' => $b->status,
                'sent_at' => $b->sent_at?->toISOString(),
                'created_at' => $b->created_at?->toISOString(),
            ])->values(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'content' => 'required|string|max:200',
            'filters' => 'sometimes|nullable|array',
            'filters.gender' => 'sometimes|nullable|string|in:male,female',
            'filters.age_min' => 'sometimes|nullable|integer|min:18|max:99',
            'filters.age_max' => 'sometimes|nullable|integer|min:18|max:99',
            'filters.location' => 'sometimes|nullable|string|max:50',
            'filters.dating_budget' => 'sometimes|nullable|string|in:casual,moderate,generous,luxury,undisclosed',
            'filters.style' => 'sometimes|nullable|string|in:fresh,sweet,sexy,intellectual,sporty',
        ]);
    }

    /**
     * 建立收件人查詢（複用 UserController::search 的風格）
     */
    private function buildRecipientQuery(User $sender, array $filters): Builder
    {
        $query = User::where('status', 'active')
            ->where('id', '!=', $sender->id);

        // 排除隱身
        $query->where(function ($q) {
            $q->whereNull('stealth_until')->orWhere('stealth_until', '<=', now());
        });

        // 排除隱藏搜尋
        $query->where(function ($q) {
            $q->whereNull('privacy_settings')
              ->orWhereRaw("JSON_EXTRACT(privacy_settings, '$.show_in_search') != 'false'");
        });

        // 排除雙向封鎖
        $blockedIds = UserBlock::where('blocker_id', $sender->id)->pluck('blocked_id');
        $blockerIds = UserBlock::where('blocked_id', $sender->id)->pluck('blocker_id');
        $excludeIds = $blockedIds->merge($blockerIds)->unique();
        if ($excludeIds->isNotEmpty()) {
            $query->whereNotIn('id', $excludeIds);
        }

        // 篩選條件
        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }
        if (!empty($filters['age_min'])) {
            $maxDate = now()->subYears((int) $filters['age_min'])->toDateString();
            $query->where(function ($q) use ($maxDate) {
                $q->where('birth_date', '<=', $maxDate)->orWhereNull('birth_date');
            });
        }
        if (!empty($filters['age_max'])) {
            $minDate = now()->subYears(((int) $filters['age_max']) + 1)->toDateString();
            $query->where(function ($q) use ($minDate) {
                $q->where('birth_date', '>=', $minDate)->orWhereNull('birth_date');
            });
        }
        if (!empty($filters['location'])) {
            $query->where('location', 'like', "%{$filters['location']}%");
        }
        if (!empty($filters['dating_budget'])) {
            $query->where('dating_budget', $filters['dating_budget']);
        }
        if (!empty($filters['style'])) {
            $query->where('style', $filters['style']);
        }

        return $query;
    }
}
