<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    protected $fillable = ['user_id', 'from_date', 'to_date', 'type', 'reason', 'status', 'decided_by', 'decided_at'];

    protected $casts = ['from_date' => 'date', 'to_date' => 'date', 'decided_at' => 'datetime'];

    public static function types(): array
    {
        return ['sick' => 'Sick', 'casual' => 'Casual', 'earned' => 'Earned', 'unpaid' => 'Unpaid'];
    }

    public static function statuses(): array
    {
        return ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Weekdays in the range (leave counts working days only). */
    public function days(): int
    {
        $n = 0;
        $d = $this->from_date->copy();
        while ($d->lte($this->to_date)) {
            if (! $d->isWeekend()) {
                $n++;
            }
            $d->addDay();
        }

        return $n;
    }

    /** Yearly quota balance per type for a user (unpaid is unlimited). */
    public static function balances(int $userId, ?int $year = null): array
    {
        $year ??= (int) now()->year;
        $out = [];
        foreach (['sick', 'casual', 'earned'] as $t) {
            $quota = (int) setting('leave.'.$t, 6);
            $used = (int) self::where('user_id', $userId)->where('type', $t)->where('status', 'approved')
                ->whereYear('from_date', $year)->get()->sum->days();
            $out[$t] = ['quota' => $quota, 'used' => $used, 'left' => max(0, $quota - $used)];
        }

        return $out;
    }
}
