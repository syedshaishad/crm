<?php

namespace Modules\Projects\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Projects\AutomationEngine;
use Modules\Projects\WorkflowRule;

class AutomationController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    public function index(Request $r)
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers run automations.');
        }

        $q = trim((string) $r->query('q', ''));
        $state = (string) $r->query('state', '');
        $trigger = (string) $r->query('trigger', '');

        $allRules = WorkflowRule::with('target')->orderByDesc('id')->get();
        $rules = $allRules;

        if ($q !== '') {
            $rules = $rules->filter(fn ($rule) => mb_stripos($rule->name, $q) !== false);
        }
        if ($state === 'live' || $state === 'paused') {
            $rules = $rules->filter(fn ($rule) => $state === 'live' ? $rule->is_active : ! $rule->is_active);
        }
        if (array_key_exists($trigger, WorkflowRule::triggers())) {
            $rules = $rules->filter(fn ($rule) => $rule->trigger === $trigger);
        }

        return view('automations.index', [
            'rules' => $rules->values(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'firings' => \DB::table('automation_firings')->join('workflow_rules', 'workflow_rules.id', '=', 'automation_firings.rule_id')->orderByDesc('automation_firings.id')->limit(30)->get(['automation_firings.*', 'workflow_rules.name as rule']),
            'lastFired' => \DB::table('automation_firings')->select('rule_id', \DB::raw('MAX(fired_on) as last_on'))->groupBy('rule_id')->pluck('last_on', 'rule_id'),
            'stats' => [
                'total' => $allRules->count(),
                'live' => $allRules->where('is_active', true)->count(),
                'paused' => $allRules->where('is_active', false)->count(),
                'firings' => \DB::table('automation_firings')->count(),
            ],
            'q' => $q,
            'state' => $state,
            'trigger' => $trigger,
        ]);
    }

    public function store(Request $r)
    {
        $this->authorizeManager();
        $data = $r->validate([
            'name' => 'required|string|max:150',
            'trigger' => 'required|in:task.overdue,invoice.overdue,lead.stale,proposal.expiring',
            'after_days' => 'nullable|integer|min:0|max:365',
            'action' => 'required|in:notify,bump,reassign',
            'reassign_to' => 'nullable|exists:users,id',
        ]);
        if ($data['action'] === 'reassign' && empty($data['reassign_to'])) {
            return back()->withErrors(['reassign_to' => 'Pick who receives the work.'])->withInput();
        }
        WorkflowRule::create([...$data, 'after_days' => $data['after_days'] ?? 0, 'is_active' => true]);

        return back()->with('ok', 'Automation saved — evaluates every morning.');
    }

    public function toggle(WorkflowRule $rule)
    {
        $this->authorizeManager();
        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('ok', $rule->is_active ? 'Automation resumed.' : 'Automation paused.');
    }

    public function destroy(WorkflowRule $rule)
    {
        $this->authorizeManager();
        $rule->delete();

        return back()->with('ok', 'Automation removed.');
    }

    public function runNow()
    {
        $this->authorizeManager();
        $lines = [];
        $n = AutomationEngine::run(function ($m) use (&$lines) { $lines[] = $m; });

        return back()->with('ok', $n === 0 ? 'Evaluated — nothing matched right now.' : 'Fired '.$n.' time(s).')->with('automation_lines', $lines);
    }

    protected function authorizeManager(): void
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers run automations.');
        }
    }
}
