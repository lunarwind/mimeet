<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\UnifiedPaymentService;
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
