<?php

namespace Modules\Renewals;

use Illuminate\Database\Eloquent\Model;

class Renewal extends Model
{
    protected $fillable = [
        'source_type', 'source_id', 'client_id', 'title', 'renewal_date',
        'amount', 'status', 'proposal_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'renewal_date' => 'date',
        'amount' => 'float',
    ];

    public function client()
    {
        return $this->belongsTo(\Modules\Clients\Client::class);
    }

    public function proposal()
    {
        return $this->belongsTo(\Modules\Proposals\Proposal::class);
    }

    public function daysAway(): ?int
    {
        return $this->renewal_date ? (int) now()->startOfDay()->diffInDays($this->renewal_date, false) : null;
    }

    public function risk(): string
    {
        $days = $this->daysAway();
        if ($this->status === 'won') return 'won';
        if ($days === null) return 'unknown';
        if ($days < 0) return 'overdue';
        if ($days <= 45) return 'hot';
        if ($days <= 90) return 'warm';
        return 'cool';
    }
}
