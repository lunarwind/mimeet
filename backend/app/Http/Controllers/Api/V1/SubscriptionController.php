<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    /**
     * Get available subscription plans.
     */
    public function plans(): JsonResponse
    {
        $plans = [
            [
                'id' => 'plan_weekly',
                'name' => '週方案',
                'duration_days' => 7,
                'price' => 199,
                'currency' => 'TWD',
                'membership_level' => 2,
                'features' => ['無限訊息', '查看誰瀏覽過你', '進階搜尋'],
                'is_popular' => false,
            ],
            [
                'id' => 'plan_monthly',
                'name' => '月方案',
                'duration_days' => 30,
                'price' => 599,
                'currency' => 'TWD',
                'membership_level' => 2,
                'features' => ['無限訊息', '查看誰瀏覽過你', '進階搜尋', '優先曝光'],
                'is_popular' => true,
            ],
            [
                'id' => 'plan_quarterly',
                'name' => '季方案',
                'duration_days' => 90,
                'price' => 1499,
                'currency' => 'TWD',
                'membership_level' => 2,
                'features' => ['無限訊息', '查看誰瀏覽過你', '進階搜尋', '優先曝光', '專屬徽章'],
                'is_popular' => false,
            ],
            [
                'id' => 'plan_yearly',
                'name' => '年方案',
                'duration_days' => 365,
                'price' => 4999,
                'currency' => 'TWD',
                'membership_level' => 2,
                'features' => ['無限訊息', '查看誰瀏覽過你', '進階搜尋', '優先曝光', '專屬徽章', 'VIP 客服'],
                'is_popular' => false,
            ],
        ];

        $trial = [
            'id' => 'plan_trial',
            'name' => '體驗方案',
            'duration_days' => 3,
            'price' => 49,
            'currency' => 'TWD',
            'membership_level' => 2,
            'features' => ['無限訊息', '進階搜尋'],
            'is_trial' => true,
            'limit_per_user' => 1,
        ];

        return response()->json([
            'success' => true,
            'code' => 'PLANS_LIST',
            'message' => 'OK',
            'data' => [
                'plans' => $plans,
                'trial' => $trial,
            ],
        ]);
    }

    /**
     * Get current user's subscription.
     */
    public function mySubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        // Mock: return null subscription for free users
        $subscription = null;

        if ($user && (int) $user->membership_level >= 1) {
            $subscription = [
                'id' => 'sub_' . Str::random(12),
                'plan_id' => 'plan_monthly',
                'plan_name' => '月方案',
                'status' => 'active',
                'auto_renew' => true,
                'started_at' => now()->subDays(15)->toISOString(),
                'expires_at' => now()->addDays(15)->toISOString(),
                'membership_level' => 2,
            ];
        }

        return response()->json([
            'success' => true,
            'code' => 'MY_SUBSCRIPTION',
            'message' => 'OK',
            'data' => [
                'subscription' => $subscription,
            ],
        ]);
    }

    /**
     * Create a subscription order (returns payment URL).
     */
    public function createOrder(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|string',
            'payment_method' => 'sometimes|string|in:credit_card,atm,cvs',
        ]);

        $orderId = 'order_' . Str::random(16);

        return response()->json([
            'success' => true,
            'code' => 'ORDER_CREATED',
            'message' => '訂單已建立。',
            'data' => [
                'order_id' => $orderId,
                'payment_url' => 'https://payment-sandbox.ecpay.com.tw/mock/' . $orderId,
                'expires_at' => now()->addMinutes(30)->toISOString(),
            ],
        ], 201);
    }

    /**
     * Update subscription (toggle auto_renew).
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'auto_renew' => 'required|boolean',
        ]);

        return response()->json([
            'success' => true,
            'code' => 'SUBSCRIPTION_UPDATED',
            'message' => $request->boolean('auto_renew')
                ? '已開啟自動續約。'
                : '已關閉自動續約。',
            'data' => [
                'auto_renew' => $request->boolean('auto_renew'),
            ],
        ]);
    }

    /**
     * Request subscription cancellation (creates a support ticket).
     */
    public function cancelRequest(Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'sometimes|string|max:500',
        ]);

        return response()->json([
            'success' => true,
            'code' => 'CANCEL_REQUEST_CREATED',
            'message' => '取消訂閱申請已提交，客服將於 24 小時內處理。',
            'data' => [
                'ticket_id' => 'ticket_' . Str::random(12),
            ],
        ]);
    }

    /**
     * Get trial plan info.
     */
    public function trial(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'TRIAL_INFO',
            'message' => 'OK',
            'data' => [
                'plan' => [
                    'id' => 'plan_trial',
                    'name' => '體驗方案',
                    'duration_days' => 3,
                    'price' => 49,
                    'currency' => 'TWD',
                    'membership_level' => 2,
                    'features' => ['無限訊息', '進階搜尋'],
                ],
                'eligible' => true,
            ],
        ]);
    }

    /**
     * Purchase trial plan.
     */
    public function trialPurchase(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method' => 'sometimes|string|in:credit_card,atm,cvs',
        ]);

        $orderId = 'order_trial_' . Str::random(12);

        return response()->json([
            'success' => true,
            'code' => 'TRIAL_ORDER_CREATED',
            'message' => '體驗方案訂單已建立。',
            'data' => [
                'order_id' => $orderId,
                'payment_url' => 'https://payment-sandbox.ecpay.com.tw/mock/' . $orderId,
                'expires_at' => now()->addMinutes(30)->toISOString(),
            ],
        ], 201);
    }
}
