<?php

namespace Modules\Work;

use Illuminate\Database\Eloquent\Model;

class Bonus extends Model
{
    protected $fillable = ['user_id', 'month', 'amount', 'reason', 'status', 'payroll_id'];

    protected $casts = ['month' => 'date'];
}
