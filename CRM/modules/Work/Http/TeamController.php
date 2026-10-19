<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tasks\Task;
use Modules\Tasks\TaskTime;

class TeamController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    /**
     * Who is drowning, who has room: open load + hours logged this week
     * against each person's weekly capacity.
     */
    public function load(Request $r)
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers see the full team load.');
        }
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();
        $defaultCap = (int) setting('team.weekly_hours', 48);
        $rows = [];
        foreach (User::where('is_active', true)->orderBy('name')->get() as $u) {
            $open = Task::where('assigned_to', $u->id)->whereNotIn('status', ['done'])->count();
            $dueWeek = Task::where('assigned_to', $u->id)->whereNotIn('status', ['done'])
                ->whereDate('due_date', '>=', $weekStart)->whereDate('due_date', '<=', $weekEnd)->count();
            $overdue = Task::where('assigned_to', $u->id)->whereNotIn('status', ['done'])
                ->whereDate('due_date', '<', now()->toDateString())->count();
            $mins = (int) TaskTime::where('user_id', $u->id)->whereDate('worked_on', '>=', $weekStart)->whereDate('worked_on', '<=', $weekEnd)->sum('minutes');
            $cap = (int) ($u->capacity_hours ?: $defaultCap);
            $rows[] = [
                'user' => $u, 'open' => $open, 'dueWeek' => $dueWeek, 'overdue' => $overdue,
                'mins' => $mins, 'cap' => $cap,
                'pct' => $cap > 0 ? min(999, round($mins / 60 / $cap * 100)) : 0,
                'tasks' => Task::where('assigned_to', $u->id)->whereNotIn('status', ['done'])->orderBy('due_date')->limit(50)->get(['id', 'title', 'due_date']),
            ];
        }
        usort($rows, fn ($a, $b) => $b['pct'] <=> $a['pct']);

        return view('team.load', compact('rows', 'defaultCap', 'weekStart', 'weekEnd'));
    }

    /** Move open tasks from one staffer to another (rebalance). */
    public function rebalance(Request $r)
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers rebalance work.');
        }
        $data = $r->validate([
            'from_id' => 'required|exists:users,id', 'to_id' => 'required|exists:users,id|different:from_id',
            'task_ids' => 'required|array|min:1', 'task_ids.*' => 'integer',
        ]);
        $n = Task::whereIn('id', $data['task_ids'])->where('assigned_to', $data['from_id'])
            ->whereNotIn('status', ['done'])->update(['assigned_to' => $data['to_id']]);
        if ($n) {
            activity('tasks-rebalanced', null, auth()->user()->name.' moved '.$n.' task(s) to '.User::find($data['to_id'])->name);
        }

        return back()->with('ok', $n ? 'Moved '.$n.' open task(s).' : 'Nothing moved — tasks were already done or reassigned.');
    }
}
