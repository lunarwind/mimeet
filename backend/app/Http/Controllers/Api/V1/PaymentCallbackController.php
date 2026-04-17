<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
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
     */
    public function mock(Request $request): mixed
    {
        if (config('app.env') === 'production') {
            abort(404);
        }

        $tradeNo = $request->query('trade_no');

        // Return JSON when the client expects it (e.g. tests)
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

        // Browser flow: process payment then redirect back to frontend
        try {
            $this->paymentService->handleMockPayment($tradeNo);
            $status = 'success';
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[ECPay Mock] Failed', ['error' => $e->getMessage()]);
            $status = 'failed';
        }

        $frontendUrl = rtrim(
            config('app.frontend_url', env('FRONTEND_URL', 'https://mimeet.online')),
            '/',
        );
        $redirectUrl = $frontendUrl . '/#/app/shop?payment=' . $status;

        // Use HTML meta refresh + JS to handle hash-mode routing correctly
        return response(
            '<html><head><meta charset="utf-8">'
            . '<meta http-equiv="refresh" content="0;url=' . e($redirectUrl) . '">'
            . '</head><body><p>正在跳轉...</p>'
            . '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>'
            . '</body></html>',
            200,
        )->header('Content-Type', 'text/html');
    }
}
