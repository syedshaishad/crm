<?php

namespace Modules\Proposals;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Clients\Client;
use Modules\Core\Documents\DocumentNumber;
use Modules\Core\Gst\Calculator;
use Modules\Invoices\Invoice;
use Modules\Projects\Project;
use Modules\Settings\SettingStore;

class Proposal extends Model
{
    use DocumentNumber, SoftDeletes;

    protected $fillable = ['number', 'client_id', 'project_id', 'assigned_to', 'created_by', 'status', 'issue_date', 'valid_until', 'subtotal', 'discount', 'taxable_value', 'gst_rate', 'gst_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'total', 'place_of_supply', 'reverse_charge', 'notes', 'scope', 'results', 'cover_path', 'terms', 'sign_token', 'signed_by', 'signed_at', 'accept_mode', 'accept_note', 'close_reason', 'lost_reason', 'competitor', 'signature_path', 'converted_invoice_id', 'view_count', 'first_viewed_at', 'last_viewed_at', 'last_view_ip', 'last_followed_up_at'];

    protected $casts = ['issue_date' => 'date', 'valid_until' => 'date', 'signed_at' => 'datetime', 'reverse_charge' => 'boolean', 'first_viewed_at' => 'datetime', 'last_viewed_at' => 'datetime', 'last_followed_up_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(ProposalItem::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function statuses(): array
    {
        return ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'declined' => 'Declined', 'expired' => 'Expired', 'invoiced' => 'Invoiced'];
    }

    /** Structured win-loss reasons for declined proposals. */
    public static function lostReasons(): array
    {
        return ['price' => 'Price too high', 'budget' => 'No budget', 'timing' => 'Bad timing', 'competitor' => 'Chose a competitor', 'features' => 'Missing features', 'ghosted' => 'Went silent', 'other' => 'Other'];
    }

    /** Display status: a sent proposal past validity shows as expired. */
    public function displayStatus(): string
    {
        if ($this->status === 'sent' && $this->valid_until && $this->valid_until->isPast()) {
            return 'expired';
        }

        return $this->status;
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

    protected static function booted(): void
    {
        // Webhooks on close (covers signing link, offline marks and API alike).
        static::updated(function (Proposal $p) {
            if (! $p->wasChanged('status')) {
                return;
            }
            $p->loadMissing('client');
            $base = ['number' => $p->number, 'total' => (float) $p->total, 'client' => $p->client->company ?? $p->client->name ?? null];
            if ($p->status === 'accepted') {
                \Modules\Core\Webhooks\Webhook::fire('proposal.accepted', $base);
            } elseif ($p->status === 'declined') {
                \Modules\Core\Webhooks\Webhook::fire('proposal.declined', $base + ['lost_reason' => $p->lost_reason, 'competitor' => $p->competitor]);
            }
        });
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
        $this->save();
    }

    /** Slab-wise breakup: [['rate'=>18,'taxable'=>x,'tax'=>y], ...] */
    public function taxSlabs(): array
    {
        $computed = Calculator::computeLines($this->items, (float) ($this->discount ?? 0), $this->gst_rate);

        return Calculator::slabs($computed['lines']);
    }

    /** Taxable split by billing cycle: ['once' => x, 'monthly' => y, 'yearly' => z]. */
    public function billingSplit(): array
    {
        $split = ['once' => 0, 'monthly' => 0, 'yearly' => 0];
        foreach ($this->items as $it) {
            $k = in_array($it->billing, ['monthly', 'yearly'], true) ? $it->billing : 'once';
            $split[$k] = round($split[$k] + (float) $it->amount, 2);
        }

        return $split;
    }
}
