<?php

namespace Modules\Challans;

use Illuminate\Database\Eloquent\Model;

class DeliveryRouteStop extends Model
{
    public $timestamps = false;

    protected $fillable = ['route_id', 'challan_id', 'position', 'done_at'];

    protected $casts = ['done_at' => 'datetime'];

    public function challan()
    {
        return $this->belongsTo(DeliveryChallan::class, 'challan_id');
    }
}
