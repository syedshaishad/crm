<?php

namespace Modules\Invoices;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Branding\Brand;

/** One sender for invoice emails (manual button + recurring auto-send). */
class InvoiceMailer
{
    /** @return array{ok: bool, message: string} */
    public static function send(Invoice $invoice): array
    {
        $invoice->loadMissing(['items', 'client']);
        if (empty($invoice->client->email)) {
            return ['ok' => false, 'message' => 'Client has no email address.'];
        }
        if (! $invoice->public_token) {
            $invoice->update(['public_token' => Str::random(32)]);
        }
        try {
            $brand = app(Brand::class)->all();
        } catch (\Throwable $e) {
            $brand = ['company_name' => config('app.name')];
        }
        $balance = $invoice->balance();
        $data = [
            'company_name' => $brand['company_name'] ?? config('app.name'), 'client_name' => $invoice->client->name,
            'invoice_number' => $invoice->number,
            'items' => $invoice->items->map(fn ($it) => $it->description.' — '.$it->qty.' × '.money($it->rate).' = '.money($it->amount))->implode("\n"),
            'subtotal' => money($invoice->subtotal), 'gst' => money($invoice->gst_amount),
            'total' => money($invoice->total), 'paid' => money($invoice->paid_amount), 'balance' => money($balance),
            'due_date' => $invoice->due_date ? d($invoice->due_date, 'j F Y') : 'on receipt',
            'link' => route('invoices.public', $invoice->public_token),
        ];
        try {
            Mail::send('emails.template', ['body' => render_template('email.invoice_body', $data)], function ($m) use ($invoice, $data) {
                $m->to($invoice->client->email, $invoice->client->name)
                    ->subject(render_template('email.invoice_subject', $data));
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not send email: '.$e->getMessage().' — check Settings → Integrations (SMTP).'];
        }

        return ['ok' => true, 'message' => 'Invoice emailed to '.$invoice->client->email.'.'];
    }
}
