<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'avatar_path', 'password', 'role', 'permissions', 'monthly_target', 'commission_rate', 'is_active', 'twofa_secret', 'twofa_enabled', 'twofa_recovery_codes', 'designation', 'department', 'phone', 'address', 'joining_date', 'exit_date', 'capacity_hours'];

    protected $hidden = ['password', 'remember_token', 'twofa_secret', 'twofa_recovery_codes'];

    protected $casts = ['email_verified_at' => 'datetime', 'is_active' => 'boolean', 'twofa_enabled' => 'boolean', 'permissions' => 'array', 'monthly_target' => 'float', 'commission_rate' => 'float', 'joining_date' => 'date', 'exit_date' => 'date'];

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '?', 0, 1);
        $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';

        return strtoupper($first.$second);
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }

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
