<?php

namespace Modules\Renewals;

use Illuminate\Support\Str;
use Modules\Amc\AmcContract;
use Modules\Clients\Client;
use Modules\Core\Numbering\NextNumber;
use Modules\Expiry\ExpiryDocument;
use Modules\Invoices\RecurringProfile;
use Modules\Proposals\Proposal;

class RenewalService
{
    /** Rebuild the pipeline from the three sources that already hold renewal dates. */
    public function sync(): array
    {
        $counts = ['amc' => 0, 'expiry' => 0, 'recurring' => 0];

        foreach (AmcContract::whereNotNull('end_date')->get() as $c) {
            $this->upsert('amc', $c->id, $c->client_id, $c->title.' ('.$c->number.')', $c->end_date, $c->value);
            $counts['amc']++;
        }
        foreach (ExpiryDocument::whereNotNull('valid_to')->get() as $d) {
            $this->upsert('expiry', $d->id, null, $d->title.' ('.$d->number.')', $d->valid_to, 0);
            $counts['expiry']++;
        }
        foreach (RecurringProfile::whereNotNull('next_run_on')->get() as $p) {
            $amount = collect($p->items ?: [])->sum(function ($i) {
                return (float) ($i['rate'] ?? 0) * (float) ($i['qty'] ?? 1);
            });
            $client = Client::find($p->client_id);
            $this->upsert('recurring', $p->id, $p->client_id,
                'Recurring '.$p->frequency.' · '.($client ? ($client->company ?: $client->name) : 'client #'.$p->client_id),
                $p->next_run_on, $amount);
            $counts['recurring']++;
        }

        // Expiry documents and sources that were removed should not leave stale
        // rows behind while still in the "upcoming" state.
        Renewal::where('status', 'upcoming')->get()->each(function ($r) {
            $exists = $r->source_type === 'amc' ? AmcContract::withTrashed()->whereKey($r->source_id)->exists()
                : ($r->source_type === 'expiry' ? ExpiryDocument::withTrashed()->whereKey($r->source_id)->exists()
                : RecurringProfile::whereKey($r->source_id)->exists());
            if (! $exists) $r->delete();
        });

        return $counts;
    }

    private function upsert(string $type, int $id, ?int $clientId, string $title, $date, $amount): void
    {
        $row = Renewal::firstOrNew(['source_type' => $type, 'source_id' => $id]);
        $row->fill([
            'client_id' => $clientId,
            'title' => $title,
            'renewal_date' => $date,
            'amount' => $amount,
        ])->save();
    }

    /** Draft a renewal proposal once; repeat calls return the existing draft. */
    public function draft(Renewal $renewal): Proposal
    {
        if ($renewal->proposal_id && Proposal::find($renewal->proposal_id)) {
            return Proposal::findOrFail($renewal->proposal_id);
        }
        if (! $renewal->client_id || ! Client::whereKey($renewal->client_id)->exists()) {
            throw new \RuntimeException('This renewal has no client to draft a proposal for.');
        }

        $amount = max(0, (float) $renewal->amount);
        $prefix = (string) setting('proposal.prefix', 'PROP-');
        $proposal = Proposal::create([
            'number' => NextNumber::take($prefix, 'proposals'),
            'client_id' => $renewal->client_id,
            'created_by' => auth()->id(),
            'assigned_to' => auth()->id(),
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays((int) setting('proposal.valid_days', 15))->toDateString(),
            'gst_rate' => 0,
            'subtotal' => $amount,
            'taxable_value' => $amount,
            'total' => $amount,
            'notes' => 'Auto-drafted renewal: '.$renewal->title.'. Renewal date '.d($renewal->renewal_date, 'j M Y').'.',
            'sign_token' => Str::random(32),
        ]);
        $proposal->items()->create([
            'description' => $renewal->title.' — renewal',
            'qty' => 1, 'unit' => 'NOS', 'rate' => $amount,
            'amount' => $amount, 'tax_rate' => 0, 'billing' => 'once',
        ]);
        $proposal->recalc();
        $renewal->update(['status' => 'drafted', 'proposal_id' => $proposal->id]);

        return $proposal;
    }

    /** Auto-draft proposals for warm renewals that do not have one yet. */
    public function autoDraft(int $days = 45): int
    {
        $made = 0;
        $cutoff = now()->startOfDay()->addDays($days)->toDateString();
        Renewal::where('status', 'upcoming')
            ->whereNotNull('client_id')
            ->whereNotNull('renewal_date')
            ->whereDate('renewal_date', '>=', now()->toDateString())
            ->whereDate('renewal_date', '<=', $cutoff)
            ->orderBy('renewal_date')
            ->get()
            ->each(function ($r) use (&$made) {
                try { $this->draft($r); $made++; } catch (\Throwable $e) { /* no client, leave for staff */ }
            });

        return $made;
    }
}
