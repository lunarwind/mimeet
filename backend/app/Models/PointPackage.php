<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PointPackage extends Model
{
    protected $table = 'point_packages';

    protected $fillable = [
        'slug', 'name', 'points', 'bonus_points', 'price',
        'description', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'points' => 'integer',
        'bonus_points' => 'integer',
        'price' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function getTotalPointsAttribute(): int
    {
        return $this->points + $this->bonus_points;
    }

    public function getCostPerPointAttribute(): float
    {
        $total = $this->total_points;
        return $total > 0 ? round($this->price / $total, 2) : 0;
    }
}
