<?php

namespace Modules\Challans;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryChallan extends Model
{
    use SoftDeletes;

    protected $fillable = ['number', 'client_id', 'invoice_id', 'challan_date', 'place_of_supply', 'vehicle_no', 'transporter', 'eway_bill', 'status', 'subtotal', 'total_qty', 'notes', 'created_by'];

    protected $casts = ['challan_date' => 'date'];

    public function items()
    {
        return $this->hasMany(DeliveryChallanItem::class);
    }

    public function client()
    {
        return $this->belongsTo(\Modules\Clients\Client::class);
    }

    public function invoice()
    {
        return $this->belongsTo(\Modules\Invoices\Invoice::class);
    }

    public static function statuses(): array
    {
        return ['draft' => 'Draft', 'issued' => 'Issued', 'cancelled' => 'Cancelled'];
    }

    public function recalc(): void
    {
        $this->subtotal = round($this->items()->sum(\DB::raw('qty * rate')), 2);
        $this->total_qty = round($this->items()->sum('qty'), 2);
        $this->save();
    }
}
