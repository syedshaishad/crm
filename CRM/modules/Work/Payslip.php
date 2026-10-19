<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    protected $fillable = ['payroll_id', 'user_id', 'working_days', 'paid_days', 'gross_monthly', 'gross_earned', 'overtime_pay', 'bonus', 'reimbursements', 'advance_cut', 'loan_cut', 'tds', 'net'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function additions(): float
    {
        return round((float) $this->overtime_pay + (float) $this->bonus + (float) $this->reimbursements, 2);
    }

    public function deductions(): float
    {
        return round((float) $this->advance_cut + (float) $this->loan_cut + (float) $this->tds, 2);
    }
}
