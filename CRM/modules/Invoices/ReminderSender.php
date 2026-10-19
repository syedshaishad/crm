<?php

namespace Modules\Invoices;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Branding\Brand;

/**
 * One sender for overdue nudges, used by the nightly command (auto)
 * and the invoice page button (manual). Every attempt is journaled to
 * reminder_logs; last_nudged_at moves only on success.
 */
class ReminderSender
{
    /** @return array{ok: bool, message: string} */
    public static function send(Invoice $invoice, string $source = 'manual', int $level = 1): array
    {
        $level = max(1, min(3, $level));
        $notice = [1 => 'Friendly reminder: ', 2 => 'Second notice: ', 3 => 'Final notice: '][$level];
        $invoice->loadMissing('client');
        $email = trim((string) ($invoice->client->email ?? ''));
        if ($email === '') {
            return ['ok' => false, 'message' => 'Client has no email address.'];
        }
        $balance = $invoice->balance();
        if ($balance <= 0) {
            return ['ok' => false, 'message' => 'Nothing due — invoice is settled.'];
        }
        if (! $invoice->public_token) {
            $invoice->update(['public_token' => Str::random(32)]);
        }
        try {
            $brand = app(Brand::class)->all();
        } catch (\Throwable $e) {
            $brand = ['company_name' => config('app.name')];
        }
        $data = [
            'company_name' => $brand['company_name'] ?? config('app.name'), 'client_name' => $invoice->client->name,
            'invoice_number' => $invoice->number, 'total' => money($invoice->total), 'balance' => money($balance),
            'due_date' => $invoice->due_date ? d($invoice->due_date, 'j M Y') : 'on receipt',
            'link' => route('invoices.public', $invoice->public_token),
            'notice' => $notice, 'level' => $level,
        ];
        try {
            Mail::send('emails.template', ['body' => render_template('email.overdue_body', $data)], function ($m) use ($email, $invoice, $data) {
                $m->to($email, $invoice->client->name)->subject(render_template('email.overdue_subject', $data));
            });
        } catch (\Throwable $e) {
            DB::table('reminder_logs')->insert([
                'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id, 'channel' => 'email',
                'recipient' => $email, 'source' => $source, 'status' => 'failed',
                'error' => substr($e->getMessage(), 0, 500), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['ok' => false, 'message' => 'Could not send email: '.$e->getMessage().' — check Settings → Integrations (SMTP).'];
        }
        DB::table('reminder_logs')->insert([
            'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id, 'channel' => 'email',
            'recipient' => $email, 'source' => $source, 'status' => 'sent',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoice->update(['last_nudged_at' => now(), 'dunning_level' => max((int) ($invoice->dunning_level ?? 0), $level)]);

        return ['ok' => true, 'message' => ($level > 1 ? $notice : '').'Reminder sent to '.$email];
    }
}
