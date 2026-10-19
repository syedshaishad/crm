<?php

namespace Modules\Petty;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyEntry extends Model
{
    use SoftDeletes;

    protected $fillable = ['entry_date', 'kind', 'amount', 'note', 'created_by'];

    protected $casts = ['entry_date' => 'date', 'amount' => 'float'];

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /** Cash in hand right now (all time). */
    public static function balance(): float
    {
        $in = (float) self::where('kind', 'in')->sum('amount');
        $out = (float) self::where('kind', 'out')->sum('amount');

        return round($in - $out, 2);
    }

    /** Balance as of end of a date (for backdated entries). */    public static function balanceOn(string $date): float
    {
        $in = (float) self::where('kind', 'in')->whereDate('entry_date', '<=', $date)->sum('amount');
        $out = (float) self::where('kind', 'out')->whereDate('entry_date', '<=', $date)->sum('amount');

        return round($in - $out, 2);
    }
}
