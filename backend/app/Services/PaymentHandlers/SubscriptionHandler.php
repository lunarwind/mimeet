<?php

namespace App\Services\PaymentHandlers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Log;

/**
 * 會員購買付款成功後的業務處理。
 *
 * 決策：直接呼叫既有 PaymentService::activateSubscription(Order $order)，
 * 不重寫一份。activateSubscription() 已包含：
 *  - orders.status = 'paid', paid_at 寫入
 *  - 停用舊訂閱、建立新 Subscription 紀錄
 *  - 更新 users.membership_level
 *  - 發送訂閱啟用通知
 */
class SubscriptionHandler
{
    public function __construct(private PaymentService $paymentService) {}

    public function onPaid(Payment $payment): void
    {
        // reference_id → orders.id
        $order = Order::find($payment->reference_id);

        if (!$order) {
            Log::error('[SubscriptionHandler] Order not found', [
                'payment_id'   => $payment->id,
                'reference_id' => $payment->reference_id,
            ]);
            return;
        }

        // 冪等：既有邏輯 activateSubscription 裡面沒有重複 guard，
        // 但 handleCallback 的 payment.status==='paid' 已擋住重送，
        // 這裡額外用 order.status 雙保險。
        if ($order->status === 'paid') {
            Log::info('[SubscriptionHandler] Order already paid, skip', ['order_id' => $order->id]);
            return;
        }

        // 直接呼叫既有邏輯（包含 DB transaction + 通知）
        $this->paymentService->activateSubscription($order);

        Log::info('[SubscriptionHandler] Subscription activated', [
            'order_no'   => $order->order_number,
            'payment_id' => $payment->id,
        ]);
    }
}
