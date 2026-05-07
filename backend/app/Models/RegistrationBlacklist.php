<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationBlacklist extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'type', 'value_hash', 'value_masked', 'reason', 'source',
        'source_user_id', 'created_by', 'expires_at', 'is_active',
        'active_value_hash', 'deactivated_by', 'deactivated_at',
        'deactivation_reason', 'created_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Auto-sync active_value_hash with value_hash + is_active.
     * 確保 schema-level UNIQUE(type, active_value_hash) 約束永遠正確,
     * 不靠 controller 維護兩欄一致性。
     */
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            $model->active_value_hash = $model->is_active ? $model->value_hash : null;
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }

    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'deactivated_by');
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }
}
