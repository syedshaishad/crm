<?php

namespace Modules\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'bought_on', 'cost', 'life_years', 'location', 'assigned_to', 'notes', 'created_by'];

    protected $casts = ['bought_on' => 'date', 'cost' => 'float'];

    public function holder()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    /** Straight-line book value today (never below zero). */
    public function bookValue(?string $on = null): float    {
        $on = $on ?: now()->toDateString();
        $months = max(0, $this->bought_on->diffInMonths($on));
        $totalMonths = max(1, (int) $this->life_years * 12);
        $dep = min(1, $months / $totalMonths);

        return round((float) $this->cost * (1 - $dep), 2);
    }
}
