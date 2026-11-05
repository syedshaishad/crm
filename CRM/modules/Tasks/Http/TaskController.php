<?php

namespace Modules\Tasks\Http;

use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Clients\Client;
use Modules\Projects\Project;
use Modules\Settings\SettingStore;
use Modules\Tasks\ChecklistTemplate;
use Modules\Tasks\Task;
use Modules\Tasks\TaskChecklist;
use Modules\Tasks\TaskComment;
use Modules\Tasks\TaskDependency;
use Modules\Tasks\TaskTemplate;
use Modules\Tasks\TaskTime;

class TaskController extends Controller
{
    public function __construct()
    {
        // Public client sign-off stays open — the rest needs work access.
        $this->middleware('perm:work')->except(['publicView', 'publicAccept']);
    }
    public function index(Request $r)
    {
        $view = $r->input('view', setting('tasks.default_view', 'list')) === 'calendar' ? 'calendar' : 'list';
        $q = Task::with(['project.client', 'assignee'])->orderByDesc('id');
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }
        if ($r->filled('priority')) {
            $q->where('priority', $r->priority);
        }
        if ($r->filled('client_id')) {
            $q->where(fn ($w) => $w->where('client_id', $r->client_id)->orWhereHas('project', fn ($p) => $p->where('client_id', $r->client_id)));
        }
        if ($r->filled('q')) {
            $q->where('title', 'like', '%'.$r->q.'%');
        }
        if ($r->filled('day')) {
            $q->whereDate('due_date', $r->day);
        }
        $funnel = Task::select('status', DB::raw('COUNT(*) c'))->groupBy('status')->pluck('c', 'status');

        // Calendar month map (applies status filter, ignores search).
        $calMonth = $r->input('month', now()->format('Y-m'));
        [$calY, $calM] = array_map('intval', explode('-', $calMonth) + [0, 0]);
        if (! $calM) {
            $calY = (int) now()->format('Y');
            $calM = (int) now()->format('n');
        }
        $calStart = (int) date('N', mktime(0, 0, 0, $calM, 1, $calY));
        $calDays = (int) date('t', mktime(0, 0, 0, $calM, 1, $calY));
        $cq = Task::whereYear('due_date', $calY)->whereMonth('due_date', $calM);
        if ($r->filled('status')) {
            $cq->where('status', $r->status);
        }
        $calMap = [];
        foreach ($cq->orderBy('due_date')->get(['id', 'title', 'status', 'due_date']) as $t) {
            $calMap[substr($t->due_date, 0, 10)][] = $t;
        }
        $prevMonth = date('Y-m', mktime(0, 0, 0, $calM - 1, 1, $calY));
        $nextMonth = date('Y-m', mktime(0, 0, 0, $calM + 1, 1, $calY));

        return view('tasks.index', ['tasks' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'status' => $r->status, 'priority' => $r->priority, 'client_id' => $r->client_id, 'clients' => Client::orderBy('name')->get(['id', 'name', 'company']), 'funnel' => $funnel, 'view' => $view, 'projects' => Project::orderBy('name')->get(['id', 'name']), 'calY' => $calY, 'calM' => $calM, 'calStart' => $calStart, 'calDays' => $calDays, 'calMap' => $calMap, 'calMonth' => sprintf('%04d-%02d', $calY, $calM), 'prevMonth' => $prevMonth, 'nextMonth' => $nextMonth, 'day' => $r->day]);
    }

    public function create(Request $r)
    {
        return view('tasks.form', ['task' => new Task(['project_id' => $r->project_id, 'status' => 'todo', 'priority' => 'medium']), 'projects' => Project::orderBy('name')->get(), 'users' => User::orderBy('name')->get()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate(['project_id' => 'nullable|exists:projects,id', 'client_id' => 'nullable|exists:clients,id', 'assigned_to' => 'nullable|exists:users,id', 'title' => 'required|string', 'description' => 'nullable|string', 'status' => 'nullable|string', 'priority' => 'nullable|string', 'due_date' => 'nullable|date', 'start_date' => 'nullable|date', 'estimate_hours' => 'nullable|numeric|min:0|max:5000', 'estimate_minutes' => 'nullable|numeric|min:0|max:5000', 'hourly_rate' => 'nullable|numeric|min:0', 'recurs' => 'nullable|in:none,weekly,monthly']);
        // Quick-add posts title/project/due-date only — fall back to the create-form defaults.
        $data['status'] ??= 'todo';
        $data['priority'] ??= 'medium';
        $data['estimate_minutes'] = (int) round(((float) ($data['estimate_hours'] ?? 0)) * 60 + (float) ($data['estimate_minutes'] ?? 0));
        unset($data['estimate_hours']);
        $data['recurs'] ??= 'none';
        $data['baseline_due'] = $data['due_date'] ?? null;
        $task = Task::create($data);
        $this->notifyAssignee($task, $data['assigned_to'] ?? null);

        // Quick-add posts from the list, so "back" lands the user on the list with the
        // new row in place. From the standalone create form, go there explicitly instead
        // of re-rendering an empty form.
        if (str_contains(url()->previous(), '/tasks/create')) {
            return redirect()->route('tasks.index')->with('ok', 'Task saved.');
        }

        return back()->with('ok', 'Task saved.');
    }

    public function edit(Task $task)
    {
        $task->load(['times.user', 'checklist', 'comments.user', 'blockers.blocker']);

        return view('tasks.form', [
            'task' => $task, 'projects' => Project::orderBy('name')->get(), 'users' => User::orderBy('name')->get(),
            'timeTotal' => $task->times->sum('minutes'),
            'siblings' => Task::where('id', '!=', $task->id)->orderByDesc('id')->limit(200)->get(['id', 'title', 'status']),
            'templates' => TaskTemplate::orderBy('name')->get(),
            'checklistTemplates' => ChecklistTemplate::orderBy('name')->get(),
        ]);
    }

    /** Fill a new task from a saved template (lines become checklist steps). */
    public function fromTemplate(Request $r)
    {
        $data = $r->validate(['template_id' => 'required|exists:task_templates,id', 'project_id' => 'nullable|exists:projects,id', 'assigned_to' => 'nullable|exists:users,id', 'due_date' => 'nullable|date']);
        $t = TaskTemplate::find($data['template_id']);
        $task = Task::create([
            'project_id' => $data['project_id'] ?? null, 'assigned_to' => $data['assigned_to'] ?? null,
            'title' => $t->title, 'description' => $t->description, 'status' => 'todo',
            'priority' => $t->priority, 'estimate_minutes' => $t->estimate_minutes,
            'due_date' => $data['due_date'] ?? null, 'baseline_due' => $data['due_date'] ?? null,
        ]);
        foreach ($t->lines() as $i => $label) {
            $task->checklist()->create(['label' => $label, 'position' => $i]);
        }

        return redirect()->route('tasks.edit', $task)->with('ok', 'Task created from template.');
    }

    /** Stamp a checklist template's lines onto this task. */
    public function applyChecklist(Request $r, Task $task)
    {
        $data = $r->validate(['template_id' => 'required|exists:checklist_templates,id']);
        $t = ChecklistTemplate::find($data['template_id']);
        $pos = (int) $task->checklist()->max('position');
        foreach ($t->lines() as $label) {
            $task->checklist()->create(['label' => $label, 'position' => ++$pos]);
        }

        return back()->with('ok', 'Checklist applied.');
    }

    /** Template library: reusable task + checklist starters. */
    public function templates(Request $r)
    {
        $q = trim((string) $r->query('q', ''));
        $allTask = TaskTemplate::orderBy('name')->get();
        $allChecklist = ChecklistTemplate::orderBy('name')->get();

        $taskTemplates = $allTask;
        $checklistTemplates = $allChecklist;

        if ($q !== '') {
            $taskTemplates = $taskTemplates->filter(fn ($t) => mb_stripos($t->name, $q) !== false || mb_stripos((string) $t->title, $q) !== false)->values();
            $checklistTemplates = $checklistTemplates->filter(fn ($t) => mb_stripos($t->name, $q) !== false)->values();
        }

        return view('tasks.templates', [
            'taskTemplates' => $taskTemplates,
            'checklistTemplates' => $checklistTemplates,
            'stats' => [
                'starters' => $allTask->count(),
                'starter_steps' => $allTask->sum(fn ($t) => count($t->lines())),
                'packs' => $allChecklist->count(),
                'pack_steps' => $allChecklist->sum(fn ($t) => count($t->lines())),
            ],
            'q' => $q,
        ]);
    }

    public function templateStore(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:150|unique:task_templates,name',
            'title' => 'required|string|max:255', 'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'estimate_minutes' => 'nullable|integer|min:0', 'checklist' => 'nullable|string',
        ]);
        TaskTemplate::create([...$data, 'priority' => $data['priority'] ?? 'medium', 'estimate_minutes' => $data['estimate_minutes'] ?? 0]);

        return back()->with('ok', 'Task template saved.');
    }

    public function templateDestroy(TaskTemplate $template)
    {
        $template->delete();

        return back()->with('ok', 'Task template removed.');
    }

    public function checklistTemplateStore(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:150|unique:checklist_templates,name', 'lines' => 'required|string']);
        ChecklistTemplate::create($data);

        return back()->with('ok', 'Checklist template saved.');
    }

    public function checklistTemplateDestroy(ChecklistTemplate $template)
    {
        $template->delete();

        return back()->with('ok', 'Checklist template removed.');
    }

    public function update(Request $r, Task $task)
    {
        $data = $r->validate(['project_id' => 'nullable|exists:projects,id', 'assigned_to' => 'nullable|exists:users,id', 'title' => 'required|string', 'description' => 'nullable|string', 'status' => 'nullable|string', 'priority' => 'nullable|string', 'due_date' => 'nullable|date', 'start_date' => 'nullable|date', 'estimate_hours' => 'nullable|numeric|min:0|max:5000', 'estimate_minutes' => 'nullable|numeric|min:0|max:5000', 'hourly_rate' => 'nullable|numeric|min:0', 'recurs' => 'nullable|in:none,weekly,monthly']);
        $data['estimate_minutes'] = (int) round(((float) ($data['estimate_hours'] ?? 0)) * 60 + (float) ($data['estimate_minutes'] ?? 0));
        unset($data['estimate_hours']);
        $data['recurs'] ??= $task->recurs ?? 'none';
        // First dated save stamps the baseline — later slips show against it.
        if (empty($task->baseline_due) && ! empty($data['due_date'])) {
            $data['baseline_due'] = $data['due_date'];
        }
        $wasDone = $task->status === 'done';
        $task->update($data);
        $this->maybeRecur($task, $wasDone);
        if ($task->wasChanged('assigned_to')) {
            $this->notifyAssignee($task, $task->assigned_to);
        }

        return redirect()->route('tasks.index')->with('ok', 'Task updated.');
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return back()->with('ok', 'Task moved to Trash.');
    }

    /** Bulk delete / status-change from the list checkboxes. */
    public function bulk(Request $r)
    {
        $data = $r->validate([
            'action' => 'required|in:delete,status', 'ids' => 'required|array|max:200', 'ids.*' => 'integer',
            'status' => 'required_if:action,status|in:todo,in_progress,review,done',
        ]);
        $done = 0;
        $errors = [];
        foreach (Task::whereIn('id', $data['ids'])->get() as $task) {
            try {
                if ($data['action'] === 'delete') $task->delete();
                else $task->update(['status' => $data['status']]);
                $done++;
            } catch (\Throwable $e) {
                $errors[] = $task->title.': failed';
            }
        }
        return response()->json(['ok' => true, 'done' => $done, 'errors' => array_slice($errors, 0, 5)]);
    }

    /** Log time on a task (hours + minutes welcome). */
    public function timeStore(Request $r, Task $task)
    {
        $data = $r->validate([
            'hours' => 'nullable|numeric|min:0|max:500', 'minutes' => 'nullable|numeric|min:0|max:500',
            'worked_on' => 'required|date|before_or_equal:today', 'note' => 'nullable|string|max:255',
        ]);
        $mins = (int) round(((float) ($data['hours'] ?? 0)) * 60 + (float) ($data['minutes'] ?? 0));
        if ($mins < 1) {
            return back()->withErrors(['minutes' => 'Log at least a minute.'])->withInput();
        }
        $task->times()->create(['user_id' => auth()->id(), 'minutes' => $mins, 'worked_on' => $data['worked_on'], 'note' => $data['note'] ?? null]);

        return back()->with('ok', 'Logged '.TaskTime::fmt($mins).'.');
    }

    public function timeDestroy(TaskTime $time)
    {
        if ($time->user_id !== auth()->id() && ! auth()->user()->canManageInvoices()) {
            abort(403, 'Only the logger or a manager can remove time.');
        }
        $time->delete();

        return back()->with('ok', 'Time entry removed.');
    }

    /** Discuss + attach files on a task. */
    public function commentStore(Request $r, Task $task)
    {
        $maxKb = (int) (app(SettingStore::class)->get('uploads.document_max_mb', 20) * 1024);
        $data = $r->validate(['body' => 'required|string|max:5000', 'file' => 'nullable|file|max:'.$maxKb]);
        $file = $data['file'] ?? $r->file('file');
        $c = new TaskComment(['task_id' => $task->id, 'user_id' => auth()->id(), 'body' => $data['body']]);
        if ($r->hasFile('file')) {
            $f = $r->file('file');
            if ($reason = upload_reject_reason($f)) {
                return back()->withErrors(['file' => $reason])->withInput();
            }
            $c->file_path = $f->store('task_attachments', 'public');
            $c->file_name = $f->getClientOriginalName();
            $c->file_size = $f->getSize();
        }
        $c->save();

        return back()->with('ok', 'Comment added.');
    }

    public function commentDestroy(TaskComment $comment)
    {
        $u = auth()->user();
        if ($comment->user_id !== $u->id && ! $u->canManageInvoices()) {
            abort(403, 'Only the author or a manager can delete this.');
        }
        if ($comment->file_path) {
            Storage::disk('public')->delete($comment->file_path);
        }
        $comment->delete();

        return back()->with('ok', 'Comment deleted.');
    }

    /** One-tap done/undo from the dashboard focus list. */
    public function toggle(Task $task)
    {
        $wasDone = $task->status === 'done';
        $task->update(['status' => $task->status === 'done' ? 'todo' : 'done']);
        $this->maybeRecur($task->fresh(), $wasDone);

        return back()->with('ok', $task->status === 'done' ? 'Task done.' : 'Task reopened.');
    }

    /** Recurring tasks respawn on completion (weekly/monthly shift). */
    protected function maybeRecur(Task $task, bool $wasDone): void
    {
        if ($wasDone || $task->status !== 'done' || ($task->recurs ?? 'none') === 'none') {
            return;
        }
        $shift = $task->recurs === 'weekly' ? 7 : 30;
        $next = $task->replicate(['handed_over_to', 'handover_note', 'handed_over_at', 'handed_over_by', 'public_token']);
        $next->status = 'todo';
        $next->due_date = $task->due_date ? $task->due_date->copy()->addDays($shift)->toDateString() : now()->addDays($shift)->toDateString();
        $next->start_date = $task->start_date ? $task->start_date->copy()->addDays($shift)->toDateString() : null;
        $next->save();
        foreach ($task->checklist as $i => $c) {
            $next->checklist()->create(['label' => $c->label, 'is_done' => false, 'position' => $c->position ?? $i]);
        }
    }

    /** This task waits on another (same project ideally, any task allowed). */
    public function depStore(Request $r, Task $task)
    {
        $data = $r->validate(['depends_on' => 'required|exists:tasks,id']);
        if ((int) $data['depends_on'] === (int) $task->id) {
            return back()->withErrors(['dep' => 'A task cannot wait on itself.']);
        }
        // Cycle guard: the blocker must not (transitively) wait on this task.
        $seen = [$task->id];
        $frontier = [(int) $data['depends_on']];
        while ($frontier) {
            $id = array_pop($frontier);
            if (in_array($id, $seen, true)) {
                return back()->withErrors(['dep' => 'That would loop back — pick another task.']);
            }
            $seen[] = $id;
            $frontier = array_merge($frontier, TaskDependency::where('task_id', $id)->pluck('depends_on_task_id')->map(fn ($v) => (int) $v)->all());
        }
        $task->blockers()->firstOrCreate(['depends_on_task_id' => $data['depends_on']]);

        return back()->with('ok', 'Dependency added.');
    }

    public function depDestroy(TaskDependency $dep)
    {
        $dep->delete();

        return back()->with('ok', 'Dependency removed.');
    }

    /** Shareable client link for sign-off (token-guarded, no login). */
    public function shareLink(Task $task)
    {
        if (! $task->public_token) {
            $task->update(['public_token' => Str::random(32)]);
        }

        return back()->with('ok', 'Client link ready — share it for sign-off.');
    }

    /** Public task card: what was done + accept button for the client. */
    public function publicView(string $token)
    {
        $task = Task::where('public_token', $token)->with(['project.client', 'checklist'])->firstOrFail();

        return view('tasks.public', compact('task'));
    }

    /** Client accepts the work from the public link. */
    public function publicAccept(Request $r, string $token)
    {
        $task = Task::where('public_token', $token)->firstOrFail();
        if ($task->handed_over_at) {
            return back()->withErrors(['task' => 'Already signed off. Thank you!']);
        }
        $data = $r->validate(['handed_over_to' => 'required|string|max:255', 'handover_note' => 'nullable|string|max:255']);
        $task->update([
            'handed_over_to' => $data['handed_over_to'], 'handover_note' => $data['handover_note'] ?? null,
            'handed_over_at' => now(), 'status' => 'done',
        ]);
        activity('task-handover', $task, 'Task signed off by client: '.$data['handed_over_to']);

        return back()->with('ok', 'Signed off — thank you!');
    }

    /** Add a step to the field checklist. */
    public function checklistStore(Request $r, Task $task)
    {
        $data = $r->validate(['label' => 'required|string|max:255']);
        $task->checklist()->create([
            'label' => $data['label'],
            'position' => (int) $task->checklist()->max('position') + 1,
        ]);

        return back()->with('ok', 'Checklist step added.');
    }

    /** Tick/untick a checklist step. */
    public function checklistToggle(TaskChecklist $item)
    {
        $item->update(['is_done' => ! $item->is_done]);

        return back()->with('ok', $item->is_done ? 'Step done.' : 'Step reopened.');
    }

    public function checklistDestroy(TaskChecklist $item)
    {
        $item->delete();

        return back()->with('ok', 'Checklist step removed.');
    }

    /**
     * Client handover: only when every checklist step is done and at least
     * one site photo is attached — proof before signature.
     */
    public function handover(Request $r, Task $task)
    {
        $data = $r->validate([
            'handed_over_to' => 'required|string|max:255',
            'handover_note' => 'nullable|string|max:255',
        ]);
        $task->loadMissing('checklist', 'comments');
        if ($task->checklist->isNotEmpty() && $task->checklist->where('is_done', false)->isNotEmpty()) {
            return back()->withErrors(['handover' => 'Finish all checklist steps first.'])->withInput();
        }
        if (! $task->hasPhoto()) {
            return back()->withErrors(['handover' => 'Attach at least one site photo before handover.'])->withInput();
        }
        $task->update([
            'handed_over_to' => $data['handed_over_to'], 'handover_note' => $data['handover_note'] ?? null,
            'handed_over_at' => now(), 'handed_over_by' => auth()->id(), 'status' => 'done',
        ]);
        activity('task-handover', $task, 'Task handed over to '.$data['handed_over_to']);

        return back()->with('ok', 'Handed over to '.$data['handed_over_to'].' — signed and closed.');
    }

    /** Calendar drag-drop reschedule (drops a task onto another day). */
    public function move(Request $r, Task $task)
    {
        $data = $r->validate(['due_date' => 'required|date']);
        $from = $task->due_date ? substr((string) $task->due_date, 0, 10) : null;
        $task->update(['due_date' => $data['due_date']]);
        if ($r->wantsJson() || $r->has('ajax')) {
            // Re-render the pills of the two affected days so the client can settle
            // the move in place (counts and "+N more" included) — respecting the
            // status filter the calendar is currently showing.
            $status = $r->filled('status') ? $r->status : null;
            $pills = function ($date) use ($status) {
                if (! $date) {
                    return ['date' => null, 'html' => ''];
                }
                $q = Task::whereDate('due_date', $date);
                if ($status) {
                    $q->where('status', $status);
                }
                $items = $q->orderBy('due_date')->orderBy('id')->get(['id', 'title', 'status', 'due_date'])->all();

                return ['date' => $date, 'html' => view('tasks._pills', ['items' => $items, 'date' => $date])->render()];
            };

            return response()->json([
                'ok' => true,
                'due_date' => $task->due_date,
                'from' => $pills($from),
                'to' => $pills(substr((string) $task->due_date, 0, 10)),
            ]);
        }

        return back()->with('ok', 'Task moved to '.d($task->due_date).'.');
    }

    /** Ping the assignee when a task lands on them (never notifies self). */
    private function notifyAssignee(Task $task, $assignedTo): void
    {
        if (! $assignedTo || (int) $assignedTo === (int) auth()->id()) {
            return;
        }
        $u = User::find($assignedTo);
        if ($u && $u->is_active) {
            $u->notify(new TaskAssigned($task->title, auth()->user()->name, $task->id));
        }
    }
}
