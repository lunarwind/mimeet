<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CreditCardVerification;
use App\Services\CreditCardVerificationService;
use App\Services\ECPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CreditCardVerificationController extends Controller
{
    public function __construct(
        private CreditCardVerificationService $service,
        private ECPayService $ecpay,
    ) {}

    /**
     * POST /api/v1/verification/credit-card/initiate
     * Start credit card verification. Returns payment URL.
     */
    public function initiate(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->gender !== 'male') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_MALE', 'message' => '僅限男性使用者'],
            ], 403);
        }

        if ($user->credit_card_verified_at) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ALREADY_VERIFIED', 'message' => '您已完成信用卡驗證'],
            ], 422);
        }

        $result = $this->service->initiate($user);
        if (!$result) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ALREADY_VERIFIED', 'message' => '您已完成信用卡驗證'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_no' => $result['order_no'],
                'payment_url' => $result['payment_url'],
            ],
        ]);
    }

    /**
     * POST /api/v1/verification/credit-card/callback
     * ECPay server-to-server notify.
     */
    public function callback(Request $request): Response
    {
        $data = $request->all();

        // Verify CheckMacValue
        if (!$this->ecpay->verifyCallback($data)) {
            return response('0|SignatureFailed', 400);
        }

        $ok = $this->service->processCallback($data);
        return response($ok ? '1|OK' : '0|Failed', 200);
    }

    /**
     * GET /api/v1/verification/credit-card/return
     * ECPay front-end return URL. Redirect to frontend result page.
     */
    public function returnUrl(Request $request): \Illuminate\Http\RedirectResponse
    {
        $rtnCode = $request->query('RtnCode', '');
        $orderNo = $request->query('MerchantTradeNo', '');
        $status = $rtnCode === '1' ? 'success' : 'failed';

        $frontendUrl = rtrim(config('app.frontend_url', 'https://mimeet.online'), '/');
        return redirect("{$frontendUrl}/#/app/settings/verify?credit_card={$status}&order={$orderNo}");
    }

    /**
     * GET /api/v1/verification/credit-card/status
     * Get current user's credit card verification status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $verification = CreditCardVerification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'verified' => !is_null($user->credit_card_verified_at),
                'verified_at' => $user->credit_card_verified_at?->toISOString(),
                'latest' => $verification ? [
                    'status' => $verification->status,
                    'created_at' => $verification->created_at?->toISOString(),
                ] : null,
            ],
        ]);
    }
}
