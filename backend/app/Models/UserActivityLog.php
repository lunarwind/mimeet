<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    public $timestamps = false;

<<<<<<< HEAD
    protected $fillable = ['user_id', 'action_type', 'metadata', 'ip_address', 'user_agent', 'created_at'];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];
=======
    protected $fillable = [
        'user_id',
        'action',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
>>>>>>> develop

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

<<<<<<< HEAD
    public static function log(int $userId, string $action, ?array $metadata = null, ?string $ip = null, ?string $userAgent = null): self
    {
        return static::create([
            'user_id' => $userId,
            'action_type' => $action,
            'metadata' => $metadata,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
=======
    /**
     * Log a user activity.
     */
    public static function log(int $userId, string $action, ?array $metadata = null, ?string $ip = null, ?string $ua = null): self
    {
        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => $ip,
            'user_agent' => $ua ? substr($ua, 0, 500) : null,
>>>>>>> develop
            'created_at' => now(),
        ]);
    }
}
