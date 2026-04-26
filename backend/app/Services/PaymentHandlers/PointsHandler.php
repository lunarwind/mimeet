<?php

namespace App\Services\PaymentHandlers;

use App\Models\Payment;
use App\Models\PointOrder;
use App\Services\PointService;
use Illuminate\Support\Facades\Log;

/**
 * 點數儲值付款成功後的業務處理。
 *
 * 決策：直接呼叫既有 PointService::credit()，不重寫。
 * PointService::credit() 已包含：
 *  - users.points_balance increment
 *  - PointTransaction 建立（type='purchase'）
 * PointOrder.status 由本 handler 更新為 'paid'。
 */
class PointsHandler
{
    public function __construct(private PointService $pointService) {}

    public function onPaid(Payment $payment): void
    {
        // reference_id → point_orders.id
        $pointOrder = PointOrder::find($payment->reference_id);

        if (!$pointOrder) {
            Log::error('[PointsHandler] PointOrder not found', [
                'payment_id'   => $payment->id,
                'reference_id' => $payment->reference_id,
            ]);
            return;
        }

        // 冪等：point_order.status==='paid' 代表已入帳，直接 return
        if ($pointOrder->status === 'paid') {
            Log::info('[PointsHandler] PointOrder already paid, skip', ['order_id' => $pointOrder->id]);
            return;
        }

        $user = $pointOrder->user;
        if (!$user) {
            Log::error('[PointsHandler] User not found for point_order', ['order_id' => $pointOrder->id]);
            return;
        }

        // 更新 point_order 狀態
        $pointOrder->update([
            'status'  => 'paid',
            'paid_at' => $payment->paid_at ?? now(),
        ]);

        // 直接呼叫既有 PointService::credit()（包含 PointTransaction 建立）
        $this->pointService->credit(
            $user,
            $pointOrder->points,
            'purchase',
            "購買點數：{$pointOrder->points} 點（訂單 {$pointOrder->trade_no}）",
            $pointOrder->id,
        );

        Log::info('[PointsHandler] Points credited', [
            'user_id'    => $user->id,
            'points'     => $pointOrder->points,
            'trade_no'   => $pointOrder->trade_no,
            'payment_id' => $payment->id,
        ]);
    }
}
