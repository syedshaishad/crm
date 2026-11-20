<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedView extends Model
{
    protected $fillable = ['user_id', 'route', 'name', 'query', 'is_default'];

    protected $casts = [
        'query' => 'array',
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
