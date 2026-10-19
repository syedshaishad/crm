<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class StaffLoan extends Model
{
    protected $fillable = ['user_id', 'principal', 'emi', 'disbursed_on', 'status', 'note'];

    protected $casts = ['disbursed_on' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function repayments()
    {
        return $this->hasMany(LoanRepayment::class, 'loan_id');
    }

    public function repaid(): float
    {
        return (float) $this->repayments()->sum('amount');
    }

    public function outstanding(): float
    {
        return max(0, round((float) $this->principal - $this->repaid(), 2));
    }
}
