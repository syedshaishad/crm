<?php

namespace Modules\Work;

use Illuminate\Database\Eloquent\Model;

class Reimbursement extends Model
{
    protected $fillable = ['user_id', 'spent_on', 'amount', 'reason', 'status', 'payroll_id'];

    protected $casts = ['spent_on' => 'date'];
}
