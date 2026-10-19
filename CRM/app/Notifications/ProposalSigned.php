<?php

namespace App\Notifications;

use Modules\Proposals\Proposal;

class ProposalSigned extends DatabaseNotification
{
    public function __construct(Proposal $proposal)
    {
        parent::__construct(
            'Proposal accepted',
            $proposal->number.' — '.($proposal->client->company ?: $proposal->client->name).' signed the deal',
            route('proposals.show', $proposal)
        );
    }
}
