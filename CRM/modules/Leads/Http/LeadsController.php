<?php

namespace Modules\Leads\Http;

use App\Models\User;
use App\Notifications\LeadWon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Clients\Client;
use Modules\Leads\Lead;

class LeadsController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:sales');
    }
    public function index(Request $r)
    {
        $view = $r->get('view', setting('leads.default_view', 'list')) === 'board' ? 'board' : 'list';
        $q = Lead::with('assignee')->orderByDesc('id');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('name', 'like', $s)->orWhere('company', 'like', $s)->orWhere('phone', 'like', $s)->orWhere('email', 'like', $s));
        }
        if ($r->filled('stage')) {
            $q->where('stage', $r->stage);
        }
        if ($r->filled('source')) {
            $q->where('source', $r->source);
        }
        $staleOnly = $r->boolean('stale');
        $stats = [
            'open' => Lead::whereNotIn('stage', ['won', 'lost'])->count(),
            'won_month' => Lead::where('stage', 'won')->whereYear('updated_at', now()->year)->whereMonth('updated_at', now()->month)->count(),
            'pipeline' => (float) Lead::whereNotIn('stage', ['won', 'lost'])->sum('value'),
            'followups' => Lead::whereNotIn('stage', ['won', 'lost'])->whereNotNull('follow_up_on')->whereDate('follow_up_on', '<=', now()->addDays(7)->toDateString())->count(),
            'stale' => Lead::whereNotIn('stage', ['won', 'lost'])->get()->filter->isStale()->count(),
        ];
        if ($staleOnly) {
            $ids = Lead::whereNotIn('stage', ['won', 'lost'])->get()->filter->isStale()->pluck('id')->all();
            $q->whereIn('id', $ids ?: [0]);
        }
        $funnel = Lead::select('stage', DB::raw('COUNT(*) c'))->groupBy('stage')->pluck('c', 'stage');
        if ($view === 'board') {
            $board = $q->limit(500)->get()->groupBy('stage');

            return view('leads.index', [
                'leads' => $q->paginate(perPage())->withQueryString(),
                'board' => $board, 'view' => $view,
                'q' => $r->q, 'stage' => $r->stage, 'source' => $r->source, 'stale' => $staleOnly,
                'funnel' => $funnel, 'stats' => $stats,
                'sources' => setting_lines('leads.sources', Lead::defaultSources()),
            ]);
        }

        return view('leads.index', [
            'leads' => $q->paginate(perPage())->withQueryString(),
            'view' => $view,
            'q' => $r->q, 'stage' => $r->stage, 'source' => $r->source, 'stale' => $staleOnly,
            'funnel' => $funnel, 'stats' => $stats,
            'sources' => setting_lines('leads.sources', Lead::defaultSources()),
        ]);
    }

    public function create(Request $r)
    {
        return view('leads.form', [
            'lead' => new Lead(['stage' => 'new', 'source' => 'website', 'assigned_to' => auth()->id()]),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
            'sources' => setting_lines('leads.sources', Lead::defaultSources()),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:255', 'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20', 'email' => 'nullable|email|max:255',
            'city' => 'nullable|string|max:100', 'state' => 'nullable|string|max:100',
            'source' => 'nullable|string|max:50', 'stage' => 'nullable|string|max:20',
            'value' => 'nullable|numeric|min:0', 'follow_up_on' => 'nullable|date',
            'notes' => 'nullable|string', 'assigned_to' => 'nullable|exists:users,id',
        ]);
        $data['stage'] ??= 'new';
        $data['source'] ??= 'website';
        Lead::create($data);

        return redirect()->route('leads.index')->with('ok', 'Lead saved.');
    }

    public function edit(Lead $lead)
    {
        return view('leads.form', [
            'lead' => $lead,
            'users' => User::where('is_active', true)->orderBy('name')->get(),
            'sources' => setting_lines('leads.sources', Lead::defaultSources()),
        ]);
    }

    public function update(Request $r, Lead $lead)
    {
        $lead->update($r->validate([
            'name' => 'required|string|max:255', 'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20', 'email' => 'nullable|email|max:255',
            'city' => 'nullable|string|max:100', 'state' => 'nullable|string|max:100',
            'source' => 'nullable|string|max:50', 'stage' => 'required|in:new,contacted,qualified,proposal,won,lost',
            'value' => 'nullable|numeric|min:0', 'follow_up_on' => 'nullable|date',
            'notes' => 'nullable|string', 'assigned_to' => 'nullable|exists:users,id',
        ]));

        return redirect()->route('leads.index')->with('ok', 'Lead updated.');
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return back()->with('ok', 'Lead moved to Trash.');
    }

    /** Bulk delete / stage-change from the list checkboxes. */
    public function bulk(Request $r)
    {
        $data = $r->validate([
            'action' => 'required|in:delete,stage', 'ids' => 'required|array|max:200', 'ids.*' => 'integer',
            'stage' => 'required_if:action,stage|in:new,contacted,qualified,proposal,won,lost',
        ]);
        $done = 0;
        $errors = [];
        foreach (Lead::whereIn('id', $data['ids'])->get() as $lead) {
            try {
                if ($data['action'] === 'delete') $lead->delete();
                else $lead->update(['stage' => $data['stage']]);
                $done++;
            } catch (\Throwable $e) {
                $errors[] = ($lead->company ?: $lead->name).': failed';
            }
        }
        return response()->json(['ok' => true, 'done' => $done, 'errors' => array_slice($errors, 0, 5)]);
    }

    /** Move a lead to another stage (kanban drag-drop or dropdown). */
    public function move(Request $r, Lead $lead)
    {
        $data = $r->validate(['stage' => 'required|in:new,contacted,qualified,proposal,won,lost']);
        $lead->update(['stage' => $data['stage']]);
        if ($lead->stage === 'won') {
            notify_roles(['admin', 'manager'], new LeadWon($lead));
        }
        if ($r->wantsJson() || $r->has('ajax')) {
            return response()->json(['ok' => true, 'stage' => $lead->stage]);
        }

        return back()->with('ok', 'Lead moved to '.Lead::stages()[$lead->stage].'.');
    }

    /** Push the follow-up out a week — one click from the list. */
    public function bump(Lead $lead)
    {
        if (in_array($lead->stage, ['won', 'lost'], true)) {
            return back()->withErrors(['lead' => 'Closed leads need no follow-up.']);
        }
        $from = ($lead->follow_up_on && $lead->follow_up_on->isFuture()) ? $lead->follow_up_on : now();
        $lead->update(['follow_up_on' => $from->copy()->addDays(7)->toDateString()]);

        return back()->with('ok', 'Follow-up pushed to '.d($lead->follow_up_on).'.');
    }

    /** Convert a qualified lead into a client (idempotent — reuses the link). */
    public function convert(Lead $lead)
    {
        if ($lead->converted_client_id && $c = Client::find($lead->converted_client_id)) {
            return redirect()->route('clients.show', $c)->with('ok', 'Already converted — opened the client.');
        }
        $client = Client::create([
            'name' => $lead->name, 'company' => $lead->company, 'email' => $lead->email,
            'phone' => $lead->phone, 'state' => $lead->state, 'status' => 'active',
            'notes' => 'Converted from lead ('.$lead->source.', '.now()->toDateString().'). '.($lead->notes ?? ''),
        ]);
        $lead->update(['converted_client_id' => $client->id, 'stage' => 'won']);
        notify_roles(['admin', 'manager'], new LeadWon($lead));

        return redirect()->route('clients.show', $client)->with('ok', 'Lead converted to client.');
    }
}
