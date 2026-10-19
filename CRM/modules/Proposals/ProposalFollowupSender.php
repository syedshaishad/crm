<?php

namespace Modules\Proposals;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Branding\Brand;

/**
 * One sender for proposal follow-ups (manual button on the proposal page).
 * Every attempt is journaled to proposal_followup_logs; the throttle stamp
 * moves only on success. Mirrors Modules\Invoices\ReminderSender.
 */
class ProposalFollowupSender
{
    /** @return array{ok: bool, message: string} */
    public static function send(Proposal $proposal, string $source = 'manual'): array
    {
        $proposal->loadMissing('client');
        $email = trim((string) ($proposal->client->email ?? ''));
        if ($email === '') {
            return ['ok' => false, 'message' => 'Client has no email address.'];
        }
        if ($proposal->status !== 'sent') {
            return ['ok' => false, 'message' => 'Only open (sent) proposals can be followed up.'];
        }
        if ($proposal->valid_until && $proposal->valid_until->isPast()) {
            return ['ok' => false, 'message' => 'This proposal has expired — send a fresh one instead.'];
        }
        try {
            $brand = app(Brand::class)->all();
        } catch (\Throwable $e) {
            $brand = ['company_name' => config('app.name')];
        }
        $data = [
            'company_name' => $brand['company_name'] ?? config('app.name'), 'client_name' => $proposal->client->name,
            'proposal_number' => $proposal->number, 'total' => money($proposal->total),
            'valid_until' => $proposal->valid_until ? d($proposal->valid_until, 'j M Y') : '—',
            'link' => route('proposals.sign', $proposal->sign_token),
        ];
        try {
            Mail::send('emails.template', ['body' => render_template('email.followup_body', $data)], function ($m) use ($email, $proposal, $data) {
                $m->to($email, $proposal->client->name)->subject(render_template('email.followup_subject', $data));
            });
        } catch (\Throwable $e) {
            DB::table('proposal_followup_logs')->insert([
                'proposal_id' => $proposal->id, 'client_id' => $proposal->client_id, 'channel' => 'email',
                'recipient' => $email, 'source' => $source, 'status' => 'failed',
                'error' => substr($e->getMessage(), 0, 500), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['ok' => false, 'message' => 'Could not send email: '.$e->getMessage().' — check Settings → Integrations (SMTP).'];
        }
        DB::table('proposal_followup_logs')->insert([
            'proposal_id' => $proposal->id, 'client_id' => $proposal->client_id, 'channel' => 'email',
            'recipient' => $email, 'source' => $source, 'status' => 'sent',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $proposal->update(['last_followed_up_at' => now()]);

        return ['ok' => true, 'message' => 'Follow-up sent to '.$email];
    }
}
