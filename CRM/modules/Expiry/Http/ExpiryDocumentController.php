<?php

namespace Modules\Expiry\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Expiry\ExpiryDocument;

class ExpiryDocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:compliance');
    }

    /** Per-row alert edge (valid_to minus its own remind_days), driver-aware. */
    protected function alertEdge(): string
    {
        return \DB::getDriverName() === 'sqlite'
            ? "date(valid_to, '-' || remind_days || ' days')"
            : 'DATE_SUB(valid_to, INTERVAL remind_days DAY)';
    }

    public function index(Request $r)
    {
        $q = ExpiryDocument::orderBy('valid_to');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhere('title', 'like', $s)->orWhere('ref_no', 'like', $s)->orWhere('authority', 'like', $s));
        }
        if ($r->filled('category') && array_key_exists($r->category, ExpiryDocument::categories())) {
            $q->where('category', $r->category);
        }
        if ($r->filled('state') && in_array($r->state, ['valid', 'expiring', 'expired'], true)) {
            $today = now()->toDateString();
            $edge = $this->alertEdge();
            $q->whereNotNull('valid_to');
            if ($r->state === 'expired') {
                $q->where('valid_to', '<', $today);
            } elseif ($r->state === 'valid') {
                $q->whereRaw("$edge > ?", [$today]);
            } else {
                $q->where('valid_to', '>=', $today)->whereRaw("$edge <= ?", [$today]);
            }
        }
        $today = now()->toDateString();
        $edge = $this->alertEdge();
        $stats = [
            'valid' => ExpiryDocument::whereNotNull('valid_to')->whereRaw("$edge > ?", [$today])->count(),
            'expiring' => ExpiryDocument::whereNotNull('valid_to')->where('valid_to', '>=', $today)->whereRaw("$edge <= ?", [$today])->count(),
            'expired' => ExpiryDocument::whereNotNull('valid_to')->where('valid_to', '<', $today)->count(),
        ];

        return view('expiry.index', ['docs' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'state' => $r->state, 'category' => $r->category, 'stats' => $stats]);
    }

    public function create()
    {
        return view('expiry.form', ['doc' => new ExpiryDocument(['remind_days' => 30, 'valid_from' => now()->toDateString()])]);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $doc = ExpiryDocument::create($data + ['number' => $this->nextNumber(), 'created_by' => auth()->id()]);

        return redirect()->route('expiry.show', $doc)->with('ok', 'Document tracked.');
    }

    public function show(ExpiryDocument $expiryDocument)
    {
        return view('expiry.show', ['doc' => $expiryDocument, 'state' => $expiryDocument->state()]);
    }

    public function edit(ExpiryDocument $expiryDocument)
    {
        return view('expiry.form', ['doc' => $expiryDocument]);
    }

    public function update(Request $r, ExpiryDocument $expiryDocument)
    {
        $expiryDocument->update($this->validated($r));

        return redirect()->route('expiry.show', $expiryDocument)->with('ok', 'Document updated.');
    }

    public function destroy(ExpiryDocument $expiryDocument)
    {
        $expiryDocument->delete();

        return back()->with('ok', 'Document moved to Trash.');
    }

    /** Renew in place: new validity starts where the old one ends. */
    public function renew(Request $r, ExpiryDocument $expiryDocument)
    {
        $data = $r->validate(['years' => 'required|integer|min:1|max:10']);
        $base = $expiryDocument->valid_to && $expiryDocument->valid_to->isFuture()
            ? $expiryDocument->valid_to->copy()
            : now();
        $expiryDocument->update([
            'valid_from' => $base->copy()->addDay()->toDateString(),
            'valid_to' => $base->copy()->addYears((int) $data['years'])->toDateString(),
            'notes' => trim(($expiryDocument->notes ? $expiryDocument->notes."\n" : '').'Renewed '.now()->toDateString().' for '.$data['years'].' yr.'),
        ]);

        return redirect()->route('expiry.show', $expiryDocument)->with('ok', 'Renewed — valid to '.d($expiryDocument->valid_to, 'j M Y').'.');
    }

    protected function validated(Request $r): array
    {
        return $r->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|in:'.implode(',', array_keys(ExpiryDocument::categories())),
            'ref_no' => 'nullable|string|max:120', 'authority' => 'nullable|string|max:160',
            'valid_from' => 'nullable|date', 'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'remind_days' => 'required|integer|min:1|max:365', 'notes' => 'nullable|string',
        ]);
    }

    protected function nextNumber(): string
    {
        return 'EXP-'.str_pad((string) ((ExpiryDocument::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
