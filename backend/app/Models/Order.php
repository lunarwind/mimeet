<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'plan_id', 'amount', 'currency',
        'payment_method', 'status', 'ecpay_trade_no', 'ecpay_merchant_trade_no',
        'ecpay_payment_date', 'ecpay_payment_type', 'ecpay_payment_type_charge_fee',
        'invoice_no', 'invoice_date', 'invoice_random_number',
        'paid_at', 'expires_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /** 對應 payments 主表記錄 */
    public function payment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
