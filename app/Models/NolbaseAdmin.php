<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class NolbaseAdmin extends Model implements AuthenticatableContract
{
    use Authenticatable, HasFactory, Notifiable;

    public const ROLE_SUPPORT = 'support';

    public const ROLE_STAFF = 'staff';

    public const ROLE_SUPERADMIN = 'superadmin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isSupport(): bool
    {
        return $this->role === self::ROLE_SUPPORT;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function canSuspendTenants(): bool
    {
        return $this->isStaff() || $this->isSuperadmin();
    }

    public function canManageAdmins(): bool
    {
        return $this->isSuperadmin();
    }
}
