<?php

namespace App\Notifications;

use Modules\Payments\Payment;

class PaymentReceived extends DatabaseNotification
{
    public function __construct(Payment $payment)
    {
        $inv = $payment->invoice;
        parent::__construct(
            'Payment received',
            money($payment->amount).' from '.($payment->client->company ?: $payment->client->name)
            .($inv ? ' for '.$inv->number : ''),
            route('payments.show', $payment)
        );
    }
}
