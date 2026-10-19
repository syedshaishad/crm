<?php

namespace App\Notifications;

use Modules\Invoices\Invoice;

class InvoiceOverdue extends DatabaseNotification
{
    public function __construct(Invoice $invoice)
    {
        $bal = max(0, (float) $invoice->total - (float) $invoice->paid_amount);
        parent::__construct(
            'Invoice overdue',
            $invoice->number.' — '.money($bal).' past due since '.$invoice->due_date,
            route('invoices.show', $invoice)
        );
    }
}
