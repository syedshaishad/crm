<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $fillable = ['user_id', 'title', 'completed_on', 'valid_till', 'note'];

    protected $casts = ['completed_on' => 'date', 'valid_till' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
