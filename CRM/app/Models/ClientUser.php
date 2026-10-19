<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Modules\Clients\Client;

/**
 * Client-portal login. Deliberately separate from staff Users: different
 * guard, different table, zero access to workspace routes.
 */
class ClientUser extends Authenticatable
{
    protected $fillable = ['client_id', 'name', 'email', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['is_active' => 'boolean'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
