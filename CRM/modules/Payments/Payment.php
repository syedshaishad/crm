<?php

namespace Modules\Payments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Clients\Client;
use Modules\Invoices\Invoice;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = ['number', 'invoice_id', 'client_id', 'amount', 'method', 'reference', 'razorpay_payment_id', 'razorpay_order_id', 'paid_on', 'notes', 'public_token'];

    protected $casts = ['paid_on' => 'date'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    protected static function booted(): void
    {
        static::saved(fn (Payment $p) => self::syncInvoice($p->invoice_id));
        static::created(function (Payment $p) {
            $p->loadMissing(['client', 'invoice']);
            \Modules\Core\Webhooks\Webhook::fire('payment.received', [
                'number' => $p->number, 'amount' => (float) $p->amount, 'method' => $p->method,
                'client' => $p->client->company ?? $p->client->name ?? null,
                'invoice' => $p->invoice->number ?? null,
            ]);
        });
        static::deleted(fn (Payment $p) => self::syncInvoice($p->invoice_id));
    }

    /** Recalculate invoice totals AND roll its status to match what is actually paid. */
    public static function syncInvoice($invoiceId): void
    {
        if (! $invoiceId || ! $inv = Invoice::find($invoiceId)) {
            return;
        }
        $inv->recalc();
        // Cancelled is terminal and manual — never touch it. Everything else
        // (draft/sent/partial/paid/overdue) follows the money so the status
        // always mirrors recorded payments. The nightly overdue job re-flags
        // past-due invoices with an outstanding balance.
        if ($inv->status === 'cancelled') {
            return;
        }
        $paid = (float) $inv->paid_amount + (float) ($inv->writeoff_amount ?? 0);
        $total = (float) $inv->total;
        $inv->status = $paid <= 0
            ? ($inv->status === 'draft' ? 'draft' : 'sent')
            : ($paid < $total ? 'partial' : 'paid');
        $inv->save();
    }
}
