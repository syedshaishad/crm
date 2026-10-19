<?php

namespace Modules\Work;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Appraisal extends Model
{
    protected $fillable = ['user_id', 'period', 'rating', 'strengths', 'improvements', 'goals', 'reviewer_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public static function periods(): array
    {
        // Current half first so the form defaults to the running period.
        $now = now();
        $cur = $now->year.'-'.($now->month <= 6 ? 'H1' : 'H2');
        $out = [$cur => ($now->month <= 6 ? 'Jan–Jun ' : 'Jul–Dec ').$now->year];
        $y = (int) $now->year;
        foreach ([$y + 1, $y, $y - 1] as $yr) {
            foreach (['H2' => 'Jul–Dec ', 'H1' => 'Jan–Jun '] as $h => $lbl) {
                $k = $yr.'-'.$h;
                $out[$k] ??= $lbl.$yr;
            }
        }

        return $out;
    }
}
