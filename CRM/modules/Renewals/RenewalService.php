<?php

namespace Modules\Renewals;

use Illuminate\Support\Facades\DB;
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

        // A source that was trashed/removed must not leave a live renewal.
        Renewal::where('status', 'upcoming')->get()->each(function ($r) {
            $exists = $r->source_type === 'amc' ? AmcContract::whereKey($r->source_id)->exists()
                : ($r->source_type === 'expiry' ? ExpiryDocument::whereKey($r->source_id)->exists()
                : RecurringProfile::whereKey($r->source_id)->exists());
            if (! $exists) $r->delete();
        });

        return $counts;
    }

    private function upsert(string $type, int $id, ?int $clientId, string $title, $date, $amount): void
    {
        $row = Renewal::firstOrNew(['source_type' => $type, 'source_id' => $id]);
        // Never overwrite a row staff have already worked on: amount, title
        // and client assignment may have been corrected after the first sync.
        if ($row->exists) return;
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
        return DB::transaction(function () use ($renewal) {
            // Lock the row so a double-click and the nightly job cannot both
            // allocate a proposal number for the same renewal.
            $row = Renewal::whereKey($renewal->id)->lockForUpdate()->firstOrFail();
            if ($row->proposal_id && Proposal::find($row->proposal_id)) {
                if ($row->status !== 'drafted') $row->update(['status' => 'drafted']);
                return Proposal::findOrFail($row->proposal_id);
            }
            if (! $row->client_id || ! Client::whereKey($row->client_id)->exists()) {
                throw new \RuntimeException('This renewal has no client to draft a proposal for.');
            }

            $amount = max(0, (float) $row->amount);
            $prefix = (string) setting('proposal.prefix', 'PROP-');
            $reviewer = auth()->id();
            if (! $reviewer) {
                $reviewer = \App\Models\User::where('role', 'admin')->where('is_active', true)->orderBy('id')->value('id');
            }
            $proposal = Proposal::create([
                'number' => NextNumber::take($prefix, 'proposals'),
                'client_id' => $row->client_id,
                'created_by' => $reviewer,
                'assigned_to' => $reviewer,
                'status' => 'draft',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays((int) setting('proposal.valid_days', 15))->toDateString(),
                'gst_rate' => 0,
                'subtotal' => $amount,
                'taxable_value' => $amount,
                'total' => $amount,
                'notes' => 'Auto-drafted renewal: '.$row->title.'. Renewal date '.d($row->renewal_date, 'j M Y').'.',
                'sign_token' => Str::random(32),
            ]);
            $proposal->items()->create([
                'description' => $row->title.' — renewal',
                'qty' => 1, 'unit' => 'NOS', 'rate' => $amount,
                'amount' => $amount, 'tax_rate' => 0, 'billing' => 'once',
            ]);
            $proposal->recalc();
            $claimed = Renewal::whereKey($row->id)->whereNull('proposal_id')
                ->update(['status' => 'drafted', 'proposal_id' => $proposal->id]);
            if (! $claimed) {
                $proposal->items()->delete();
                $proposal->delete();
                throw new \RuntimeException('This renewal was drafted in another request.');
            }

            return $proposal;
        });
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
                $before = $r->proposal_id;
                try {
                    $this->draft($r);
                    if ($r->fresh()->proposal_id !== $before) $made++;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Renewal auto-draft failed', [
                        'renewal_id' => $r->id, 'error' => $e->getMessage(),
                    ]);
                }
            });

        return $made;
    }
}
