<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    protected $fillable = [
        'uuid',
        'reporter_id',
        'reported_user_id',
        'type',
        'description',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_note',
        'reporter_score_change',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ReportImage::class);
    }

    public function followups(): HasMany
    {
        return $this->hasMany(ReportFollowup::class);
    }
}
