<?php

namespace Modules\Projects\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Clients\Client;
use Modules\Core\Numbering\NextNumber;
use Modules\Expenses\Expense;
use Modules\Invoices\Invoice;
use Modules\Projects\Project;
use Modules\Settings\SettingStore;
use Modules\Tasks\TaskTime;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }
    public function index(Request $r)
    {
        $q = Project::with('client')->withCount('tasks')->withCount(['tasks as done_tasks_count' => fn ($c) => $c->where('status', 'done')])->withSum('expenses as costs_sum', 'amount')->withSum(['invoices as billed_sum' => fn ($c) => $c->whereIn('status', ['sent', 'partial', 'paid', 'overdue'])], 'total')->orderByDesc('id');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('name', 'like', $s)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $s)));
        }
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }
        $funnel = Project::select('status', DB::raw('COUNT(*) c'))->groupBy('status')->pluck('c', 'status');
        $stats = [
            'total' => Project::count(),
            'active' => Project::whereIn('status', ['new', 'in_progress'])->count(),
            'completed' => Project::where('status', 'completed')->count(),
            'budget' => (float) Project::sum('budget'),
        ];

        return view('projects.index', ['projects' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'status' => $r->status, 'funnel' => $funnel, 'stats' => $stats]);
    }

    public function show(Project $project)
    {
        $project->load(['client', 'tasks.times', 'tasks.checklist', 'expenses', 'documents']);
        $labor = $project->laborTotal();
        $estMin = $project->estimateMinutes();
        $workedMin = $project->workedMinutes();
        $unbilled = $project->unbilledTime();

        return view('projects.show', compact('project', 'labor', 'estMin', 'workedMin', 'unbilled'));
    }

    /**
     * Bill unbilled time: one draft invoice, one line per task
     * (hours × that task's rate), entries stamped so never double-billed.
     */
    public function billTime(Request $r, Project $project)
    {
        $project->load('client');
        $entries = $project->unbilledTime();
        if ($entries->isEmpty()) {
            return back()->withErrors(['billing' => 'No unbilled time on this project.']);
        }
        $data = $r->validate(['gst_rate' => 'nullable|numeric|min:0|max:28']);
        $s = app(SettingStore::class);
        $inv = Invoice::create([
            'number' => NextNumber::take($s->get('invoice.prefix', 'INV-'), 'invoices'),
            'client_id' => $project->client_id, 'project_id' => $project->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays((int) $s->get('invoice.due_days', 7))->toDateString(),
            'status' => 'draft', 'gst_rate' => $data['gst_rate'] ?? (float) $s->get('tax.default_gst_rate', 18),
            'place_of_supply' => $project->client->state ?? $s->get('company.state', ''),
            'notes' => 'Billed time on '.$project->name.'.',
            'currency' => $project->client->currency ?? 'INR',
            'exchange_rate' => 1,
            'public_token' => Str::random(32), 'created_by' => auth()->id(),
        ]);
        foreach ($entries->groupBy('task_id') as $taskId => $rows) {
            $task = $rows->first()->task;
            $mins = (int) $rows->sum('minutes');
            if ($mins < 1 || ! $task) {
                continue;
            }
            $hours = round($mins / 60, 2);
            $line = $inv->items()->create([
                'description' => $task->title.' — '.\Modules\Tasks\TaskTime::fmt($mins).' ('.$rows->count().' entries)',
                'qty' => $hours, 'unit' => 'HRS', 'rate' => $task->rate(),
                'tax_rate' => null, 'amount' => round($hours * $task->rate(), 2),
            ]);
            TaskTime::whereIn('id', $rows->pluck('id'))->update(['invoice_id' => $inv->id]);
        }
        if ($inv->items()->count() === 0) {
            $inv->delete();

            return back()->withErrors(['billing' => 'Nothing billable — entries below a minute are skipped.']);
        }
        $inv->recalc();

        return redirect()->route('invoices.show', $inv)->with('ok', 'Draft invoice from '.$entries->count().' time entries. Review and send.');
    }

    public function create()
    {
        return view('projects.form', ['project' => new Project, 'clients' => Client::orderBy('name')->get()]);
    }

    public function store(Request $r)
    {
        Project::create($r->validate(['client_id' => 'required|exists:clients,id', 'name' => 'required|string', 'description' => 'nullable|string', 'status' => 'nullable|string', 'start_date' => 'nullable|date', 'due_date' => 'nullable|date', 'budget' => 'nullable|numeric']));

        return redirect()->route('projects.index')->with('ok', 'Project saved.');
    }

    public function edit(Project $project)
    {
        return view('projects.form', ['project' => $project, 'clients' => Client::orderBy('name')->get()]);
    }

    public function update(Request $r, Project $project)
    {
        $project->update($r->validate(['client_id' => 'required|exists:clients,id', 'name' => 'required|string', 'description' => 'nullable|string', 'status' => 'nullable|string', 'start_date' => 'nullable|date', 'due_date' => 'nullable|date', 'budget' => 'nullable|numeric']));

        return redirect()->route('projects.index')->with('ok', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        // Keep expenses for the books, just unlink them from the deleted project.
        Expense::where('project_id', $project->id)->update(['project_id' => null]);
        $project->delete();

        return back()->with('ok', 'Project moved to Trash.');
    }

    /** Bulk delete / status-change from the list checkboxes. */
    public function bulk(Request $r)
    {
        $data = $r->validate([
            'action' => 'required|in:delete,status', 'ids' => 'required|array|max:200', 'ids.*' => 'integer',
            'status' => 'required_if:action,status|in:new,in_progress,on_hold,completed,cancelled',
        ]);
        $done = 0;
        $errors = [];
        foreach (Project::whereIn('id', $data['ids'])->with('client')->get() as $project) {
            try {
                if ($data['action'] === 'delete') {
                    Expense::where('project_id', $project->id)->update(['project_id' => null]);
                    $project->delete();
                } else {
                    $project->update(['status' => $data['status']]);
                }
                $done++;
            } catch (\Throwable $e) {
                $errors[] = $project->name.': failed';
            }
        }
        return response()->json(['ok' => true, 'done' => $done, 'errors' => array_slice($errors, 0, 5)]);
    }
}
