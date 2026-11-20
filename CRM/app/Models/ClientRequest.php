<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientRequest extends Model
{
    protected $fillable = [
        'client_id', 'client_user_id', 'subject', 'body', 'status',
        'priority', 'assigned_to', 'messages',
    ];

    protected $casts = [
        'messages' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(\Modules\Clients\Client::class);
    }

    public function clientUser()
    {
        return $this->belongsTo(ClientUser::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
