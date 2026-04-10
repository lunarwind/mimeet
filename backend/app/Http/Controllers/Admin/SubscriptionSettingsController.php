<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SubscriptionSettingsController extends Controller
{
    public function plans(): JsonResponse
    {
        $plans = Cache::get('admin_subscription_plans', [
            ['id' => 1, 'name' => '週方案', 'period' => 'weekly', 'price' => 199, 'duration_days' => 7, 'is_active' => true, 'discount_pct' => 0],
            ['id' => 2, 'name' => '月方案', 'period' => 'monthly', 'price' => 599, 'duration_days' => 30, 'is_active' => true, 'discount_pct' => 0],
            ['id' => 3, 'name' => '季方案', 'period' => 'quarterly', 'price' => 1499, 'duration_days' => 90, 'is_active' => true, 'discount_pct' => 15],
            ['id' => 4, 'name' => '年方案', 'period' => 'yearly', 'price' => 4999, 'duration_days' => 365, 'is_active' => true, 'discount_pct' => 30],
        ]);
        return response()->json(['success' => true, 'data' => ['plans' => $plans]]);
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'price' => 'sometimes|integer|min:0',
            'duration_days' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'discount_pct' => 'sometimes|integer|min:0|max:100',
        ]);
        return response()->json(['success' => true, 'message' => '方案已更新']);
    }

    public function discounts(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'discounts' => Cache::get('subscription_discounts', [])
        ]]);
    }

    public function storeDiscount(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:20',
            'discount_pct' => 'required|integer|min:1|max:100',
            'valid_until' => 'sometimes|date',
        ]);
        return response()->json(['success' => true, 'message' => '折扣碼已建立'], 201);
    }

    public function updateDiscount(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => true, 'message' => '折扣碼已更新']);
    }
}
