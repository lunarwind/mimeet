<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PointOrder;
use App\Models\PointPackage;
use App\Services\PointService;
use App\Services\UnifiedPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * F40 前台點數 API
 *  GET  /points/packages — 列出啟用方案
 *  POST /points/purchase — 產生訂單 + 回傳 ECPay AIO 參數
 *  GET  /points/balance  — 餘額
 *  GET  /points/history  — 交易紀錄（分頁）
 */
class PointController extends Controller
{
    public function __construct(
        private readonly PointService          $pointService,
        private readonly UnifiedPaymentService $unifiedPayment,
    ) {}

    public function packages(): JsonResponse
    {
        $packages = PointPackage::active()->get()->map(fn (PointPackage $p) => [
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
        ]);

        return response()->json([
            'success' => true,
            'data' => $packages,
        ]);
    }

    public function purchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_slug' => 'required|string|exists:point_packages,slug',
            'payment_method' => 'sometimes|string|in:credit_card,atm,cvs',
        ]);

        $user = $request->user();
        $package = PointPackage::where('slug', $validated['package_slug'])
            ->where('is_active', true)
            ->firstOrFail();

        if ($package->price <= 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_PACKAGE', 'message' => '此方案未上架或無效'],
            ], 422);
        }

        // 產生訂單號（PTS + 14 碼日期 + 3 碼亂數 = 20 chars，符合 ECPay 限制）
        $tradeNo = $this->unifiedPayment->generateOrderNo('points');

        $order = PointOrder::create([
            'uuid'           => (string) Str::uuid(),
            'user_id'        => $user->id,
            'package_id'     => $package->id,
            'points'         => $package->total_points,
            'amount'         => $package->price,
            'payment_method' => $validated['payment_method'] ?? 'credit_card',
            'trade_no'       => $tradeNo,
            'status'         => 'pending',
        ]);

        $result = $this->unifiedPayment->initiate('points', $user, [
            'item_name'    => "MiMeet 點數 {$package->total_points} 點",
            'amount'       => $package->price,
            'reference_id' => $order->id,
        ]);

        return response()->json([
            'success' => true,
            'code'    => 201,
            'message' => '點數訂單已建立',
            'data'    => [
                'payment_id' => $result['payment']->id,
                'aio_url'    => $result['aio_url'],
                'params'     => $result['params'],
                // 保留向下相容欄位
                'order'      => [
                    'id'       => $order->id,
                    'trade_no' => $order->trade_no,
                    'points'   => $order->points,
                    'amount'   => $order->amount,
                    'status'   => $order->status,
                ],
            ],
        ], 201);
    }

    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'data' => [
                'points_balance' => $this->pointService->getBalance($user),
                'stealth_until' => $user->stealth_until?->toISOString(),
                'stealth_active' => $user->isStealthActive(),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $paginator = $this->pointService->getHistory($request->user(), min(max($perPage, 1), 50));

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $paginator->getCollection()->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount' => $t->amount,
                    'balance_after' => $t->balance_after,
                    'feature' => $t->feature,
                    'description' => $t->description,
                    'reference_id' => $t->reference_id,
                    'created_at' => $t->created_at?->toISOString(),
                ]),
            ],
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
