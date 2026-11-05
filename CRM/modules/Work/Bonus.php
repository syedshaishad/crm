<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Bonus extends Model
{
    protected $fillable = ['user_id', 'month', 'amount', 'reason', 'status', 'payroll_id'];

    protected $casts = ['month' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
