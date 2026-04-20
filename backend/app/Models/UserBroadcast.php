<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBroadcast extends Model
{
    protected $table = 'user_broadcasts';

    protected $fillable = [
        'uuid', 'sender_id', 'content', 'filters',
        'recipient_count', 'points_spent', 'status', 'sent_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'recipient_count' => 'integer',
        'points_spent' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
