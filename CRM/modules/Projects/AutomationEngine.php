<?php

namespace Modules\Projects;

use Illuminate\Support\Facades\DB;
use Modules\Leads\Lead;
use Modules\Proposals\Proposal;
use Modules\Tasks\Task;

/**
 * Tiny process engine: triggers evaluated nightly, actions applied once
 * per subject per day (firing log), so rules nudge instead of spam.
 */
class AutomationEngine
{
    public static function run(?callable $log = null): int
    {
        $log ??= function () {};
        $today = now()->toDateString();
        $fired = 0;
        foreach (WorkflowRule::where('is_active', true)->get() as $rule) {
            foreach (self::matches($rule, $today) as [$type, $id, $label]) {
                $dup = DB::table('automation_firings')->where('rule_id', $rule->id)
                    ->where('subject_type', $type)->where('subject_id', $id)->where('fired_on', $today)->exists();
                if ($dup) {
                    continue;
                }
                self::act($rule, $type, $id, $label);
                DB::table('automation_firings')->insert([
                    'rule_id' => $rule->id, 'subject_type' => $type, 'subject_id' => $id,
                    'fired_on' => $today, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $fired++;
                $log('Rule "'.$rule->name.'": '.$label);
            }
        }

        return $fired;
    }

    protected static function matches(WorkflowRule $rule, string $today): array
    {
        $cut = \Carbon\Carbon::parse($today)->subDays((int) $rule->after_days)->toDateString();
        $out = [];
        switch ($rule->trigger) {
            case 'task.overdue':
                foreach (Task::whereNotIn('status', ['done'])->whereNotNull('due_date')->whereDate('due_date', '<=', $cut)->limit(100)->get(['id', 'title', 'due_date']) as $t) {
                    $out[] = ['task', $t->id, 'Task "'.$t->title.'" overdue since '.d($t->due_date)];
                }
                break;
            case 'invoice.overdue':
                foreach (\Modules\Invoices\Invoice::with('client')->where('status', 'overdue')->whereDate('due_date', '<=', $cut)->limit(100)->get() as $i) {
                    $out[] = ['invoice', $i->id, 'Invoice '.$i->number.' ('.($i->client->company ?? $i->client->name ?? '').') overdue'];
                }
                break;
            case 'lead.stale':
                foreach (Lead::whereNotIn('stage', ['won', 'lost'])->limit(300)->get()->filter->isStale()->take(100) as $l) {
                    $out[] = ['lead', $l->id, 'Lead '.($l->company ?: $l->name).' gone stale'];
                }
                break;
            case 'proposal.expiring':
                foreach (Proposal::with('client')->where('status', 'sent')->whereNotNull('valid_until')->whereDate('valid_until', '>=', $today)->whereDate('valid_until', '<=', \Carbon\Carbon::parse($today)->addDays(max(1, (int) $rule->after_days))->toDateString())->limit(100)->get() as $p) {
                    $out[] = ['proposal', $p->id, 'Proposal '.$p->number.' expires '.d($p->valid_until)];
                }
                break;
        }

        return $out;
    }

    protected static function act(WorkflowRule $rule, string $type, int $id, string $label): void
    {
        if ($rule->action === 'notify') {
            notify_roles(['admin', 'manager'], new \App\Notifications\AutomationFired($rule->name, $label));
        } elseif ($rule->action === 'bump' && $type === 'task') {
            $t = Task::find($id);
            if ($t) {
                $ladder = ['low' => 'medium', 'medium' => 'high', 'high' => 'urgent', 'urgent' => 'urgent'];
                $t->update(['priority' => $ladder[$t->priority] ?? 'medium']);
            }
        } elseif ($rule->action === 'reassign' && $rule->reassign_to) {
            if ($type === 'task' && ($t = Task::find($id))) {
                $t->update(['assigned_to' => $rule->reassign_to]);
            } elseif ($type === 'lead' && ($l = Lead::find($id))) {
                $l->update(['assigned_to' => $rule->reassign_to]);
            }
        }
    }
}
