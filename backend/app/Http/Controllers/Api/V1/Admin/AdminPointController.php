<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminOperationLog;
use App\Models\PointOrder;
use App\Models\PointPackage;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 後台點數管理：方案 CRUD、管理員贈扣點、交易紀錄查詢
 */
class AdminPointController extends Controller
{
    public function __construct(
        private readonly PointService $pointService,
    ) {}

    public function packages(): JsonResponse
    {
        $packages = PointPackage::orderBy('sort_order')->get();
        return response()->json([
            'success' => true,
            'data' => $packages->map(fn (PointPackage $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'points' => $p->points,
                'bonus_points' => $p->bonus_points,
                'total_points' => $p->total_points,
                'price' => $p->price,
                'cost_per_point' => $p->cost_per_point,
                'description' => $p->description,
                'sort_order' => $p->sort_order,
                'is_active' => $p->is_active,
            ])->values(),
        ]);
    }

    public function updatePackage(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:50',
            'points' => 'sometimes|integer|min:0',
            'bonus_points' => 'sometimes|integer|min:0',
            'price' => 'sometimes|integer|min:0',
            'description' => 'sometimes|nullable|string|max:200',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $pkg = PointPackage::findOrFail($id);
        $before = $pkg->only(array_keys($validated));
        $pkg->update($validated);

        AdminOperationLog::create([
            'admin_id' => $request->user()?->id,
            'action' => 'update_point_package',
            'resource_type' => 'point_package',
            'resource_id' => $id,
            'description' => "更新點數方案 {$pkg->slug}",
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'request_summary' => ['before' => $before, 'after' => $validated],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '方案已更新',
            'data' => $pkg->fresh(),
        ]);
    }

    public function adjustPoints(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:gift,deduct',
            'amount' => 'required|integer|min:1|max:10000',
            'reason' => 'required|string|max:200',
        ]);

        $user = User::findOrFail($id);

        if ($validated['type'] === 'gift') {
            $txn = $this->pointService->credit(
                $user, $validated['amount'], 'admin_gift',
                '管理員贈送：' . $validated['reason'],
            );
        } else {
            // deduct — 用 adminAdjust 的負值路徑（會自動 clamp 到 0）
            try {
                $txn = $this->pointService->adminAdjust(
                    $user, -$validated['amount'],
                    '管理員扣除：' . $validated['reason'],
                );
            } catch (\App\Exceptions\InsufficientPointsException $e) {
                return response()->json([
                    'success' => false,
                    'code' => 'INSUFFICIENT_POINTS',
                    'message' => '用戶餘額為 0，無法扣除',
                ], 422);
            }
        }

        AdminOperationLog::create([
            'admin_id' => $request->user()?->id,
            'action' => 'admin_adjust_points',
            'resource_type' => 'user',
            'resource_id' => $id,
            'description' => ($validated['type'] === 'gift' ? '贈送' : '扣除')
                . " {$validated['amount']} 點：{$validated['reason']}",
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'request_summary' => $validated,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => ($validated['type'] === 'gift' ? '已贈送 ' : '已扣除 ') . $validated['amount'] . ' 點',
            'data' => [
                'transaction_id' => $txn->id,
                'points_balance' => (int) $user->fresh()->points_balance,
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'sometimes|integer',
            'nickname' => 'sometimes|string',
            'type' => 'sometimes|string|in:purchase,consume,refund,admin_gift,admin_deduct',
            'feature' => 'sometimes|string|in:stealth,super_like,reverse_msg,broadcast',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = PointTransaction::query()->with(['user:id,nickname,email']);

        if (!empty($validated['user_id'])) $query->where('user_id', $validated['user_id']);
        if (!empty($validated['nickname'])) {
            $nick = $validated['nickname'];
            $query->whereHas('user', fn ($q) => $q->where('nickname', 'LIKE', "%{$nick}%"));
        }
        if (!empty($validated['type'])) $query->where('type', $validated['type']);
        if (!empty($validated['feature'])) $query->where('feature', $validated['feature']);
        if (!empty($validated['date_from'])) $query->whereDate('created_at', '>=', $validated['date_from']);
        if (!empty($validated['date_to'])) $query->whereDate('created_at', '<=', $validated['date_to']);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginator->getCollection()->map(fn (PointTransaction $t) => [
                'id' => $t->id,
                'user' => [
                    'id' => $t->user?->id,
                    'nickname' => $t->user?->nickname,
                    'email' => $t->user?->email,
                ],
                'type' => $t->type,
                'amount' => $t->amount,
                'balance_after' => $t->balance_after,
                'feature' => $t->feature,
                'description' => $t->description,
                'reference_id' => $t->reference_id,
                'created_at' => $t->created_at?->toISOString(),
            ])->values(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
