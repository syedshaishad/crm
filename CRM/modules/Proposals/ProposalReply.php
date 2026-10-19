<?php

namespace Modules\Proposals;

use Illuminate\Database\Eloquent\Model;

class ProposalReply extends Model
{
    protected $fillable = ['proposal_id', 'side', 'author', 'body'];
}
