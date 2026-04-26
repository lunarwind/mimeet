<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointOrder extends Model
{
    protected $table = 'point_orders';

    protected $fillable = [
        'uuid', 'user_id', 'package_id', 'points', 'amount',
        'payment_method', 'trade_no', 'gateway_trade_no', 'status', 'paid_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PointPackage::class, 'package_id');
    }

    /** 對應 payments 主表記錄 */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
