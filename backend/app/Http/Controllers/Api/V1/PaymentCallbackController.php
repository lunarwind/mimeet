<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PointOrder;
use App\Services\PaymentService;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PointService $pointService,
    ) {}

    /**
     * POST /api/v1/payments/ecpay/notify — ECPay server-to-server callback
     */
    public function notify(Request $request): Response
    {
        $result = $this->paymentService->handleECPayNotify($request->all());
        return response($result, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * GET /api/v1/payments/ecpay/return — ECPay browser redirect after payment
     */
    public function returnUrl(Request $request): Response
    {
        $frontendUrl = rtrim(
            config('app.frontend_url', env('FRONTEND_URL', 'https://mimeet.online')),
            '/',
        );
        $redirectUrl = $frontendUrl . '/#/app/shop?payment=complete';

        return response(
            '<html><head><meta charset="utf-8">'
            . '<meta http-equiv="refresh" content="0;url=' . e($redirectUrl) . '">'
            . '</head><body><p>正在跳轉...</p>'
            . '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>'
            . '</body></html>',
            200,
        )->header('Content-Type', 'text/html');
    }

    /**
     * GET /api/v1/payments/ecpay/checkout/{token} — Serve auto-submit form to ECPay
     */
    public function checkout(string $token): Response
    {
        $html = cache()->pull("ecpay_form:{$token}");

        if (!$html) {
            return response('Payment session expired. Please try again.', 410)
                ->header('Content-Type', 'text/plain');
        }

        return response($html, 200)->header('Content-Type', 'text/html');
    }

    /**
     * GET /api/v1/payments/ecpay/mock — Sandbox mock payment (dev only)
     *
     * Two-step flow:
     *   Step 1 (no confirm param): show simulated credit card input page
     *   Step 2 (?confirm=1):       process payment and redirect to frontend
     */
    public function mock(Request $request): mixed
    {
        if (config('app.env') === 'production') {
            abort(404);
        }

        $tradeNo = $request->query('trade_no');
        $amount  = $request->query('amount', 0);
        $confirm = $request->query('confirm', '0');

        // JSON callers (tests) skip the UI and process immediately
        if ($request->expectsJson()) {
            $order = $this->paymentService->handleMockPayment($tradeNo);
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $order->status,
                    'order_number' => $order->order_number,
                ],
            ]);
        }

        $frontendUrl = rtrim(
            config('app.frontend_url', env('FRONTEND_URL', 'https://mimeet.online')),
            '/',
        );

        // ── Step 2: user confirmed → process payment → redirect ──
        if ($confirm === '1') {
            try {
                $this->paymentService->handleMockPayment($tradeNo);
                $status = 'success';
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('[ECPay Mock] Failed', ['error' => $e->getMessage()]);
                $status = 'failed';
            }

            $redirectUrl = $frontendUrl . '/#/app/shop?payment=' . $status;

            return response(
                '<html><head><meta charset="utf-8">'
                . '<meta http-equiv="refresh" content="0;url=' . e($redirectUrl) . '">'
                . '</head><body><p>正在跳轉...</p>'
                . '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>'
                . '</body></html>',
                200,
            )->header('Content-Type', 'text/html');
        }

        // ── Step 1: show simulated credit card input page ──
        $confirmUrl = url("/api/v1/payments/ecpay/mock")
            . "?trade_no=" . urlencode($tradeNo) . "&amount={$amount}&confirm=1";
        $cancelUrl = $frontendUrl . '/#/app/shop?payment=cancelled';

        return response(
            view('ecpay-mock', compact('amount', 'confirmUrl', 'cancelUrl'))->render(),
            200,
        )->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * GET /api/v1/payments/ecpay/point-mock — F40 點數訂單 mock 付款
     *
     * Step 1 (無 confirm)：顯示模擬付款頁
     * Step 2 (?confirm=1)：標記 paid + 點數入帳 + 導回 shop
     */
    public function pointMock(Request $request): mixed
    {
        if (config('app.env') === 'production') {
            abort(404);
        }

        $tradeNo = (string) $request->query('trade_no');
        $amount  = (int) $request->query('amount', 0);
        $confirm = (string) $request->query('confirm', '0');

        $frontendUrl = rtrim(
            config('app.frontend_url', env('FRONTEND_URL', 'https://mimeet.online')),
            '/',
        );

        // JSON callers (tests) 直接處理
        if ($request->expectsJson()) {
            $order = $this->processPointPayment($tradeNo);
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $order->status,
                    'trade_no' => $order->trade_no,
                    'points' => $order->points,
                ],
            ]);
        }

        // Step 2：確認付款 → 入帳 → 跳轉
        if ($confirm === '1') {
            try {
                $this->processPointPayment($tradeNo);
                $status = 'success';
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('[ECPay PointMock] Failed', [
                    'trade_no' => $tradeNo, 'error' => $e->getMessage(),
                ]);
                $status = 'failed';
            }

            $redirectUrl = $frontendUrl . '/#/app/shop?tab=points&payment=' . $status;

            return response(
                '<html><head><meta charset="utf-8">'
                . '<meta http-equiv="refresh" content="0;url=' . e($redirectUrl) . '">'
                . '</head><body><p>正在跳轉...</p>'
                . '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>'
                . '</body></html>',
                200,
            )->header('Content-Type', 'text/html');
        }

        // Step 1：顯示模擬付款頁
        $confirmUrl = url('/api/v1/payments/ecpay/point-mock')
            . '?trade_no=' . urlencode($tradeNo) . '&amount=' . $amount . '&confirm=1';
        $cancelUrl = $frontendUrl . '/#/app/shop?tab=points&payment=cancelled';

        return response(
            view('ecpay-mock', compact('amount', 'confirmUrl', 'cancelUrl'))->render(),
            200,
        )->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * 處理點數訂單付款完成：order.status=paid + 點數入帳（冪等）
     */
    private function processPointPayment(string $tradeNo): PointOrder
    {
        $order = PointOrder::where('trade_no', $tradeNo)->firstOrFail();

        if ($order->status === 'paid') {
            return $order; // 冪等：已付款過就直接回
        }

        $order->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // 點數入帳（以 order.id 為 reference_id）
        $user = $order->user()->first();
        if ($user) {
            $this->pointService->credit(
                $user,
                $order->points,
                'purchase',
                "購買點數：{$order->points} 點（訂單 {$order->trade_no}）",
                $order->id,
            );
        }

        return $order->fresh();
    }
}
