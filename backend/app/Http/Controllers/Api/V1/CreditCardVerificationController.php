<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CreditCardVerification;
use App\Services\CreditCardVerificationService;
use App\Services\ECPayService;
use App\Services\UnifiedPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CreditCardVerificationController extends Controller
{
    public function __construct(
        private CreditCardVerificationService $service,
        private ECPayService                  $ecpay,
        private UnifiedPaymentService         $unifiedPayment,
    ) {}

    /**
     * POST /api/v1/verification/credit-card/initiate
     * 發起信用卡身份驗證，回傳 ECPay AIO 表單參數（前端直接 POST 到 ECPay）。
     *
     * 三道守門（必須全部通過才呼叫 UnifiedPaymentService）：
     *   1. 性別守門：僅限 gender=male
     *   2. 等級前置：membership_level >= 1（已完成手機驗證）
     *   3. 已驗證：credit_card_verified_at 為 null
     */
    public function initiate(Request $request): JsonResponse
    {
        $user = $request->user();

        // ── 守門 1：性別（僅限男性）──────────────────────────────────
        if ($user->gender !== 'male') {
            return response()->json(['success' => false, 'error' => [
                'code' => 'NOT_MALE', 'message' => '信用卡驗證為男性專屬功能',
            ]], 403);
        }

        // ── 守門 2：等級前置（須 Lv1 以上）──────────────────────────
        if (((float) $user->membership_level) < 1) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'LEVEL_REQUIRED', 'message' => '請先完成手機驗證（Lv1）才可發起信用卡驗證',
            ]], 422);
        }

        // ── 守門 3：已驗證防呆 ────────────────────────────────────────
        if ($user->credit_card_verified_at) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'ALREADY_VERIFIED', 'message' => '您已完成信用卡驗證',
            ]], 422);
        }

        // ── 建立 CreditCardVerification 業務紀錄 ─────────────────────
        $orderNo      = $this->unifiedPayment->generateOrderNo('verification');
        $verification = $this->service->createRecord($user, $orderNo);

        if (!$verification) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'ALREADY_VERIFIED', 'message' => '您已完成信用卡驗證',
            ]], 422);
        }

        // ── 呼叫統一金流發起 ──────────────────────────────────────────
        $result = $this->unifiedPayment->initiate('verification', $user, [
            'item_name'    => 'MiMeet 信用卡身份驗證（NT$100，3-5 工作日退還）',
            'amount'       => 100,
            'reference_id' => $verification->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'payment_id' => $result['payment']->id,
                'aio_url'    => $result['aio_url'],
                'params'     => $result['params'],
            ],
        ]);
    }

    /**
     * POST /api/v1/verification/credit-card/callback
     * ECPay server-to-server notify（alias → UnifiedPaymentService）。
     * 過渡期保留兩週，之後由 POST /api/v1/payments/callback 統一處理。
     */
    public function callback(Request $request): Response
    {
        // 直接委派給統一入口（驗簽在 UnifiedPaymentService 內部做）
        $result = $this->unifiedPayment->handleCallback($request->all());
        return response($result, 200)->header('Content-Type', 'text/plain');

        // ==== 舊邏輯保留作參考（已移至 UnifiedPaymentService::handleCallback） ====
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
