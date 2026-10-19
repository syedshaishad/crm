<?php

namespace App\Notifications;

class AutomationFired extends DatabaseNotification
{
    public function __construct(string $rule, string $detail)
    {
        parent::__construct('Automation: '.$rule, $detail, route('automations.index'));
    }
}
