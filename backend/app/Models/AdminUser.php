<?php

namespace App\Models;

<<<<<<< HEAD
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
=======
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
>>>>>>> develop
use Laravel\Sanctum\HasApiTokens;

class AdminUser extends Authenticatable
{
<<<<<<< HEAD
    use HasApiTokens, HasFactory;
=======
    use HasApiTokens, Notifiable;
>>>>>>> develop

    protected $table = 'admin_users';

    protected $fillable = [
<<<<<<< HEAD
        'name', 'email', 'password', 'role', 'is_active',
        'failed_login_attempts', 'locked_until',
        'last_login_at', 'last_login_ip',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
    ];

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

=======
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Check if this admin has a specific role.
     */
    public function hasRole(string $role): bool
    {
        if ($this->role === 'super_admin') {
            return true; // super_admin has all roles
        }

        return $this->role === $role;
    }

    /**
     * Check if this admin is a super admin.
     */
>>>>>>> develop
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
}
