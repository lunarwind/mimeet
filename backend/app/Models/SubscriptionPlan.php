<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'slug', 'name', 'price', 'currency', 'duration_days',
        'membership_level', 'features', 'is_trial', 'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_trial' => 'boolean',
        'is_active' => 'boolean',
        'price' => 'integer',
        'duration_days' => 'integer',
    ];
}
