<?php

namespace Modules\Work\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tasks\Task;
use Modules\Tasks\TaskTime;
use Modules\Work\FieldVisit;

class FieldController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    public function index(Request $r)
    {
        $me = auth()->user();
        $canAll = $me->isAdmin() || $me->isManager();
        $q = FieldVisit::with(['user', 'task'])->orderByDesc('check_in_at');
        if (! $canAll) {
            $q->where('user_id', $me->id);
        } elseif ($r->filled('user_id')) {
            $q->where('user_id', $r->user_id);
        }
        if ($r->filled('day')) {
            $q->whereDate('check_in_at', $r->day);
        }
        $open = FieldVisit::where('user_id', $me->id)->whereNull('check_out_at')->orderByDesc('check_in_at')->first();

        return view('field.index', [
            'rows' => $q->paginate(perPage())->withQueryString(),
            'users' => $canAll ? \App\Models\User::where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'canAll' => $canAll, 'user_id' => $r->user_id, 'day' => $r->day,
            'open' => $open,
            'tasks' => Task::whereNotIn('status', ['done'])->orderByDesc('id')->limit(200)->get(['id', 'title']),
        ]);
    }

    /** Check in at site (browser GPS attached, spoof-proof enough for records). */
    public function checkin(Request $r)
    {
        $data = $r->validate([
            'task_id' => 'nullable|exists:tasks,id',
            'lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180',
            'note' => 'nullable|string|max:255',
        ]);
        $me = auth()->user();
        if (FieldVisit::where('user_id', $me->id)->whereNull('check_out_at')->exists()) {
            return back()->withErrors(['visit' => 'You already have an open visit — check out first.'])->withInput();
        }
        FieldVisit::create([
            'user_id' => $me->id, 'task_id' => $data['task_id'] ?? null,
            'check_in_at' => now(), 'in_lat' => $data['lat'] ?? null, 'in_lng' => $data['lng'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('ok', 'Checked in — have a productive visit.');
    }

    /** Check out: closes the visit and logs the time to the linked task. */
    public function checkout(Request $r, FieldVisit $visit)
    {
        $me = auth()->user();
        if ($visit->user_id !== $me->id && ! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Not your visit.');
        }
        if ($visit->check_out_at) {
            return back()->withErrors(['visit' => 'Already checked out.']);
        }
        $data = $r->validate([
            'lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180',
        ]);
        $visit->update(['check_out_at' => now(), 'out_lat' => $data['lat'] ?? null, 'out_lng' => $data['lng'] ?? null]);
        $mins = $visit->minutes();
        if ($mins && $visit->task_id) {
            TaskTime::create(['task_id' => $visit->task_id, 'user_id' => $visit->user_id, 'minutes' => $mins, 'worked_on' => now()->toDateString(), 'note' => 'Field visit '.d($visit->check_in_at, 'h:i A').'–'.d($visit->check_out_at, 'h:i A')]);
        }

        return back()->with('ok', 'Checked out after '.\Modules\Tasks\TaskTime::fmt($mins ?? 0).($visit->task_id ? ' — logged to the task.' : '.'));
    }
}
