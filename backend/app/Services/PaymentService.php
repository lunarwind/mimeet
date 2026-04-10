<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly ECPayService $ecPayService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Create order and return payment URL.
     */
    public function createOrder(User $user, string $planSlug, string $paymentMethod = 'credit_card'): array
    {
        $plan = SubscriptionPlan::where('slug', $planSlug)->where('is_active', true)->firstOrFail();

        // Trial: check if already used
        if ($plan->is_trial) {
            $alreadyUsed = Order::where('user_id', $user->id)
                ->whereHas('plan', fn ($q) => $q->where('is_trial', true))
                ->where('status', 'paid')
                ->exists();

            if ($alreadyUsed) {
                throw new \Exception('TRIAL_ALREADY_USED');
            }
        }

        $orderNumber = 'MM' . now()->format('YmdHis') . strtoupper(Str::random(4));

        $order = Order::create([
            'order_number' => $orderNumber,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'payment_method' => $paymentMethod,
            'status' => 'pending',
            'ecpay_merchant_trade_no' => $orderNumber,
            'expires_at' => now()->addMinutes(30),
        ]);

        $paymentUrl = $this->ecPayService->getPaymentUrl(
            $orderNumber,
            $plan->price,
            "MiMeet {$plan->name}",
            url('/api/v1/payments/ecpay/return'),
            url('/api/v1/payments/ecpay/notify'),
        );

        return [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->amount,
                'status' => $order->status,
                'expires_at' => $order->expires_at->toISOString(),
            ],
            'payment_url' => $paymentUrl,
        ];
    }

    /**
     * Handle ECPay payment callback (notify).
     */
    public function handleECPayNotify(array $data): string
    {
        // Verify CheckMacValue
        if (!$this->ecPayService->verifyCallback($data)) {
            Log::warning('[ECPay] Invalid CheckMacValue', $data);
            return '0|CheckMacValue Error';
        }

        $merchantTradeNo = $data['MerchantTradeNo'] ?? '';
        $rtnCode = $data['RtnCode'] ?? '';
        $tradeNo = $data['TradeNo'] ?? '';

        $order = Order::where('ecpay_merchant_trade_no', $merchantTradeNo)->first();

        if (!$order) {
            Log::warning('[ECPay] Order not found', ['trade_no' => $merchantTradeNo]);
            return '0|Order Not Found';
        }

        if ($order->status === 'paid') {
            return '1|OK'; // Already processed
        }

        if ($rtnCode === '1') {
            $this->activateSubscription($order, $tradeNo);

            // After payment is confirmed successful, issue invoice
            $invoiceResult = app(ECPayService::class)->issueInvoice([
                'order_number' => $order->order_number,
                'customer_email' => $order->user->email ?? '',
                'amount' => $order->amount,
                'item_name' => $order->plan_name ?? 'MiMeet訂閱服務',
                'carrier_type' => $order->carrier_type ?? '',
                'carrier_num' => $order->carrier_num ?? '',
                'love_code' => $order->love_code ?? '',
            ]);

            if ($invoiceResult['success']) {
                $order->update(['ecpay_invoice_no' => $invoiceResult['invoice_no']]);
            }

            return '1|OK';
        }

        $order->update(['status' => 'failed']);
        Log::info('[ECPay] Payment failed', ['order' => $merchantTradeNo, 'rtn_code' => $rtnCode]);
        return '1|OK';
    }

    /**
     * Handle sandbox mock payment (for development).
     */
    public function handleMockPayment(string $merchantTradeNo): Order
    {
        $order = Order::where('ecpay_merchant_trade_no', $merchantTradeNo)->firstOrFail();

        if ($order->status !== 'pending') {
            return $order;
        }

        $this->activateSubscription($order, 'MOCK_' . Str::random(10));
        return $order->fresh();
    }

    /**
     * Activate subscription after successful payment.
     */
    private function activateSubscription(Order $order, string $ecpayTradeNo): void
    {
        DB::transaction(function () use ($order, $ecpayTradeNo) {
            $order->update([
                'status' => 'paid',
                'ecpay_trade_no' => $ecpayTradeNo,
                'paid_at' => now(),
            ]);

            $plan = $order->plan;

            // Deactivate old active subscriptions
            Subscription::where('user_id', $order->user_id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            // Create new subscription
            Subscription::create([
                'user_id' => $order->user_id,
                'plan_id' => $plan->id,
                'order_id' => $order->id,
                'status' => 'active',
                'started_at' => now(),
                'expires_at' => now()->addDays($plan->duration_days),
            ]);

            // Upgrade membership level
            $user = User::find($order->user_id);
            if ($user && $user->membership_level < $plan->membership_level) {
                $user->forceFill(['membership_level' => $plan->membership_level])->save();
            }

            // Notify user
            if ($user) {
                $this->notificationService->notifySubscriptionActivated($user, $plan->name);
            }
        });
    }

    /**
     * Get user's active subscription.
     */
    public function getActiveSubscription(User $user): ?array
    {
        $sub = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->with('plan')
            ->first();

        if (!$sub) {
            return null;
        }

        return [
            'id' => $sub->id,
            'plan_id' => $sub->plan->slug,
            'plan_name' => $sub->plan->name,
            'status' => $sub->status,
            'auto_renew' => $sub->auto_renew,
            'started_at' => $sub->started_at->toISOString(),
            'expires_at' => $sub->expires_at->toISOString(),
            'membership_level' => $sub->plan->membership_level,
        ];
    }
}
