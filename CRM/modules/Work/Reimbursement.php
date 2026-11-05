<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Reimbursement extends Model
{
    protected $fillable = ['user_id', 'spent_on', 'amount', 'reason', 'status', 'payroll_id'];

    protected $casts = ['spent_on' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
