<?php

namespace Modules\Challans;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryRoute extends Model
{
    use SoftDeletes;

    protected $fillable = ['number', 'route_date', 'vehicle_no', 'driver', 'status', 'notes', 'created_by'];

    protected $casts = ['route_date' => 'date'];

    public static function statuses(): array
    {
        return ['planned' => 'Planned', 'on_route' => 'On route', 'done' => 'Done'];
    }

    public function stops()
    {
        return $this->hasMany(DeliveryRouteStop::class, 'route_id')->orderBy('position');
    }

    public function progress(): array
    {
        $total = $this->stops()->count();
        $done = $this->stops()->whereNotNull('done_at')->count();

        return ['done' => $done, 'total' => $total, 'pct' => $total > 0 ? (int) round($done / $total * 100) : 0];
    }
}
