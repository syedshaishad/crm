<?php

namespace App\Notifications;

use Modules\Leads\Lead;

class LeadWon extends DatabaseNotification
{
    public function __construct(Lead $lead)
    {
        parent::__construct(
            'Deal won',
            ($lead->company ?: $lead->name).' — '.money($lead->value),
            route('leads.index')
        );
    }
}
