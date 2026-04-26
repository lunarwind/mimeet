<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 統一金流主表
 *
 * type 分三類：
 *   verification  信用卡身份驗證（CCV_ 前綴）
 *   subscription  會員購買       （MM   前綴）
 *   points        點數儲值       （PTS_ 前綴）
 */
class Payment extends Model
{
    protected $fillable = [
        'user_id', 'type', 'order_no', 'item_name',
        'amount', 'currency', 'status', 'gateway', 'environment',
        'reference_id', 'gateway_trade_no', 'card_country', 'payment_method',
        'paid_at', 'refund_scheduled_at', 'refunded_at',
        'refund_trade_no', 'refund_failure_reason',
        'refund_attempts', 'requires_manual_review',
        'invoice_no', 'invoice_issued_at',
        'failure_reason', 'raw_callback',
    ];

    protected $casts = [
        'amount'               => 'integer',
        'paid_at'              => 'datetime',
        'refund_scheduled_at'  => 'datetime',
        'refunded_at'          => 'datetime',
        'invoice_issued_at'    => 'datetime',
        'raw_callback'         => 'array',
    ];

    // ── Relations ───────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 訂閱業務記錄（type=subscription） */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'reference_id');
    }

    /** 點數業務記錄（type=points） */
    public function pointOrder(): BelongsTo
    {
        return $this->belongsTo(PointOrder::class, 'reference_id');
    }

    /** 信用卡驗證業務記錄（type=verification） */
    public function creditCardVerification(): BelongsTo
    {
        return $this->belongsTo(CreditCardVerification::class, 'reference_id');
    }

    // ── Helpers ─────────────────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isRefundable(): bool
    {
        return $this->status === 'paid' && is_null($this->refunded_at);
    }

    /** 依 type 動態載入業務記錄 */
    public function businessRecord(): ?Model
    {
        return match ($this->type) {
            'subscription' => $this->order,
            'points'       => $this->pointOrder,
            'verification' => $this->creditCardVerification,
            default        => null,
        };
    }
}
