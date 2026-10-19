<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Invoices\Invoice;
use Modules\Invoices\ReminderSender;
use Modules\Settings\SettingStore;

class SendReminders extends Command
{
    protected $signature = 'reminders:send {--dry-run : Print the digest instead of emailing}';

    protected $description = 'Morning digest: follow-ups, due/overdue tasks, overdue invoices, expiring proposals.';

    public function handle(): int
    {
        $store = app(SettingStore::class);
        if (! $this->option('dry-run') && $store->get('reminders.enabled', '0') !== '1') {
            $this->info('Reminders disabled (Settings → General). Nothing sent.');

            return self::SUCCESS;
        }
        $today = now()->toDateString();
        $lines = [];
        $this->stampApprovedLeave($today);
        if (! $this->option('dry-run')) {
            $autoN = \Modules\Projects\AutomationEngine::run();
            if ($autoN > 0) {
                $this->info('Automations fired '.$autoN.' time(s).');
            }
        }

        $yday = now()->subDay()->toDateString();
        $yGot = (float) DB::table('payments')->whereDate('paid_on', $yday)->sum('amount');
        $yCount = (int) DB::table('payments')->whereDate('paid_on', $yday)->count();
        $dueToday = DB::table('invoices')->whereNotIn('status', ['paid', 'cancelled', 'draft'])
            ->whereDate('due_date', $today)->selectRaw('COUNT(*) c, COALESCE(SUM(total - paid_amount),0) b')->first();
        $lines[] = '• Yesterday: '.money($yGot).' collected ('.$yCount.' receipts) to start the day.';
        if (($dueToday->c ?? 0) > 0) {
            $lines[] = '• Due today: '.money(max(0, $dueToday->b)).' across '.$dueToday->c.' invoice(s) — chase before EOD.';
        }

        $followups = DB::table('leads')->whereNotIn('stage', ['won', 'lost'])
            ->whereNotNull('follow_up_on')->where('follow_up_on', '<=', now()->addDays(1)->toDateString())
            ->orderBy('follow_up_on')->limit(20)->get(['name', 'company', 'phone', 'follow_up_on']);
        foreach ($followups as $l) {
            $when = $l->follow_up_on < $today ? 'OVERDUE since '.d($l->follow_up_on, 'j M Y') : 'due '.d($l->follow_up_on, 'j M Y');
            $lines[] = '• Follow up: '.($l->company ?: $l->name).($l->phone ? ' ('.$l->phone.')' : '').' — '.$when;
        }
        // Nurture: open leads gone quiet (no touch for staleDays) that have no follow-up date at all.
        $cutoff = now()->subDays(\Modules\Leads\Lead::staleDays())->toDateTimeString();
        $stale = DB::table('leads')->whereNotIn('stage', ['won', 'lost'])->whereNull('follow_up_on')
            ->where('updated_at', '<', $cutoff)
            ->orderBy('updated_at')->limit(10)->get(['name', 'company', 'phone', 'stage', 'updated_at']);
        foreach ($stale as $l) {
            $lines[] = '• Gone stale ('.$l->stage.', quiet since '.d($l->updated_at, 'j M Y').'): '.($l->company ?: $l->name).($l->phone ? ' ('.$l->phone.')' : '').' — bump or reassign.';
        }
        $pendingLeave = DB::table('leaves')->join('users', 'users.id', '=', 'leaves.user_id')
            ->where('leaves.status', 'pending')->orderBy('leaves.from_date')->limit(10)
            ->get(['users.name', 'leaves.from_date', 'leaves.to_date', 'leaves.type']);
        foreach ($pendingLeave as $l) {
            $lines[] = '• Leave pending: '.$l->name.' ('.$l->type.', '.d($l->from_date, 'j M Y').' → '.d($l->to_date, 'j M Y').') — approve in Work → Leave.';
        }
        $certs = DB::table('user_skills')->join('users', 'users.id', '=', 'user_skills.user_id')->join('skills', 'skills.id', '=', 'user_skills.skill_id')
            ->where('user_skills.certified', true)->whereNotNull('user_skills.cert_expires_on')
            ->whereDate('user_skills.cert_expires_on', '>=', $today)->whereDate('user_skills.cert_expires_on', '<=', now()->addDays(30)->toDateString())
            ->orderBy('user_skills.cert_expires_on')->limit(10)->get(['users.name', 'skills.name as skill', 'user_skills.cert_expires_on']);
        foreach ($certs as $c) {
            $lines[] = '• Cert expiring: '.$c->name.' — '.$c->skill.' lapses '.d($c->cert_expires_on, 'j M Y').' — schedule renewal training.';
        }
        $meets = DB::table('meetings')->whereDate('starts_at', $today)->orderBy('starts_at')->limit(10)->get(['title', 'starts_at', 'duration_minutes']);
        foreach ($meets as $m) {
            $lines[] = '• Meeting today '.d($m->starts_at, 'h:i A').': '.$m->title.' ('.$m->duration_minutes.'m).';
        }

        $tasks = DB::table('tasks')->whereNotIn('status', ['done'])
            ->whereNotNull('due_date')->where('due_date', '<=', $today)
            ->orderBy('due_date')->limit(20)->get(['title', 'due_date']);
        foreach ($tasks as $t) {
            $lines[] = '• Task '.($t->due_date < $today ? 'OVERDUE' : 'due today').': '.$t->title.' ('.d($t->due_date, 'j M Y').')';
        }

        $overdue = DB::table('invoices')->where('status', 'overdue')
            ->orderBy('due_date')->limit(20)->get(['number', 'client_id', 'total', 'paid_amount', 'due_date']);
        foreach ($overdue as $i) {
            $c = DB::table('clients')->where('id', $i->client_id)->first();
            $bal = max(0, (float) $i->total - (float) $i->paid_amount);
            $lines[] = '• Overdue invoice '.$i->number.' — '.($c->company ?? $c->name ?? '').' owes '.money($bal).' (due '.d($i->due_date, 'j M Y').')';
        }

        $expiring = DB::table('proposals')->where('status', 'sent')->whereNotNull('valid_until')
            ->where('valid_until', '<=', now()->addDays(3)->toDateString())
            ->orderBy('valid_until')->limit(20)->get(['number', 'client_id', 'total', 'valid_until']);
        foreach ($expiring as $p) {
            $c = DB::table('clients')->where('id', $p->client_id)->first();
            $lines[] = '• Proposal '.$p->number.' ('.($c->company ?? $c->name ?? '').', '.money($p->total).') expires '.d($p->valid_until, 'j M Y');
        }

        $amc = DB::table('amc_contracts')->join('clients', 'clients.id', '=', 'amc_contracts.client_id')
            ->whereNotNull('amc_contracts.end_date')
            ->where('amc_contracts.end_date', '<=', now()->addDays(30)->toDateString())
            ->orderBy('amc_contracts.end_date')->limit(20)
            ->get(['amc_contracts.number', 'amc_contracts.title', 'amc_contracts.end_date', 'clients.company', 'clients.name']);
        foreach ($amc as $a) {
            $when = $a->end_date < $today ? 'EXPIRED '.d($a->end_date, 'j M Y') : 'ends '.d($a->end_date, 'j M Y');
            $lines[] = '• AMC '.$a->number.' ('.($a->company ?: $a->name).' — '.$a->title.') '.$when;
        }

        $pendExp = DB::table('expenses')->where('status', 'pending')->orderBy('spent_on')->limit(20)->get(['number', 'title', 'amount', 'spent_on']);
        foreach ($pendExp as $e) {
            $lines[] = '• Expense '.$e->number.' ('.$e->title.', '.money($e->amount).') awaits manager approval';
        }
        // Birthdays in the next 7 days (month/day match, any year).
        $md = [];
        for ($d = 0; $d < 7; $d++) {
            $md[] = now()->addDays($d)->format('m-d');
        }
        $bday = DB::table('clients')->whereNotNull('birthday')->orderBy('name')->limit(200)->get(['name', 'company', 'birthday']);
        foreach ($bday as $c) {
            if (in_array(substr((string) $c->birthday, 5, 5), $md, true)) {
                $when = substr((string) $c->birthday, 5, 5) === now()->format('m-d') ? 'TODAY' : d($c->birthday, 'j M');
                $lines[] = '• Birthday '.$when.': '.($c->company ?: $c->name).' — send greetings';
            }
        }

        $chqs = DB::table('pdc_cheques')->join('clients', 'clients.id', '=', 'pdc_cheques.client_id')            ->whereIn('pdc_cheques.status', ['held', 'bounced'])
            ->where('pdc_cheques.cheque_date', '<=', now()->addDays((int) $store->get('cheques.alert_days', 7))->toDateString())
            ->orderBy('pdc_cheques.cheque_date')->limit(20)
            ->get(['pdc_cheques.number', 'pdc_cheques.cheque_no', 'pdc_cheques.amount', 'pdc_cheques.cheque_date', 'pdc_cheques.status', 'clients.company', 'clients.name']);
        foreach ($chqs as $c) {
            $what = $c->status === 'bounced' ? 'BOUNCED — chase' : ($c->cheque_date < $today ? 'due — deposit now' : 'due '.d($c->cheque_date, 'j M Y'));
            $lines[] = '• Cheque '.$c->number.' ('.($c->company ?: $c->name).', '.$c->cheque_no.', '.money($c->amount).') '.$what;
        }

        $docs = DB::table('expiry_documents')->whereNotNull('valid_to')
            ->orderBy('valid_to')->limit(200)->get(['number', 'title', 'category', 'valid_to', 'remind_days']);
        foreach ($docs as $x) {            $edge = \Carbon\Carbon::parse($x->valid_to)->subDays((int) ($x->remind_days ?: 30))->toDateString();
            if ($edge > $today) {
                continue;
            }
            $when = $x->valid_to < $today ? 'EXPIRED '.d($x->valid_to, 'j M Y') : 'due '.d($x->valid_to, 'j M Y');
            $lines[] = '• '.ucfirst(str_replace('_', ' ', $x->category)).' '.$x->number.' ('.$x->title.') '.$when;
            if (count($lines) > 60) {
                break;
            }
        }

        if (! $lines) {
            $this->info('Nothing due. No digest sent.');

            return self::SUCCESS;
        }

        $data = ['app_name' => config('app.name'), 'date' => d($today, 'j M Y'), 'lines' => implode("\n", $lines)];
        $subject = render_template('email.reminder_subject', $data);
        $body = render_template('email.reminder_body', $data);

        if ($this->option('dry-run')) {
            $this->info('Subject: '.$subject);
            $this->line($body);
            $this->nudgeClients($store);

            return self::SUCCESS;
        }

        $to = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $store->get('reminders.recipients', ''))))));
        $to = array_filter($to, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        if (! $to) {
            $this->error('No valid recipients (Settings → General → Morning digest).');

            return self::FAILURE;
        }
        try {
            Mail::send('emails.template', ['body' => $body], function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            $this->error('Mail failed: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info('Digest sent to '.implode(', ', $to).' ('.count($lines).' items).');
        $this->nudgeClients($store);

        return self::SUCCESS;
    }

    /**
     * Daily housekeeping: stamp today's approved leave onto attendance
     * (approvals made in advance land when the day arrives).
     */
    protected function stampApprovedLeave(string $today): void
    {
        $rows = \Modules\Work\Leave::where('status', 'approved')
            ->whereDate('from_date', '<=', $today)->whereDate('to_date', '>=', $today)->get();
        foreach ($rows as $l) {
            \Modules\Work\Attendance::mark($l->user_id, $today, 'leave', 'Approved '.\Modules\Work\Leave::types()[$l->type].' leave');
        }
        if ($rows->isNotEmpty() && ! $this->option('dry-run')) {
            $this->info('Stamped '.$rows->count().' approved leave(s) onto attendance.');
        }
    }

    /** Overdue nudges straight to clients (max once every 3 days per invoice). */
    protected function nudgeClients($store): void
    {
        if (! $this->option('dry-run') && $store->get('reminders.clients', '0') !== '1') {
            return;
        }
        // Collection ladder: gentle → firm → final as days-overdue passes each threshold.
        $ladder = collect(explode(',', (string) $store->get('collections.dunning_days', '7,15,30')))
            ->map(fn ($d) => (int) trim($d))->filter(fn ($d) => $d > 0)->sort()->values();
        $due = Invoice::with('client')
            ->where('status', 'overdue')
            ->where(function ($q) {
                $q->whereNull('last_nudged_at')->orWhere('last_nudged_at', '<', now()->subDays(3)->toDateTimeString());
            })
            ->orderBy('due_date')->limit(50)->get();
        foreach ($due as $inv) {
            if (empty($inv->client->email)) {
                continue;
            }
            $bal = max(0, round((float) $inv->total - (float) $inv->paid_amount - (float) ($inv->writeoff_amount ?? 0), 2));
            if ($bal <= 0) {
                continue;
            }
            $daysOver = $inv->due_date ? max(0, (int) now()->diffInDays($inv->due_date, true)) : 0;
            $level = 1;
            foreach ($ladder as $i => $day) {
                if ($daysOver >= $day) {
                    $level = min(3, $i + 2);
                }
            }
            if ($this->option('dry-run')) {
                $this->line('Would nudge '.$inv->client->email.' ('.$inv->number.', '.money($bal).' due, level '.$level.').');
                if ($store->get('whatsapp.auto', '0') === '1') {
                    $this->line('Would WhatsApp-nudge '.$inv->number.'.');
                }

                continue;
            }
            $res = ReminderSender::send($inv, 'auto', $level);
            if ($res['ok']) {
                $this->info('Nudged '.$inv->client->email.' ('.$inv->number.').');
            } else {
                $this->error('Nudge failed for '.$inv->number.': '.$res['message']);
            }
            // WhatsApp twin: same overdue set, own toggle + own 3-day throttle.
            if (! $this->option('dry-run')
                && $store->get('whatsapp.auto', '0') === '1'
                && \Modules\Core\WhatsApp\WhatsAppSender::configured($store)
                && (! $inv->last_whatsapp_at || $inv->last_whatsapp_at->lt(now()->subDays(3)))) {
                $w = \Modules\Core\WhatsApp\WhatsAppSender::sendReminder($inv->fresh(), 'auto');
                if ($w['ok']) {
                    $this->info('WhatsApp nudged '.$inv->number.'.');
                } else {
                    $this->error('WhatsApp failed for '.$inv->number.': '.$w['message']);
                }
            }
        }
        // Proposal expiry nudges: sent quotes dying within 3 days, same toggle + throttle.
        $dying = \Modules\Proposals\Proposal::with('client')->where('status', 'sent')->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', now()->toDateString())
            ->whereDate('valid_until', '<=', now()->addDays(3)->toDateString())
            ->where(function ($q) {
                $q->whereNull('last_followed_up_at')->orWhere('last_followed_up_at', '<', now()->subDays(3)->toDateTimeString());
            })
            ->orderBy('valid_until')->limit(50)->get();
        foreach ($dying as $prop) {
            if (empty($prop->client->email)) {
                continue;
            }
            if ($this->option('dry-run')) {
                $this->line('Would nudge '.$prop->client->email.' ('.$prop->number.' expires '.d($prop->valid_until, 'j M Y').').');

                continue;
            }
            $res = \Modules\Proposals\ProposalFollowupSender::send($prop, 'auto');
            if ($res['ok']) {
                $this->info('Nudged '.$prop->client->email.' ('.$prop->number.').');
            } else {
                $this->error('Nudge failed for '.$prop->number.': '.$res['message']);
            }
        }
        // Quote follow-up sequence: auto email on configured ages (default 3,7,14 days
        // after issue). Runs only if the quote wasn't already touched today, so the
        // expiry nudge above and this sequence never double-send on the same day.
        $seqDays = collect(explode(',', (string) $store->get('proposal.followup_days', '3,7,14')))
            ->map(fn ($d) => (int) trim($d))->filter(fn ($d) => $d > 0)->unique()->values();
        if ($seqDays->isNotEmpty()) {
            $seq = \Modules\Proposals\Proposal::with('client')->where('status', 'sent')
                ->where(function ($q) {
                    $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString());
                })
                ->where(function ($q) {
                    $q->whereNull('last_followed_up_at')->orWhere('last_followed_up_at', '<', now()->startOfDay());
                })
                ->orderBy('issue_date')->limit(100)->get()
                ->filter(fn ($p) => $seqDays->contains((int) now()->diffInDays($p->issue_date, true)));
            foreach ($seq as $prop) {
                if (empty($prop->client->email)) {
                    continue;
                }
                if ($this->option('dry-run')) {
                    $this->line('Would sequence-follow-up '.$prop->client->email.' ('.$prop->number.', day '.(int) now()->diffInDays($prop->issue_date, true).').');

                    continue;
                }
                $res = \Modules\Proposals\ProposalFollowupSender::send($prop, 'sequence');
                if ($res['ok']) {
                    $this->info('Sequence-followed '.$prop->client->email.' ('.$prop->number.').');
                } else {
                    $this->error('Sequence failed for '.$prop->number.': '.$res['message']);
                }
            }
        }
    }
}
