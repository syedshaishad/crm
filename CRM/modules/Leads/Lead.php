<?php

namespace Modules\Leads;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Clients\Client;

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'company', 'phone', 'email', 'city', 'state', 'source', 'stage', 'value', 'follow_up_on', 'notes', 'assigned_to', 'converted_client_id'];

    protected $casts = ['follow_up_on' => 'date'];

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedClient()
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    protected static function booted(): void
    {
        static::updated(function (Lead $l) {
            if (! $l->wasChanged('stage') || $l->stage !== 'won') {
                return;
            }
            \Modules\Core\Webhooks\Webhook::fire('lead.won', [
                'name' => $l->name, 'company' => $l->company, 'value' => (float) ($l->value ?? 0),
            ]);
        });
    }

    public static function stages(): array
    {
        return ['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'proposal' => 'Proposal', 'won' => 'Won', 'lost' => 'Lost'];
    }

    /** Days of silence before an open lead counts as stale. */
    public static function staleDays(): int
    {
        return max(1, (int) setting('leads.stale_days', 7));
    }

    /** Nurture score 0-100: stage base + deal size, minus silence penalty. */
    public function score(): int
    {
        $base = ['new' => 10, 'contacted' => 25, 'qualified' => 50, 'proposal' => 75, 'won' => 100, 'lost' => 0][$this->stage] ?? 10;
        $size = min(20, (int) floor(((float) ($this->value ?? 0)) / 10000));
        $adj = 0;
        if (! in_array($this->stage, ['won', 'lost'], true)) {
            if ($this->follow_up_on) {
                $adj = $this->follow_up_on->isPast() ? -15 : 5;
            } else {
                $adj = -5;
            }
        }

        return max(0, min(100, $base + $size + $adj));
    }

    /** Open leads gone quiet: follow-up overdue, or no follow-up and untouched for staleDays. */
    public function isStale(): bool
    {
        if (in_array($this->stage, ['won', 'lost'], true)) {
            return false;
        }
        if ($this->follow_up_on) {
            return $this->follow_up_on->isPast();
        }

        return $this->updated_at && $this->updated_at->lt(now()->subDays(self::staleDays()));
    }

    public static function defaultSources(): array
    {
        return ['website', 'indiamart', 'justdial', 'google', 'referral', 'walkin', 'instagram', 'facebook', 'whatsapp', 'coldcall', 'other'];
    }
}
