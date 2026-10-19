<?php

namespace Modules\Proposals;

use Illuminate\Database\Eloquent\Model;

class ProposalTemplate extends Model
{
    protected $fillable = ['name', 'gst_rate', 'discount', 'place_of_supply', 'notes', 'scope', 'results', 'terms', 'items', 'created_by'];

    protected $casts = ['items' => 'array'];
}
