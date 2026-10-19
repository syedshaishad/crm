<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = ['user_id', 'actor', 'action', 'subject_type', 'subject_id', 'label', 'description'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
