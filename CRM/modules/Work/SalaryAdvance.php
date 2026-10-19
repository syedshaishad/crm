<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SalaryAdvance extends Model
{
    protected $fillable = ['user_id', 'kind', 'amount', 'dated', 'note', 'created_by'];

    protected $casts = ['dated' => 'date', 'amount' => 'float'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Still owed by a staffer (advances minus recoveries). */
    public static function balance(int $userId): float
    {
        $adv = (float) self::where('user_id', $userId)->where('kind', 'advance')->sum('amount');
        $rec = (float) self::where('user_id', $userId)->where('kind', 'recovery')->sum('amount');

        return round($adv - $rec, 2);
    }
}
