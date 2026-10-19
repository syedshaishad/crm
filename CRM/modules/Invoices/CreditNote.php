<?php

namespace Modules\Invoices;

use Illuminate\Database\Eloquent\Model;
use Modules\Clients\Client;

class CreditNote extends Model
{
    protected $fillable = ['number', 'client_id', 'invoice_id', 'date', 'amount', 'applied_amount', 'status', 'reason', 'notes', 'created_by'];

    protected $casts = ['date' => 'date'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function remaining(): float
    {
        return max(0, round((float) $this->amount - (float) $this->applied_amount, 2));
    }
}
