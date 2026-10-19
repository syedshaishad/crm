<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'password', 'role', 'permissions', 'monthly_target', 'commission_rate', 'is_active', 'twofa_secret', 'twofa_enabled', 'twofa_recovery_codes', 'designation', 'department', 'phone', 'address', 'joining_date', 'exit_date', 'capacity_hours'];

    protected $hidden = ['password', 'remember_token', 'twofa_secret', 'twofa_recovery_codes'];

    protected $casts = ['email_verified_at' => 'datetime', 'is_active' => 'boolean', 'twofa_enabled' => 'boolean', 'permissions' => 'array', 'monthly_target' => 'float', 'commission_rate' => 'float', 'joining_date' => 'date', 'exit_date' => 'date'];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    /** Managers and admins control invoice assignment; staff handle their own. */
    public function canManageInvoices(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }

    public function scopeStaff($query)
    {
        return $query->where('role', 'staff')->where('is_active', true)->orderBy('name');
    }
}
