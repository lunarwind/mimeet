<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminOperationLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['admin_id', 'admin_name', 'action_type', 'resource_type', 'resource_id', 'description', 'metadata', 'ip_address', 'created_at'];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];
}
