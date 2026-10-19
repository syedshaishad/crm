<?php

namespace Modules\Documents\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Clients\Client;
use Modules\Documents\Document;
use Modules\Projects\Project;
use Modules\Settings\SettingStore;

class DocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:documents');
    }
    public function index(Request $r)
    {
        $clientId = (string) $r->input('client_id', '');
        $projectId = (string) $r->input('project_id', '');
        $q = Document::with('linkable')->orderByDesc('id');
        if ($r->filled('q')) {
            $q->where('name', 'like', '%'.$r->input('q').'%');
        }
        if ($projectId !== '') {
            $q->where('linkable_type', Project::class)->where('linkable_id', $projectId);
        } elseif ($clientId === 'general') {
            $q->whereNull('linkable_id');
        } elseif ($clientId !== '') {
            $pids = Project::where('client_id', $clientId)->pluck('id');
            $q->where(fn ($w) => $w
                ->where(fn ($x) => $x->where('linkable_type', Client::class)->where('linkable_id', $clientId))
                ->orWhere(fn ($x) => $x->where('linkable_type', Project::class)->whereIn('linkable_id', $pids)));
        }
        $docs = $q->paginate(perPage())->withQueryString();

        // Organise the page as client → projects tree (+ unfiled).
        $tree = [];
        $general = [];
        foreach ($docs as $dd) {
            $link = $dd->linkable;
            if ($link instanceof Project) {
                $c = $link->client;
                $cid = $c?->id ?? 0;
                $tree[$cid] ??= ['client' => $c, 'direct' => [], 'projects' => []];
                $tree[$cid]['projects'][$link->id] ??= ['project' => $link, 'items' => []];
                $tree[$cid]['projects'][$link->id]['items'][] = $dd;
            } elseif ($link instanceof Client) {
                $tree[$link->id] ??= ['client' => $link, 'direct' => [], 'projects' => []];
                $tree[$link->id]['client'] = $link;
                $tree[$link->id]['direct'][] = $dd;
            } else {
                $general[] = $dd;
            }
        }

        $clients = Client::with('projects:id,name,client_id')->orderBy('name')->get(['id', 'name', 'company']);
        $projCounts = Document::where('linkable_type', Project::class)->selectRaw('linkable_id, count(*) as c')->groupBy('linkable_id')->pluck('c', 'linkable_id');
        $cliCounts = Document::where('linkable_type', Client::class)->selectRaw('linkable_id, count(*) as c')->groupBy('linkable_id')->pluck('c', 'linkable_id');
        $generalCount = Document::whereNull('linkable_id')->count();
        $clientTotals = [];
        foreach ($clients as $c) {
            $t = (int) ($cliCounts[$c->id] ?? 0);
            foreach ($c->projects as $p) {
                $t += (int) ($projCounts[$p->id] ?? 0);
            }
            $clientTotals[$c->id] = $t;
        }
        $activeClient = ($clientId !== '' && $clientId !== 'general') ? Client::with('projects:id,name,client_id')->find($clientId) : null;
        $allProjects = Project::with('client:id,name,company')->orderBy('name')->get(['id', 'name', 'client_id']);

        return ($r->ajax() ? view('documents.explorer') : view('documents.index'))->with([
            'docs' => $docs, 'tree' => $tree, 'general' => $general,
            'docs' => $docs, 'tree' => $tree, 'general' => $general,
            'client_id' => $r->input('client_id', ''), 'project_id' => $r->input('project_id', ''), 'q' => $r->input('q', ''),
            'clients' => $clients, 'allProjects' => $allProjects,
            'projCounts' => $projCounts, 'clientTotals' => $clientTotals, 'generalCount' => $generalCount,
            'totalCount' => Document::count(), 'totalSize' => (int) Document::sum('size'),
            'activeClient' => $activeClient,
        ]);
    }

    public function store(Request $r)
    {
        $maxKb = (int) (app(SettingStore::class)->get('uploads.document_max_mb', 20) * 1024);
        $r->validate(['file' => 'required|file|max:'.$maxKb, 'filed_under' => 'nullable|string', 'project_id' => 'nullable|exists:projects,id']);
        $f = $r->file('file');
        if ($reason = upload_reject_reason($f)) {
            return back()->withErrors(['file' => $reason]);
        }
        [$type, $id] = $this->filing($r->input('filed_under'), $r->input('project_id'));
        Document::create([
            'name' => $f->getClientOriginalName(), 'path' => $f->store('documents', 'public'),
            'mime' => $f->getMimeType(), 'size' => $f->getSize(), 'uploaded_by' => auth()->id(),
            'linkable_type' => $type, 'linkable_id' => $id,
        ]);

        return back()->with('ok', 'Document uploaded.');
    }

    /** Move a document to General, a client, or one of its projects. */
    public function move(Request $r, Document $document)
    {
        $r->validate(['filed_under' => 'nullable|string', 'project_id' => 'nullable|exists:projects,id']);
        [$type, $id] = $this->filing($r->input('filed_under'), $r->input('project_id'));
        $document->update(['linkable_type' => $type, 'linkable_id' => $id]);

        return back()->with('ok', 'Document filed.');
    }

    /**
     * Resolve where a file lives: "c5" = client 5, "p3" = project 3,
     * "" = General. Legacy project_id input still works.
     *
     * @return array{0: string|null, 1: int|null}
     */
    protected function filing(?string $v, $legacyProjectId): array
    {
        if ($v !== null && $v !== '') {
            if (str_starts_with($v, 'p') && Project::whereKey((int) substr($v, 1))->exists()) {
                return [Project::class, (int) substr($v, 1)];
            }
            if (str_starts_with($v, 'c') && Client::whereKey((int) substr($v, 1))->exists()) {
                return [Client::class, (int) substr($v, 1)];
            }
            if (str_starts_with($v, 'u') && \App\Models\User::whereKey((int) substr($v, 1))->exists()) {
                return [\App\Models\User::class, (int) substr($v, 1)];
            }

            return [null, null];
        }
        if ($legacyProjectId) {
            return [Project::class, (int) $legacyProjectId];
        }

        return [null, null];
    }

    public function download(Document $document)
    {
        return Storage::disk('public')->download($document->path, $document->name);
    }

    public function destroy(Document $document)
    {
        $document->delete(); // soft: file stays so restore works

        return back()->with('ok', 'Document moved to Trash.');
    }
}
