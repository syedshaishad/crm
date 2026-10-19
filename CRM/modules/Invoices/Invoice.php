<?php

namespace Modules\Invoices;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Modules\Clients\Client;
use Modules\Core\Documents\DocumentNumber;
use Modules\Core\Gst\Calculator;
use Modules\Payments\Payment;
use Modules\Settings\SettingStore;

class Invoice extends Model
{
    use DocumentNumber, SoftDeletes;

    protected $fillable = ['number', 'client_id', 'assigned_to', 'created_by', 'project_id', 'issue_date', 'due_date', 'status', 'subtotal', 'discount', 'taxable_value', 'gst_rate', 'gst_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'total', 'paid_amount', 'writeoff_amount', 'dunning_level', 'currency', 'exchange_rate', 'place_of_supply', 'reverse_charge', 'notes', 'public_token', 'last_nudged_at', 'late_fee_on', 'eway_bill_no'];

    protected $casts = ['issue_date' => 'date', 'due_date' => 'date', 'reverse_charge' => 'boolean', 'last_nudged_at' => 'datetime', 'last_whatsapp_at' => 'datetime', 'late_fee_on' => 'date'];

    protected static function booted(): void
    {
        // Hard delete (never the soft Trash path): drop this invoice's TDS
        // rows so no orphaned claims linger in the register.
        static::forceDeleted(fn (Invoice $inv) => DB::table('tds_entries')->where('invoice_id', $inv->id)->delete());
        // Webhooks on status transitions (covers web, API and auto-send alike).
        static::updated(function (Invoice $inv) {
            if (! $inv->wasChanged('status')) {
                return;
            }
            $inv->loadMissing('client');
            $base = ['number' => $inv->number, 'total' => (float) $inv->total, 'balance' => $inv->balance(), 'client' => $inv->client->company ?? $inv->client->name ?? null];
            if ($inv->status === 'sent') {
                \Modules\Core\Webhooks\Webhook::fire('invoice.sent', $base);
            } elseif ($inv->status === 'paid') {
                \Modules\Core\Webhooks\Webhook::fire('invoice.paid', $base + ['paid' => (float) $inv->paid_amount]);
            }
        });
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function callLogs()
    {
        return $this->hasMany(InvoiceCallLog::class)->orderByDesc('id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Intra-state (same state) = CGST+SGST, inter-state = IGST. Falls back to global toggle. */
    public function isIntraState(): bool
    {
        try {
            $companyState = app(SettingStore::class)->get('company.state', '');
        } catch (\Throwable $e) {
            $companyState = '';
        }
        if ($this->place_of_supply && $companyState) {
            return mb_strtolower(trim($this->place_of_supply)) === mb_strtolower(trim($companyState));
        }

        return (bool) config('gst.cgst_sgst_split', true);
    }

    public function recalc(): void
    {
        $computed = Calculator::computeLines($this->items()->get(), (float) ($this->discount ?? 0), $this->gst_rate);
        $split = $this->isIntraState();
        $gst = $computed['gst'];
        $this->subtotal = $computed['subtotal'];
        $this->discount = $computed['discount'];
        $this->taxable_value = $computed['taxable'];
        $this->gst_amount = $gst;
        $this->cgst_amount = $split ? round($gst / 2, 2) : 0;
        $this->sgst_amount = $split ? round($gst / 2, 2) : 0;
        $this->igst_amount = $split ? 0 : $gst;
        $this->total = $computed['taxable'] + $gst;
        // Claimed TDS settles the invoice exactly like cash: one writer, so
        // status, overdue, guards, portal and reminders all stay consistent.
        $this->paid_amount = $this->payments()->sum('amount') + (float) DB::table('tds_entries')->where('invoice_id', $this->id)->where('kind', 'receivable')->sum('amount');
        $this->save();
    }

    /** Collectable balance: total minus cash/TDS minus any written-off amount. */
    public function balance(): float
    {
        return max(0, round((float) $this->total - (float) $this->paid_amount - (float) ($this->writeoff_amount ?? 0), 2));
    }

    /** Is this billed in a foreign currency? Books always stay in INR. */
    public function isForeign(): bool
    {
        return strtoupper($this->currency ?? 'INR') !== 'INR' && (float) ($this->exchange_rate ?? 1) > 0;
    }

    /** Total expressed in the invoice currency (for display; books use total). */
    public function foreignTotal(): float
    {
        if (! $this->isForeign()) {
            return (float) $this->total;
        }

        return round((float) $this->total / (float) $this->exchange_rate, 2);
    }

    /** Slab-wise breakup: [['rate'=>18,'taxable'=>x,'tax'=>y], ...] */
    public function taxSlabs(): array
    {
        $computed = Calculator::computeLines($this->items, (float) ($this->discount ?? 0), $this->gst_rate);

        return Calculator::slabs($computed['lines']);
    }

    /**
     * Two-way stock sync, HSN-matched and idempotent. Sending (any live
     * status) posts one out-entry per matched line; cancelling removes the
     * auto entries again. Lines match only a single tracked product —
     * shared service HSNs and ambiguous codes are skipped, never guessed.
     */
    public function syncStock(): void
    {
        try {
            $ref = 'Invoice '.$this->number;
            if ($this->status === 'cancelled') {
                \Modules\Products\StockEntry::where('reference', $ref)->delete();

                return;
            }
            if (! in_array($this->status, ['sent', 'partial', 'paid', 'overdue'], true)) {
                return;
            }
            if (\Modules\Products\StockEntry::where('reference', $ref)->exists()) {
                return;
            }
            foreach ($this->items as $it) {
                if (empty($it->hsn_code) || (float) $it->qty <= 0) {
                    continue;
                }
                $cands = \Modules\Products\Product::where('hsn_code', $it->hsn_code)->get();
                if ($cands->count() !== 1) {
                    continue;
                }
                $p = $cands->first();
                $tracked = (float) ($p->opening_stock ?? 0) > 0 || $p->low_stock_at !== null
                    || \Modules\Products\StockEntry::where('product_id', $p->id)->exists();
                if (! $tracked) {
                    continue;
                }
                $p->stockEntries()->create([
                    'type' => 'out', 'qty' => $it->qty, 'reference' => $ref,
                    'notes' => 'Auto: '.$it->description, 'moved_on' => now()->toDateString(),
                    'created_by' => auth()->id(),
                ]);
            }
        } catch (\Throwable $e) {
        }
    }
}
