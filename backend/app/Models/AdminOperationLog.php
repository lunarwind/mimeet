<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminOperationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_id', 'action', 'resource_type', 'resource_id',
        'description', 'ip_address', 'user_agent', 'request_summary',
        'created_at',
    ];

    protected $casts = [
        'request_summary' => 'array',
        'created_at' => 'datetime',
    ];
}
