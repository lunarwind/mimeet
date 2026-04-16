<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFollow extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = ['follower_id', 'following_id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (UserFollow $follow) {
            $follow->created_at = $follow->created_at ?? now();
        });
    }

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function followed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}
