<?php

namespace Modules\Invoices;

use Illuminate\Database\Eloquent\Model;
use Modules\Clients\Client;
use Modules\Core\Recurring\Schedule;
use Modules\Projects\Project;

class RecurringProfile extends Model
{
    protected $fillable = ['client_id', 'project_id', 'frequency', 'next_run_on', 'last_run_on', 'is_active', 'status', 'trial_ends_on', 'prorate_first', 'billing_day', 'currency', 'exchange_rate', 'auto_send', 'gst_rate', 'discount', 'place_of_supply', 'notes', 'items', 'created_by'];

    protected $casts = ['next_run_on' => 'date', 'last_run_on' => 'date', 'is_active' => 'boolean', 'trial_ends_on' => 'date', 'prorate_first' => 'boolean', 'auto_send' => 'boolean', 'items' => 'array'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public static function frequencies(): array
    {
        return Schedule::frequencies();
    }

    public static function statuses(): array
    {
        return ['active' => 'Active', 'paused' => 'Paused', 'cancelled' => 'Cancelled'];
    }

    /** First-run proration factor 0-1: share of the billing period remaining. */
    public function prorateFactor(): float
    {
        $run = $this->next_run_on ? $this->next_run_on->copy() : now();
        $f = match ($this->frequency) {
            'weekly' => [(8 - $run->dayOfWeekIso) / 7, 7],
            'monthly' => [$run->daysInMonth - $run->day + 1, $run->daysInMonth],
            'quarterly' => [$run->copy()->endOfQuarter()->diffInDays($run) + 1, 90],
            'yearly' => [$run->copy()->endOfYear()->diffInDays($run) + 1, $run->isLeapYear() ? 366 : 365],
            default => [1, 1],
        };

        return max(0.05, min(1, round($f[0] / max(1, $f[1]), 4)));
    }

    public function advance(): void
    {
        $next = Schedule::next($this->next_run_on, $this->frequency);
        // Billing-day snap: monthly subscriptions always land on the same date.
        if ($this->frequency === 'monthly' && $this->billing_day) {
            try {
                $d = \Carbon\Carbon::parse($next);
                $d->day = min((int) $this->billing_day, $d->daysInMonth);
                $next = $d->toDateString();
            } catch (\Throwable $e) {
            }
        }
        $this->update([
            'last_run_on' => $this->next_run_on,
            'next_run_on' => $next,
        ]);
    }
}
