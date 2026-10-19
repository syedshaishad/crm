<?php

namespace Modules\Cheques;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdcCheque extends Model
{
    use SoftDeletes;

    protected $fillable = ['number', 'client_id', 'invoice_id', 'cheque_no', 'bank', 'amount', 'cheque_date', 'status', 'deposited_on', 'cleared_on', 'payment_id', 'notes', 'created_by'];

    protected $casts = ['amount' => 'float', 'cheque_date' => 'date', 'deposited_on' => 'date', 'cleared_on' => 'date'];

    public static function statuses(): array
    {
        return ['held' => 'Held', 'deposited' => 'Deposited', 'cleared' => 'Cleared', 'bounced' => 'Bounced', 'cancelled' => 'Cancelled'];
    }

    public function client()
    {
        return $this->belongsTo(\Modules\Clients\Client::class);
    }

    public function invoice()
    {
        return $this->belongsTo(\Modules\Invoices\Invoice::class);
    }

    public function payment()
    {
        return $this->belongsTo(\Modules\Payments\Payment::class);
    }

    /** Deposit-ready: date reached and still sitting in the drawer. */
    public function isDue(): bool
    {
        return $this->status === 'held' && $this->cheque_date->toDateString() <= now()->toDateString();
    }
}
