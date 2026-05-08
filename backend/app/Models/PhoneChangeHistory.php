<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneChangeHistory extends Model
{
    protected $table = 'phone_change_histories';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'old_phone_hash', 'old_phone_masked',
        'new_phone_hash', 'new_phone_masked',
        'source', 'ip_address', 'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];
}
