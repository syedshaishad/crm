<?php

namespace Modules\Work;

use Illuminate\Database\Eloquent\Model;

class PollVote extends Model
{
    protected $fillable = ['poll_id', 'score', 'comment', 'voter_hash'];
}
