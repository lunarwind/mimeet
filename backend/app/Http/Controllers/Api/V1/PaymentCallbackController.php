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
        // Redirect user back to frontend
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        return response(
            '<html><head><meta http-equiv="refresh" content="0;url=' . $frontendUrl . '/#/app/shop?payment=complete"></head></html>',
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
    public function mock(Request $request): \Illuminate\Http\JsonResponse
    {
        if (config('app.env') === 'production') {
            abort(404);
        }

        $tradeNo = $request->query('trade_no');
        $order = $this->paymentService->handleMockPayment($tradeNo);

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '模擬付款成功',
            'data' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
            ],
        ]);
    }
}
