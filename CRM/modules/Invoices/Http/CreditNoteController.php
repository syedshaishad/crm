<?php

namespace Modules\Invoices\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Clients\Client;
use Modules\Core\Numbering\NextNumber;
use Modules\Invoices\CreditNote;
use Modules\Invoices\Invoice;
use Modules\Payments\Payment;
use Modules\Settings\SettingStore;

class CreditNoteController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
    }
    public function index(Request $r)
    {
        $q = CreditNote::with('client')->orderByDesc('id');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $s)->orWhere('company', 'like', $s)));
        }
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }

        return view('credits.index', ['notes' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'status' => $r->status]);
    }

    public function create(Request $r)
    {
        $invoice = $r->invoice_id ? Invoice::with('client')->find($r->invoice_id) : null;

        return view('credits.form', [
            'note' => new CreditNote(['client_id' => $invoice->client_id ?? $r->client_id, 'invoice_id' => $invoice?->id, 'date' => now()->toDateString()]),
            'clients' => Client::orderBy('name')->get(),
            'invoice' => $invoice,
            'prefix' => app(SettingStore::class)->get('credit.prefix', 'CN-'),
        ]);
    }

    public function store(Request $r, SettingStore $s)
    {
        $data = $r->validate([
            'client_id' => 'required|exists:clients,id', 'invoice_id' => 'nullable|exists:invoices,id',
            'date' => 'nullable|date', 'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255', 'notes' => 'nullable|string',
        ]);
        $prefix = $s->get('credit.prefix', 'CN-');
        $note = CreditNote::create([
            'number' => NextNumber::take($prefix, 'credit_notes'),
            'client_id' => $data['client_id'], 'invoice_id' => $data['invoice_id'] ?? null,
            'date' => $data['date'] ?? now()->toDateString(), 'amount' => $data['amount'],
            'status' => 'open', 'reason' => $data['reason'] ?? null, 'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('credits.show', $note)->with('ok', 'Credit note '.$note->number.' created.');
    }

    public function show(CreditNote $creditNote)
    {
        $creditNote->load(['client', 'invoice']);
        $openInvoices = Invoice::where('client_id', $creditNote->client_id)
            ->whereNotIn('status', ['paid', 'cancelled'])->orderByDesc('id')->get(['id', 'number', 'total', 'paid_amount']);

        return view('credits.show', ['note' => $creditNote, 'openInvoices' => $openInvoices]);
    }

    /** Apply (part of) the credit against an open invoice — books a 'credit' payment. */
    public function apply(Request $r, CreditNote $creditNote, SettingStore $s)
    {
        $data = $r->validate(['invoice_id' => 'required|exists:invoices,id', 'amount' => 'required|numeric|min:0.01']);
        $invoice = Invoice::findOrFail($data['invoice_id']);
        if ($invoice->client_id !== $creditNote->client_id) {
            return back()->withErrors(['invoice' => 'That invoice belongs to a different client.']);
        }
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return back()->withErrors(['invoice' => 'That invoice needs no money.']);
        }
        $bal = $invoice->balance();
        $alloc = min(round((float) $data['amount'], 2), $creditNote->remaining(), $bal);
        if ($alloc <= 0) {
            return back()->withErrors(['amount' => 'Nothing left to apply.']);
        }
        $payPrefix = $s->get('payments.prefix', 'PAY-');
        Payment::create([
            'number' => NextNumber::take($payPrefix, 'payments'),
            'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id, 'amount' => $alloc,
            'method' => 'credit', 'reference' => $creditNote->number,
            'paid_on' => now()->toDateString(), 'notes' => 'Applied from credit note '.$creditNote->number.'.',
        ]);
        $creditNote->increment('applied_amount', $alloc);
        $creditNote->update(['status' => $creditNote->fresh()->remaining() <= 0 ? 'applied' : 'open']);

        return back()->with('ok', money($alloc).' applied to '.$invoice->number.'.');
    }

    public function destroy(CreditNote $creditNote)
    {
        if ($creditNote->applied_amount > 0) {
            return back()->withErrors(['credit' => 'Partly applied — delete the credit payments first.']);
        }
        $creditNote->delete();

        return redirect()->route('credits.index')->with('ok', 'Credit note moved to Trash.');
    }
}

