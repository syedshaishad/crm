<?php

namespace Modules\Challans\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Challans\DeliveryChallan;
use Modules\Clients\Client;
use Modules\Core\Numbering\NextNumber;
use Modules\Invoices\Invoice;

class DeliveryChallanController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
    }

    public function index(Request $r)
    {
        $q = DeliveryChallan::with('client')->orderByDesc('id');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $s)->orWhere('company', 'like', $s)));
        }
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }

        return view('challans.index', ['challans' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'status' => $r->status]);
    }

    public function create(Request $r)
    {
        $prefill = null;
        if ($r->filled('invoice_id') && $inv = Invoice::with('items')->find($r->invoice_id)) {
            $prefill = $inv;
        }

        return view('challans.form', [
            'challan' => new DeliveryChallan(['challan_date' => now()->toDateString(), 'client_id' => $prefill?->client_id, 'invoice_id' => $prefill?->id, 'place_of_supply' => $prefill?->place_of_supply]),
            'clients' => Client::orderBy('name')->get(),
            'prefillItems' => $prefill ? $prefill->items->map(fn ($it) => ['description' => $it->description, 'hsn_code' => $it->hsn_code, 'qty' => $it->qty, 'unit' => $it->unit ?? 'NOS', 'rate' => $it->rate]) : collect(),
        ]);
    }

    public function store(Request $r)
    {
        $r->merge(['items' => collect($r->input('items', []))->filter(fn ($it) => trim((string) ($it['description'] ?? '')) !== '')->values()->all()]);
        $data = $r->validate([
            'client_id' => 'required|exists:clients,id', 'invoice_id' => 'nullable|exists:invoices,id',
            'challan_date' => 'nullable|date', 'place_of_supply' => 'nullable|string|max:100',
            'vehicle_no' => 'nullable|string|max:20', 'transporter' => 'nullable|string|max:255',
            'eway_bill' => 'nullable|string|max:20', 'notes' => 'nullable|string',
            'items' => 'required|array|min:1', 'items.*.description' => 'required|string',
            'items.*.hsn_code' => 'nullable|string|max:20',
            'items.*.qty' => 'required|numeric|min:0.01', 'items.*.unit' => 'nullable|string|max:10',
            'items.*.rate' => 'nullable|numeric|min:0',
        ]);
        $challan = DeliveryChallan::create([
            'number' => $this->nextNumber(), 'client_id' => $data['client_id'], 'invoice_id' => $data['invoice_id'] ?? null,
            'challan_date' => $data['challan_date'] ?? now()->toDateString(),
            'place_of_supply' => $data['place_of_supply'] ?? null, 'vehicle_no' => $data['vehicle_no'] ?? null,
            'transporter' => $data['transporter'] ?? null, 'eway_bill' => $data['eway_bill'] ?? null,
            'status' => 'draft', 'notes' => $data['notes'] ?? null, 'created_by' => auth()->id(),
        ]);
        foreach ($data['items'] as $it) {
            $challan->items()->create(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'qty' => $it['qty'], 'unit' => $it['unit'] ?? 'NOS', 'rate' => $it['rate'] ?? 0, 'amount' => round($it['qty'] * ($it['rate'] ?? 0), 2)]);
        }
        $challan->recalc();

        return redirect()->route('challans.show', $challan)->with('ok', 'Delivery challan created.');
    }

    public function show(DeliveryChallan $challan)
    {
        $challan->load(['items', 'client', 'invoice']);

        return view('challans.show', compact('challan'));
    }

    public function edit(DeliveryChallan $challan)
    {
        if ($challan->status !== 'draft') {
            return redirect()->route('challans.show', $challan)->withErrors(['status' => 'Only draft challans can be edited.']);
        }

        return view('challans.form', [
            'challan' => $challan->load('items'), 'clients' => Client::orderBy('name')->get(), 'prefillItems' => collect(),
        ]);
    }

    public function update(Request $r, DeliveryChallan $challan)
    {
        if ($challan->status !== 'draft') {
            return back()->withErrors(['status' => 'Only draft challans can be edited.']);
        }
        $r->merge(['items' => collect($r->input('items', []))->filter(fn ($it) => trim((string) ($it['description'] ?? '')) !== '')->values()->all()]);
        $data = $r->validate([
            'client_id' => 'required|exists:clients,id', 'invoice_id' => 'nullable|exists:invoices,id',
            'challan_date' => 'nullable|date', 'place_of_supply' => 'nullable|string|max:100',
            'vehicle_no' => 'nullable|string|max:20', 'transporter' => 'nullable|string|max:255',
            'eway_bill' => 'nullable|string|max:20', 'notes' => 'nullable|string',
            'items' => 'sometimes|array', 'items.*.description' => 'required|string',
            'items.*.hsn_code' => 'nullable|string|max:20',
            'items.*.qty' => 'required|numeric|min:0.01', 'items.*.unit' => 'nullable|string|max:10',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items_existing' => 'sometimes|array',
            'items_existing.*.description' => 'required|string',
            'items_existing.*.hsn_code' => 'nullable|string|max:20',
            'items_existing.*.qty' => 'required|numeric|min:0.01', 'items_existing.*.unit' => 'nullable|string|max:10',
            'items_existing.*.rate' => 'nullable|numeric|min:0',
            'remove_items' => 'sometimes|array', 'remove_items.*' => 'integer',
        ]);
        $challan->update([
            'client_id' => $data['client_id'], 'invoice_id' => $data['invoice_id'] ?? null,
            'challan_date' => $data['challan_date'] ?? $challan->challan_date,
            'place_of_supply' => $data['place_of_supply'] ?? null, 'vehicle_no' => $data['vehicle_no'] ?? null,
            'transporter' => $data['transporter'] ?? null, 'eway_bill' => $data['eway_bill'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $remove = collect($data['remove_items'] ?? [])->map(fn ($v) => (int) $v);
        foreach ($data['items_existing'] ?? [] as $id => $it) {
            $line = $challan->items()->find((int) $id);
            if (! $line) {
                continue;
            }
            if ($remove->contains($line->id)) {
                $line->delete();

                continue;
            }
            $line->update(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'qty' => $it['qty'], 'unit' => $it['unit'] ?? 'NOS', 'rate' => $it['rate'] ?? 0, 'amount' => round($it['qty'] * ($it['rate'] ?? 0), 2)]);
        }
        if ($challan->items()->whereIn('id', $remove)->exists()) {
            $challan->items()->whereIn('id', $remove)->delete();
        }
        if (! empty($data['items'])) {
            foreach ($data['items'] as $it) {
                $challan->items()->create(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'qty' => $it['qty'], 'unit' => $it['unit'] ?? 'NOS', 'rate' => $it['rate'] ?? 0, 'amount' => round($it['qty'] * ($it['rate'] ?? 0), 2)]);
            }
        }
        $challan->recalc();

        return redirect()->route('challans.show', $challan)->with('ok', 'Challan updated.');
    }

    public function destroy(DeliveryChallan $challan)
    {
        $challan->delete();

        return back()->with('ok', 'Challan moved to Trash.');
    }

    public function issue(DeliveryChallan $challan)
    {
        if ($challan->status !== 'draft') {
            return back()->withErrors(['status' => 'Only draft challans can be issued.']);
        }
        if ($challan->items()->count() === 0) {
            return back()->withErrors(['items' => 'Add at least one line first.']);
        }
        $challan->update(['status' => 'issued']);

        return back()->with('ok', 'Challan issued.');
    }

    protected function nextNumber(): string
    {
        return NextNumber::take('DC-', 'delivery_challans');
    }
}
