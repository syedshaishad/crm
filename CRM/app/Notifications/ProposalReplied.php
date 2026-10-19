<?php

namespace App\Notifications;

use Modules\Proposals\Proposal;

class ProposalReplied extends DatabaseNotification
{
    public function __construct(Proposal $proposal)
    {
        parent::__construct(
            'Proposal reply',
            $proposal->number.' — '.($proposal->client->company ?: $proposal->client->name).' asked a question',
            route('proposals.show', $proposal)
        );
    }
}
