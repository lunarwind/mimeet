<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCardVerification extends Model
{
    protected $fillable = [
        'user_id',
        'order_no',
        'amount',
        'status',
        'gateway',
        'gateway_trade_no',
        'payment_method',
        'card_last4',
        'paid_at',
        'refund_initiated_at',
        'refunded_at',
        'refund_trade_no',
        'failure_reason',
        'raw_callback',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'refund_initiated_at' => 'datetime',
        'refunded_at' => 'datetime',
        'raw_callback' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
