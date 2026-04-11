<?php
<<<<<<< HEAD
=======

>>>>>>> develop
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVerification extends Model
{
<<<<<<< HEAD
    protected $fillable = ['user_id', 'type', 'status', 'photo_url', 'random_code', 'reject_reason', 'reviewed_by', 'submitted_at', 'reviewed_at'];
    protected $casts = ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
=======
    protected $fillable = [
        'user_id', 'random_code', 'photo_url', 'status',
        'expires_at', 'reviewed_by', 'reviewed_at', 'reject_reason',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
>>>>>>> develop
}
