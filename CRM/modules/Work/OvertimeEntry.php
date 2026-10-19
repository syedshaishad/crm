<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OvertimeEntry extends Model
{
    protected $fillable = ['user_id', 'day', 'minutes', 'note', 'status', 'decided_by'];

    protected $casts = ['day' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
