<?php

namespace Modules\Work;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    protected $fillable = ['month', 'status', 'finalized_by', 'finalized_at'];

    protected $casts = ['month' => 'date', 'finalized_at' => 'datetime'];

    public function slips()
    {
        return $this->hasMany(Payslip::class);
    }

    public function label(): string
    {
        return $this->month->format('F Y');
    }

    /** Weekdays in the month minus company holidays. */
    public static function workingDays(string $month): int
    {
        $start = \Carbon\Carbon::parse($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $holidays = Holiday::whereDate('day', '>=', $start->toDateString())->whereDate('day', '<=', $end->toDateString())->pluck('day')->map(fn ($d) => substr((string) $d, 0, 10))->all();
        $n = 0;
        $d = $start->copy();
        while ($d->lte($end)) {
            if (! $d->isWeekend() && ! in_array($d->toDateString(), $holidays, true)) {
                $n++;
            }
            $d->addDay();
        }

        return $n;
    }
}
