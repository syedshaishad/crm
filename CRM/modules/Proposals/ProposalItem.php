<?php

namespace Modules\Proposals;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProposalItem extends Model
{
    use SoftDeletes;

    protected $fillable = ['proposal_id', 'description', 'hsn_code', 'qty', 'unit', 'rate', 'discount_percent', 'tax_rate', 'billing', 'amount'];

    /** Line total after the per-item discount (GST is computed on this). */
    public function netAmount(): float
    {
        $pct = max(0, min(100, (float) ($this->discount_percent ?? 0)));

        return round((float) $this->amount * (1 - $pct / 100), 2);
    }
}
