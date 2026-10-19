<?php

namespace Modules\Amc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmcContract extends Model
{
    use SoftDeletes;

    protected $fillable = ['number', 'client_id', 'product_id', 'invoice_id', 'title', 'type', 'start_date', 'end_date', 'value', 'notes', 'created_by'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function client()
    {
        return $this->belongsTo(\Modules\Clients\Client::class);
    }

    public function product()
    {
        return $this->belongsTo(\Modules\Products\Product::class);
    }

    public function invoice()
    {
        return $this->belongsTo(\Modules\Invoices\Invoice::class);
    }

    /** Live state from dates: expired / expiring (≤30d) / active / unscheduled. */
    public function state(): string
    {
        if (! $this->end_date) {
            return 'unscheduled';
        }
        $today = now()->toDateString();
        if ($this->end_date->toDateString() < $today) {
            return 'expired';
        }
        if ($this->end_date->toDateString() <= now()->addDays(30)->toDateString()) {
            return 'expiring';
        }

        return 'active';
    }
}
