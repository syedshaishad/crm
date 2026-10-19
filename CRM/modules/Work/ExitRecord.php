<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ExitRecord extends Model
{
    protected $table = 'exits';

    protected $fillable = ['user_id', 'resigned_on', 'last_day', 'reason', 'status', 'clearance'];

    protected $casts = ['resigned_on' => 'date', 'last_day' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
