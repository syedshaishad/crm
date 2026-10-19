<?php

namespace Modules\Projects\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Projects\Incident;
use Modules\Projects\Improvement;
use Modules\Projects\Project;
use Modules\Tasks\Task;

class QualityController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    public function index(Request $r)
    {
        $iq = Incident::with(['project', 'task'])->orderByDesc('occurred_on')->orderByDesc('id');
        if ($r->filled('status')) {
            $iq->where('status', $r->status);
        }
        $mq = Improvement::orderByDesc('id');
        if ($r->filled('mstatus')) {
            $mq->where('status', $r->mstatus);
        }

        return view('quality.index', [
            'incidents' => $iq->paginate(perPage(), ['*'], 'ipage')->withQueryString(),
            'improvements' => $mq->orderBy('id')->get(),
            'status' => $r->status, 'mstatus' => $r->mstatus,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'tasks' => Task::whereNotIn('status', ['done'])->orderByDesc('id')->limit(200)->get(['id', 'title']),
        ]);
    }

    public function incidentStore(Request $r)
    {
        $data = $r->validate([
            'title' => 'required|string|max:255',
            'project_id' => 'nullable|exists:projects,id', 'task_id' => 'nullable|exists:tasks,id',
            'severity' => 'required|in:low,medium,high,critical',
            'what_happened' => 'nullable|string', 'occurred_on' => 'nullable|date|before_or_equal:today',
        ]);
        Incident::create([...$data, 'status' => 'open', 'created_by' => auth()->id()]);

        return back()->with('ok', 'Incident logged — add the root cause once known.');
    }

    public function incidentUpdate(Request $r, Incident $incident)
    {
        $data = $r->validate([
            'root_cause' => 'nullable|string', 'fix' => 'nullable|string',
            'status' => 'required|in:open,fixing,fixed',
        ]);
        $incident->update($data);

        return back()->with('ok', 'Incident updated.');
    }

    public function incidentDestroy(Incident $incident)
    {
        $incident->delete();

        return back()->with('ok', 'Incident removed.');
    }

    public function improvementStore(Request $r)
    {
        $data = $r->validate(['title' => 'required|string|max:255', 'detail' => 'nullable|string']);
        Improvement::create([...$data, 'status' => 'proposed', 'created_by' => auth()->id()]);

        return back()->with('ok', 'Idea captured for the next retro.');
    }

    public function improvementMove(Request $r, Improvement $improvement)
    {
        $data = $r->validate(['status' => 'required|in:proposed,doing,done']);
        $improvement->update(['status' => $data['status']]);

        return back()->with('ok', 'Moved to '.$data['status'].'.');
    }

    public function improvementDestroy(Improvement $improvement)
    {
        $improvement->delete();

        return back()->with('ok', 'Idea removed.');
    }
}
