<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\UnifiedPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * 統一金流回調 Controller（Step 5）
 *
 * POST /api/v1/payments/callback  — 統一入口（新 ECPay NotifyURL）
 * GET  /api/v1/payments/return    — ECPay 前端 return，redirect 到前台結果頁
 */
class UnifiedPaymentController extends Controller
{
    public function __construct(private UnifiedPaymentService $service) {}

    /**
     * POST /api/v1/payments/callback
     * ECPay server-to-server notify（純文字回應）。
     */
    public function callback(Request $request): Response
    {
        $result = $this->service->handleCallback($request->all());
        return response($result, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * GET /api/v1/payments/{order_no}
     * 查詢 payment 狀態（前端結果頁 polling 用）。
     */
    public function show(Request $request, string $orderNo): JsonResponse
    {
        $payment = Payment::where('order_no', $orderNo)->first();

        if (!$payment) {
            return response()->json(['success' => false, 'code' => 'NOT_FOUND', 'message' => '找不到此訂單'], 404);
        }

        // 只有訂單擁有者可查
        $user = $request->user();
        if (!$user || $user->id !== $payment->user_id) {
            return response()->json(['success' => false, 'code' => 'FORBIDDEN'], 403);
        }

        // 依 type 附帶業務資料（用於結果頁顯示）
        $businessData = match ($payment->type) {
            'verification' => [
                'verified'   => !is_null($user->credit_card_verified_at),
                'verified_at'=> $user->credit_card_verified_at?->toISOString(),
            ],
            'subscription' => $this->subscriptionData($user),
            'points'       => ['balance' => (int) ($user->points_balance ?? 0)],
            default        => [],
        };

        return response()->json([
            'success' => true,
            'data'    => [
                'payment_id'    => $payment->id,
                'order_no'      => $payment->order_no,
                'type'          => $payment->type,
                'status'        => $payment->status,
                'amount'        => $payment->amount,
                'paid_at'       => $payment->paid_at?->toISOString(),
                'business_data' => $businessData,
            ],
        ]);
    }

    private function subscriptionData(\App\Models\User $user): array
    {
        $sub = \App\Models\Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderByDesc('expires_at')
            ->first();
        return $sub ? [
            'plan_name'  => $sub->plan?->name,
            'expires_at' => $sub->expires_at?->toISOString(),
        ] : [];
    }

    /**
     * GET /api/v1/payments/return
     * ECPay 瀏覽器導回 URL，redirect 到前台結果頁。
     */
    public function returnUrl(Request $request): \Illuminate\Http\RedirectResponse
    {
        $orderNo = $request->query('MerchantTradeNo', '');
        $rtnCode = $request->query('RtnCode', '0');
        $status  = $rtnCode === '1' ? 'success' : 'failed';

        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'https://mimeet.online'));
        return redirect("{$frontendUrl}/#/payment/result?order_no={$orderNo}&status={$status}");
    }
}
