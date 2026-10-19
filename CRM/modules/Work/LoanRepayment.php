<?php

namespace Modules\Work;

use Illuminate\Database\Eloquent\Model;

class LoanRepayment extends Model
{
    protected $fillable = ['loan_id', 'payroll_id', 'amount', 'paid_on'];

    protected $casts = ['paid_on' => 'date'];

    public function loan()
    {
        return $this->belongsTo(StaffLoan::class, 'loan_id');
    }
}
