<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\PaymentService;
use App\Services\UnifiedPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PaymentService        $paymentService,
        private readonly UnifiedPaymentService $unifiedPayment,
    ) {}

    /**
     * GET /api/v1/subscriptions/plans
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->where('is_trial', false)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->slug,
                'name' => $p->name,
                'duration_days' => $p->duration_days,
                'price' => $p->price,
                'currency' => $p->currency,
                'membership_level' => $p->membership_level,
                'features' => $p->features,
            ]);

        $trial = SubscriptionPlan::where('is_active', true)
            ->where('is_trial', true)
            ->first();

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => 'OK',
            'data' => [
                'plans' => $plans,
                'trial' => $trial ? [
                    'id' => $trial->slug,
                    'name' => $trial->name,
                    'duration_days' => $trial->duration_days,
                    'price' => $trial->price,
                    'currency' => $trial->currency,
                    'features' => $trial->features,
                    'is_trial' => true,
                ] : null,
            ],
        ]);
    }

    /**
     * GET /api/v1/subscriptions/me
     */
    public function mySubscription(Request $request): JsonResponse
    {
        $subscription = $this->paymentService->getActiveSubscription($request->user());

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => 'OK',
            'data' => ['subscription' => $subscription],
        ]);
    }

    /**
     * POST /api/v1/subscriptions/orders
     */
    public function createOrder(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id'        => 'required|string',
            'payment_method' => 'sometimes|string|in:credit_card,atm,cvs',
        ]);

        $user   = $request->user();
        $planId = $request->input('plan_id');
        $method = $request->input('payment_method', 'credit_card');

        try {
            $orderNo = $this->unifiedPayment->generateOrderNo('subscription');
            $order   = $this->paymentService->createOrderRecord($user, $planId, $orderNo, $method);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'TRIAL_ALREADY_USED') {
                return response()->json([
                    'success' => false,
                    'code'    => 422,
                    'message' => '您已使用過體驗方案',
                ], 422);
            }
            throw $e;
        }

        $plan   = $order->plan;
        $result = $this->unifiedPayment->initiate('subscription', $user, [
            'item_name'    => "MiMeet {$plan->name}",
            'amount'       => $order->amount,
            'reference_id' => $order->id,
        ]);

        return response()->json([
            'success' => true,
            'code'    => 201,
            'message' => '訂單已建立',
            'data'    => [
                'payment_id' => $result['payment']->id,
                'aio_url'    => $result['aio_url'],
                'params'     => $result['params'],
                // 保留向下相容欄位
                'order'      => [
                    'id'           => $order->id,
                    'order_number' => $order->order_number,
                    'amount'       => $order->amount,
                    'status'       => $order->status,
                    'expires_at'   => $order->expires_at->toISOString(),
                ],
            ],
        ], 201);
    }

    /**
     * PATCH /api/v1/subscriptions/me — toggle auto_renew
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'auto_renew' => 'required|boolean',
        ]);

        $sub = Subscription::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (!$sub) {
            return response()->json([
                'success' => false,
                'code' => 404,
                'message' => '目前沒有有效訂閱',
            ], 404);
        }

        $sub->update(['auto_renew' => $request->boolean('auto_renew')]);

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => $request->boolean('auto_renew') ? '已開啟自動續約' : '已關閉自動續約',
            'data' => ['auto_renew' => $sub->auto_renew],
        ]);
    }

    /**
     * POST /api/v1/subscriptions/cancel-request
     */
    public function cancelRequest(Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'sometimes|string|max:500',
        ]);

        $sub = Subscription::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if ($sub) {
            $sub->update(['auto_renew' => false]);
        }

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '取消訂閱申請已提交',
        ]);
    }

    /**
     * GET /api/v1/subscription/trial
     */
    public function trial(Request $request): JsonResponse
    {
        $plan = SubscriptionPlan::where('is_trial', true)->where('is_active', true)->first();

        $eligible = $plan && !\App\Models\Order::where('user_id', $request->user()->id)
            ->whereHas('plan', fn ($q) => $q->where('is_trial', true))
            ->where('status', 'paid')
            ->exists();

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => 'OK',
            'data' => [
                'trial_available' => $plan !== null,
                'is_eligible'     => $eligible,
                'plan'            => $plan ? [
                    'id'            => $plan->slug,
                    'name'          => $plan->name,
                    'duration_days' => $plan->duration_days,
                    'price'         => $plan->price,
                    'currency'      => $plan->currency,
                    'features'      => $plan->features,
                ] : null,
                'notice' => '每位會員限購一次，購買後不可退款，不自動續費',
            ],
        ]);
    }

    /**
     * POST /api/v1/subscription/trial/purchase
     */
    public function trialPurchase(Request $request): JsonResponse
    {
        $user      = $request->user();
        $trialPlan = SubscriptionPlan::where('is_trial', true)->where('is_active', true)->firstOrFail();

        try {
            $orderNo = $this->unifiedPayment->generateOrderNo('subscription');
            $order   = $this->paymentService->createOrderRecord($user, $trialPlan->slug, $orderNo);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'TRIAL_ALREADY_USED') {
                return response()->json([
                    'success' => false,
                    'code'    => 422,
                    'message' => '您已使用過體驗方案',
                ], 422);
            }
            throw $e;
        }

        $result = $this->unifiedPayment->initiate('subscription', $user, [
            'item_name'    => "MiMeet {$trialPlan->name}",
            'amount'       => $order->amount,
            'reference_id' => $order->id,
        ]);

        return response()->json([
            'success' => true,
            'code'    => 201,
            'message' => '體驗方案訂單已建立',
            'data'    => [
                'payment_id' => $result['payment']->id,
                'aio_url'    => $result['aio_url'],
                'params'     => $result['params'],
            ],
        ], 201);
    }
}
