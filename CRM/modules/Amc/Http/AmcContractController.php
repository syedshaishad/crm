<?php

namespace Modules\Amc\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Amc\AmcContract;
use Modules\Clients\Client;
use Modules\Invoices\Invoice;
use Modules\Products\Product;

class AmcContractController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:sales');
    }

    public function index(Request $r)
    {
        $q = AmcContract::with(['client', 'product'])->orderBy('end_date');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhere('title', 'like', $s)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $s)->orWhere('company', 'like', $s)));
        }
        if ($r->filled('state') && in_array($r->state, ['active', 'expiring', 'expired'], true)) {
            $today = now()->toDateString();
            $edge = now()->addDays(30)->toDateString();
            $q->whereNotNull('end_date');
            if ($r->state === 'expired') {
                $q->where('end_date', '<', $today);
            } elseif ($r->state === 'expiring') {
                $q->where('end_date', '>=', $today)->where('end_date', '<=', $edge);
            } else {
                $q->where('end_date', '>', $edge);
            }
        }
        $stats = [
            'active' => AmcContract::whereNotNull('end_date')->where('end_date', '>', now()->addDays(30)->toDateString())->count(),
            'expiring' => AmcContract::whereNotNull('end_date')->where('end_date', '>=', now()->toDateString())->where('end_date', '<=', now()->addDays(30)->toDateString())->count(),
            'expired' => AmcContract::whereNotNull('end_date')->where('end_date', '<', now()->toDateString())->count(),
        ];

        return view('amc.index', ['contracts' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'state' => $r->state, 'stats' => $stats]);
    }

    public function create(Request $r)
    {
        return view('amc.form', [
            'contract' => new AmcContract(['type' => 'amc', 'start_date' => now()->toDateString(), 'client_id' => $r->client_id, 'invoice_id' => $r->invoice_id, 'product_id' => $r->product_id]),
            'clients' => Client::orderBy('name')->get(), 'products' => Product::active()->orderBy('name')->get(), 'invoices' => Invoice::orderByDesc('id')->limit(200)->get(['id', 'number']),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'title' => 'required|string|max:255', 'type' => 'required|in:warranty,amc',
            'client_id' => 'required|exists:clients,id', 'product_id' => 'nullable|exists:products,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
            'value' => 'nullable|numeric|min:0', 'notes' => 'nullable|string',
        ]);
        $contract = AmcContract::create([
            'number' => $this->nextNumber(), 'title' => $data['title'], 'type' => $data['type'],
            'client_id' => $data['client_id'], 'product_id' => $data['product_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'start_date' => $data['start_date'] ?? null, 'end_date' => $data['end_date'] ?? null,
            'value' => $data['value'] ?? 0, 'notes' => $data['notes'] ?? null, 'created_by' => auth()->id(),
        ]);

        return redirect()->route('amc.show', $contract)->with('ok', 'Contract recorded.');
    }

    public function show(AmcContract $amcContract)
    {
        $amcContract->load(['client', 'product', 'invoice']);

        return view('amc.show', ['contract' => $amcContract, 'state' => $amcContract->state()]);
    }

    public function edit(AmcContract $amcContract)
    {
        return view('amc.form', [
            'contract' => $amcContract,
            'clients' => Client::orderBy('name')->get(), 'products' => Product::active()->orderBy('name')->get(), 'invoices' => Invoice::orderByDesc('id')->limit(200)->get(['id', 'number']),
        ]);
    }

    public function update(Request $r, AmcContract $amcContract)
    {
        $data = $r->validate([
            'title' => 'required|string|max:255', 'type' => 'required|in:warranty,amc',
            'client_id' => 'required|exists:clients,id', 'product_id' => 'nullable|exists:products,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
            'value' => 'nullable|numeric|min:0', 'notes' => 'nullable|string',
        ]);
        $amcContract->update([
            'title' => $data['title'], 'type' => $data['type'],
            'client_id' => $data['client_id'], 'product_id' => $data['product_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'start_date' => $data['start_date'] ?? null, 'end_date' => $data['end_date'] ?? null,
            'value' => $data['value'] ?? 0, 'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('amc.show', $amcContract)->with('ok', 'Contract updated.');
    }

    public function destroy(AmcContract $amcContract)
    {
        $amcContract->delete();

        return back()->with('ok', 'Contract moved to Trash.');
    }

    /** Renew: fresh 12-month contract continuing where this one ends. */
    public function renew(AmcContract $amcContract)
    {
        $start = $amcContract->end_date ? $amcContract->end_date->copy()->addDay()->toDateString() : now()->toDateString();
        $next = AmcContract::create([
            'number' => $this->nextNumber(), 'title' => $amcContract->title, 'type' => 'amc',
            'client_id' => $amcContract->client_id, 'product_id' => $amcContract->product_id,
            'invoice_id' => $amcContract->invoice_id,
            'start_date' => $start, 'end_date' => \Carbon\Carbon::parse($start)->addYear()->subDay()->toDateString(),
            'value' => $amcContract->value, 'notes' => 'Renewed from '.$amcContract->number.'.', 'created_by' => auth()->id(),
        ]);

        return redirect()->route('amc.show', $next)->with('ok', 'Renewed as '.$next->number.'.');
    }

    protected function nextNumber(): string
    {
        return 'AMC-'.str_pad((string) ((AmcContract::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
