<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastCampaign extends Model
{
    protected $fillable = [
        'title', 'content', 'filters', 'delivery_mode', 'status',
        'target_count', 'sent_count', 'created_by', 'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'completed_at' => 'datetime',
    ];
}
