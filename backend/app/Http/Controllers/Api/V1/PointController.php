<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PointOrder;
use App\Models\PointPackage;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * F40 前台點數 API
 *  GET  /points/packages — 列出啟用方案
 *  POST /points/purchase — 產生訂單 + 回傳綠界付款 URL
 *  GET  /points/balance  — 餘額
 *  GET  /points/history  — 交易紀錄（分頁）
 */
class PointController extends Controller
{
    public function __construct(
        private readonly PointService $pointService,
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

        // 產生內部訂單（trade_no 使用 PTS_ 前綴以和訂閱 SUB_ 區分）
        $tradeNo = 'PTS_' . date('YmdHis') . strtoupper(Str::random(6));

        $order = PointOrder::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'package_id' => $package->id,
            'points' => $package->total_points,
            'amount' => $package->price,
            'payment_method' => $validated['payment_method'] ?? 'credit_card',
            'trade_no' => $tradeNo,
            'status' => 'pending',
        ]);

        // Sandbox / 本地：走 mock 流程；正式：走真實 ECPay checkout
        $isSandbox = (string) \App\Models\SystemSetting::get('ecpay_is_sandbox', 'true') !== 'false';

        if ($isSandbox || app()->environment(['local', 'testing', 'staging'])) {
            $paymentUrl = url('/api/v1/payments/ecpay/point-mock')
                . '?trade_no=' . urlencode($tradeNo)
                . '&amount=' . $package->price;
        } else {
            // 真實 ECPay 流程留待 Phase 2 完整整合；目前 staging/production 都走 mock
            $paymentUrl = url('/api/v1/payments/ecpay/point-mock')
                . '?trade_no=' . urlencode($tradeNo)
                . '&amount=' . $package->price;
        }

        return response()->json([
            'success' => true,
            'code' => 201,
            'message' => '點數訂單已建立',
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'trade_no' => $order->trade_no,
                    'points' => $order->points,
                    'amount' => $order->amount,
                    'status' => $order->status,
                ],
                'payment_url' => $paymentUrl,
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
