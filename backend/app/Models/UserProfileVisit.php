<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfileVisit extends Model
{
    public $timestamps = false;

    protected $fillable = ['visitor_id', 'visited_user_id', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visitor_id');
    }

    public function visitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visited_user_id');
    }
}
