<?php

namespace App\Services\PaymentHandlers;

use App\Models\Payment;
use App\Models\PointOrder;
use App\Services\PaymentService;
use App\Services\PointService;
use Illuminate\Support\Facades\Log;

class PointsHandler
{
    public function __construct(
        private PointService   $pointService,
        private PaymentService $paymentService,
    ) {}

    public function onPaid(Payment $payment): void
    {
        $pointOrder = PointOrder::find($payment->reference_id);

        if (!$pointOrder) {
            Log::error('[PointsHandler] PointOrder not found', [
                'payment_id'   => $payment->id,
                'reference_id' => $payment->reference_id,
            ]);
            return;
        }

        if ($pointOrder->status === 'paid') {
            Log::info('[PointsHandler] PointOrder already paid, skip', ['order_id' => $pointOrder->id]);
            return;
        }

        $user = $pointOrder->user;
        if (!$user) {
            Log::error('[PointsHandler] User not found for point_order', ['order_id' => $pointOrder->id]);
            return;
        }

        $pointOrder->update([
            'status'  => 'paid',
            'paid_at' => $payment->paid_at ?? now(),
        ]);

        $this->pointService->credit(
            $user,
            $pointOrder->points,
            'purchase',
            "購買點數：{$pointOrder->points} 點（訂單 {$pointOrder->trade_no}）",
            $pointOrder->id,
        );

        // 開立電子發票（單寫 payments SSOT）
        $this->paymentService->issueInvoiceForPointOrder($pointOrder->fresh());

        Log::info('[PointsHandler] Points credited', [
            'user_id'    => $user->id,
            'points'     => $pointOrder->points,
            'trade_no'   => $pointOrder->trade_no,
            'payment_id' => $payment->id,
        ]);
    }
}
