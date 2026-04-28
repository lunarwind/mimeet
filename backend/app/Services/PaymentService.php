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
     * Create an Order record only (no payment URL).
     * Used by SubscriptionController when integrating with UnifiedPaymentService.
     *
     * @throws \Exception TRIAL_ALREADY_USED
     */
    public function createOrderRecord(
        User   $user,
        string $planSlug,
        string $orderNo,
        string $paymentMethod = 'credit_card',
    ): Order {
        $plan = SubscriptionPlan::where('slug', $planSlug)->where('is_active', true)->firstOrFail();

        if ($plan->is_trial) {
            $alreadyUsed = Order::where('user_id', $user->id)
                ->whereHas('plan', fn ($q) => $q->where('is_trial', true))
                ->where('status', 'paid')
                ->exists();
            if ($alreadyUsed) {
                throw new \Exception('TRIAL_ALREADY_USED');
            }
        }

        return Order::create([
            'order_number'            => $orderNo,
            'user_id'                 => $user->id,
            'plan_id'                 => $plan->id,
            'amount'                  => $plan->price,
            'currency'                => $plan->currency,
            'payment_method'          => $paymentMethod,
            'status'                  => 'pending',
            'ecpay_merchant_trade_no' => $orderNo,
            'expires_at'              => now()->addMinutes(30),
        ]);
    }

    /**
     * Handle ECPay payment callback (notify).
     * Stores reconciliation fields and issues invoice on success.
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

        $order = Order::where('ecpay_merchant_trade_no', $merchantTradeNo)->first();

        if (!$order) {
            Log::warning('[ECPay] Order not found', ['trade_no' => $merchantTradeNo]);
            return '0|Order Not Found';
        }

        // Idempotency: already processed
        if ($order->status === 'paid') {
            return '1|OK';
        }

        // Store reconciliation fields regardless of success/failure
        $order->update(array_filter([
            'ecpay_trade_no' => $data['TradeNo'] ?? null,
            'ecpay_payment_date' => $data['PaymentDate'] ?? null,
            'ecpay_payment_type' => $data['PaymentType'] ?? null,
            'ecpay_payment_type_charge_fee' => $data['PaymentTypeChargeFee'] ?? null,
        ], fn ($v) => $v !== null));

        if ($rtnCode === '1') {
            $this->activateSubscription($order);

            // Issue electronic invoice
            $this->issueInvoiceForOrder($order);

            return '1|OK';
        }

        $order->update(['status' => 'failed']);
        Log::info('[ECPay] Payment failed', ['order' => $merchantTradeNo, 'rtn_code' => $rtnCode]);
        return '1|OK';
    }

    /**
     * Activate subscription after successful payment.
     * Changed to public so SubscriptionHandler can call it directly.
     */
    public function activateSubscription(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $plan = $order->plan;
            $paidAt = $order->fresh()->paid_at;

            // Capture the latest active expiry before deactivating, so early
            // renewals extend from the existing end date instead of losing days.
            $existingExpiry = Subscription::where('user_id', $order->user_id)
                ->where('status', 'active')
                ->where('expires_at', '>', $paidAt)
                ->max('expires_at');

            // Deactivate old active subscriptions
            Subscription::where('user_id', $order->user_id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            // New subscription starts from whichever is later: payment time
            // or the previous subscription's expiry (early renewal).
            $startedAt = $existingExpiry
                ? \Carbon\Carbon::parse($existingExpiry)
                : $paidAt;

            // Create new subscription
            Subscription::create([
                'user_id' => $order->user_id,
                'plan_id' => $plan->id,
                'order_id' => $order->id,
                'status' => 'active',
                'started_at' => $startedAt,
                'expires_at' => $startedAt->copy()->addDays($plan->duration_days),
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
     * Issue B2C electronic invoice for a paid order.
     * SSOT：發票欄位寫入 payments 表，不再寫 orders 表。
     */
    public function issueInvoiceForOrder(Order $order): void
    {
        if (!$this->ecPayService->isInvoiceEnabled()) {
            return;
        }

        // 找對應的 Payment 主表記錄
        $payment = \App\Models\Payment::find($order->payment_id);
        if (!$payment) {
            Log::warning('[Invoice] Payment record not found for order', ['order_id' => $order->id]);
            return;
        }

        if ($payment->invoice_no) {
            return; // 已開立，冪等
        }

        $user = $order->user;
        if (!$user) {
            return;
        }

        $plan = $order->plan;

        $invoiceResult = $this->ecPayService->issueInvoice([
            'relate_number'  => $order->order_number,
            'customer_email' => $user->email ?? '',
            'customer_phone' => '',
            'sales_amount'   => $order->amount,
            'items'          => [[
                'seq'    => 1,
                'name'   => 'MiMeet ' . ($plan->name ?? '訂閱方案'),
                'count'  => 1,
                'word'   => '式',
                'price'  => $order->amount,
                'amount' => $order->amount,
            ]],
        ]);

        if ($invoiceResult) {
            // 單寫 payments，不寫 orders
            $payment->update([
                'invoice_no'            => $invoiceResult['invoice_no'],
                'invoice_issued_at'     => now(),
                'invoice_random_number' => $invoiceResult['random_number'] ?? null,
            ]);
            Log::info('[Invoice] Saved to payments', [
                'payment_id' => $payment->id,
                'invoice_no' => $invoiceResult['invoice_no'],
            ]);
        }
    }

    /**
     * Issue invoice for a point purchase, writing to payments SSOT.
     */
    public function issueInvoiceForPointOrder(\App\Models\PointOrder $pointOrder): void
    {
        if (!$this->ecPayService->isInvoiceEnabled()) {
            return;
        }

        $payment = \App\Models\Payment::find($pointOrder->payment_id);
        if (!$payment || $payment->invoice_no) {
            return;
        }

        $user = $pointOrder->user;
        if (!$user) {
            return;
        }

        $package = $pointOrder->package;

        $invoiceResult = $this->ecPayService->issueInvoice([
            'relate_number'  => $pointOrder->trade_no,
            'customer_email' => $user->email ?? '',
            'customer_phone' => '',
            'sales_amount'   => $pointOrder->amount,
            'items'          => [[
                'seq'    => 1,
                'name'   => 'MiMeet 點數 ' . ($package?->name ?? $pointOrder->points . '點'),
                'count'  => 1,
                'word'   => '組',
                'price'  => $pointOrder->amount,
                'amount' => $pointOrder->amount,
            ]],
        ]);

        if ($invoiceResult) {
            $payment->update([
                'invoice_no'            => $invoiceResult['invoice_no'],
                'invoice_issued_at'     => now(),
                'invoice_random_number' => $invoiceResult['random_number'] ?? null,
            ]);
            Log::info('[Invoice] Saved to payments (points)', [
                'payment_id' => $payment->id,
                'invoice_no' => $invoiceResult['invoice_no'],
            ]);
        }
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
            'days_remaining' => (int) max(0, now()->diffInDays($sub->expires_at, false)),
            'membership_level' => $sub->plan->membership_level,
        ];
    }
}
