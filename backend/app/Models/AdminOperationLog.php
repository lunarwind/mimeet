<?php
<<<<<<< HEAD
=======

>>>>>>> develop
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminOperationLog extends Model
{
    public $timestamps = false;
<<<<<<< HEAD
    protected $fillable = ['admin_id', 'admin_name', 'action_type', 'resource_type', 'resource_id', 'description', 'metadata', 'ip_address', 'created_at'];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];
=======

    protected $fillable = [
        'admin_id', 'action', 'resource_type', 'resource_id',
        'description', 'ip_address', 'user_agent', 'request_summary',
        'created_at',
    ];

    protected $casts = [
        'request_summary' => 'array',
        'created_at' => 'datetime',
    ];
>>>>>>> develop
}
