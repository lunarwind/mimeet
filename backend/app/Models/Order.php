<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'plan_id', 'amount', 'currency',
        'payment_method', 'status', 'ecpay_trade_no', 'ecpay_merchant_trade_no',
<<<<<<< HEAD
        'invoice_no', 'ecpay_payment_type', 'payment_date',
=======
        'ecpay_payment_date', 'ecpay_payment_type', 'ecpay_payment_type_charge_fee',
        'invoice_no', 'invoice_date', 'invoice_random_number',
>>>>>>> develop
        'paid_at', 'expires_at',
        'carrier_type', 'carrier_num', 'love_code', 'ecpay_invoice_no',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'payment_date' => 'datetime',
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
}
