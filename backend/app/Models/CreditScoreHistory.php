<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditScoreHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'delta',
        'score_before',
        'score_after',
        'type',
        'reason',
        'operator_id',
        'created_at',
    ];

    protected $casts = [
        'delta' => 'integer',
        'score_before' => 'integer',
        'score_after' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
