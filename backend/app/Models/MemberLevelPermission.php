<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberLevelPermission extends Model
{
    protected $fillable = ['level', 'permission_key', 'is_allowed', 'config'];
    protected $casts = ['level' => 'decimal:1', 'is_allowed' => 'boolean', 'config' => 'array'];
}
