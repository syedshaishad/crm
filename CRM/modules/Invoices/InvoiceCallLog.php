<?php

namespace Modules\Invoices;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class InvoiceCallLog extends Model
{
    protected $fillable = ['invoice_id', 'client_id', 'outcome', 'notes', 'called_by'];

    public static function outcomes(): array
    {
        return ['promised' => 'Promised to pay', 'callback' => 'Call back later', 'no_answer' => 'No answer', 'dispute' => 'Disputed', 'paid' => 'Paid already', 'other' => 'Other'];
    }

    public function caller()
    {
        return $this->belongsTo(User::class, 'called_by');
    }
}
