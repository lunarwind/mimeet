<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DateInvitation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'inviter_id',
        'invitee_id',
        'date_time',
        'location_name',
        'latitude',
        'longitude',
        'qr_token',
        'status',
        'inviter_scanned_at',
        'invitee_scanned_at',
        'inviter_gps_lat',
        'inviter_gps_lng',
        'inviter_gps_verified',
        'invitee_gps_lat',
        'invitee_gps_lng',
        'invitee_gps_verified',
        'gps_verification_passed',
        'score_awarded',
        'verified_at',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'date_time' => 'datetime',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'inviter_scanned_at' => 'datetime',
        'invitee_scanned_at' => 'datetime',
        'inviter_gps_verified' => 'boolean',
        'invitee_gps_verified' => 'boolean',
        'gps_verification_passed' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'inviter_gps_lat' => 'float',
        'inviter_gps_lng' => 'float',
        'invitee_gps_lat' => 'float',
        'invitee_gps_lng' => 'float',
    ];

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }
}
