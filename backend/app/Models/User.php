<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    /**
     * User-editable fields only.
     * Admin-only fields (credit_score, status, membership_level) are managed
     * via CreditScoreService::adjust() and explicit $user->update() in admin controllers.
     */
    protected $fillable = [
        'email',
        'password',
        'nickname',
        'gender',
        'birth_date',
        'avatar_url',
        'bio',
        'height',
        'location',
        'occupation',
        'education',
        'interests',
        'email_verified',
        'phone',
        'phone_verified',
        'privacy_settings',
        'preferences',
        'profile',
        'last_active_at',
    ];

    // Admin-only fields (credit_score, status, membership_level, suspended_at,
    // delete_requested_at, deleted_at) are NOT in $fillable.
    // Admin controllers use $user->forceFill([...])->save() for these fields.

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birth_date' => 'date',
        'email_verified' => 'boolean',
        'phone_verified' => 'boolean',
        'membership_level' => 'integer',
        'credit_score' => 'integer',
        'height' => 'integer',
        'interests' => 'array',
        // privacy_settings handled by custom accessor (getPrivacySettingsAttribute)
        'last_active_at' => 'datetime',
        'suspended_at' => 'datetime',
        'delete_requested_at' => 'datetime',
        'deleted_at' => 'datetime',
        'password' => 'hashed',
        'phone' => 'encrypted',
    ];

    public function getPrivacySettingsAttribute($value): array
    {
        $defaults = [
            'show_online_status' => true,
            'allow_profile_visits' => true,
            'show_in_search' => true,
            'show_last_active' => true,
            'allow_stranger_message' => true,
        ];
        return array_merge($defaults, $value ? (is_string($value) ? json_decode($value, true) : $value) : []);
    }
}
