<?php

namespace Modules\Cheques\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Cheques\PdcCheque;
use Modules\Clients\Client;
use Modules\Core\Numbering\NextNumber;
use Modules\Invoices\Invoice;
use Modules\Payments\Payment;

class PdcChequeController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
    }

    public function index(Request $r)
    {
        $q = PdcCheque::with(['client', 'invoice'])->orderBy('cheque_date');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhere('cheque_no', 'like', $s)->orWhere('bank', 'like', $s)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $s)->orWhere('company', 'like', $s)));
        }
        if ($r->filled('status') && array_key_exists($r->status, PdcCheque::statuses())) {
            $q->where('status', $r->status);
        }
        $stats = [
            'held' => (float) PdcCheque::where('status', 'held')->sum('amount'),
            'due' => PdcCheque::where('status', 'held')->whereDate('cheque_date', '<=', now()->toDateString())->count(),
            'bounced' => PdcCheque::where('status', 'bounced')->count(),
        ];

        return view('cheques.index', ['cheques' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'status' => $r->status, 'stats' => $stats]);
    }

    public function create(Request $r)
    {
        return view('cheques.form', [
            'cheque' => new PdcCheque(['client_id' => $r->client_id, 'invoice_id' => $r->invoice_id, 'cheque_date' => now()->toDateString()]),
            'clients' => Client::orderBy('name')->get(), 'invoices' => Invoice::orderByDesc('id')->limit(200)->get(['id', 'number', 'client_id']),
        ]);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $cheque = PdcCheque::create($data + ['number' => $this->nextNumber(), 'status' => 'held', 'created_by' => auth()->id()]);

        return redirect()->route('cheques.show', $cheque)->with('ok', 'Cheque recorded — held safely.');
    }

    public function show(PdcCheque $cheque)
    {
        $cheque->load(['client', 'invoice', 'payment']);

        return view('cheques.show', ['cheque' => $cheque]);
    }

    public function edit(PdcCheque $cheque)
    {
        abort_if(in_array($cheque->status, ['cleared', 'cancelled'], true), 400, 'Settled cheques cannot be edited.');

        return view('cheques.form', [
            'cheque' => $cheque,
            'clients' => Client::orderBy('name')->get(), 'invoices' => Invoice::orderByDesc('id')->limit(200)->get(['id', 'number', 'client_id']),
        ]);
    }

    public function update(Request $r, PdcCheque $cheque)
    {
        abort_if(in_array($cheque->status, ['cleared', 'cancelled'], true), 400, 'Settled cheques cannot be edited.');
        $cheque->update($this->validated($r));

        return redirect()->route('cheques.show', $cheque)->with('ok', 'Cheque updated.');
    }

    public function destroy(PdcCheque $cheque)
    {
        abort_if($cheque->status === 'cleared', 400, 'A cleared cheque stays on record — cancel instead.');
        $cheque->delete();

        return back()->with('ok', 'Cheque moved to Trash.');
    }

    public function deposit(Request $r, PdcCheque $cheque)
    {
        abort_if($cheque->status !== 'held', 400, 'Only held cheques can be deposited.');
        $data = $r->validate(['deposited_on' => 'required|date']);
        $cheque->update(['status' => 'deposited', 'deposited_on' => $data['deposited_on']]);

        return back()->with('ok', 'Marked deposited — clear it when the bank confirms.');
    }

    /** Cleared: books a cheque payment (capped at the invoice balance). */
    public function clear(Request $r, PdcCheque $cheque)
    {
        abort_if(! in_array($cheque->status, ['held', 'deposited'], true), 400, 'Only held or deposited cheques can clear.');
        $data = $r->validate(['cleared_on' => 'required|date']);
        $inv = $cheque->invoice;
        $amount = (float) $cheque->amount;
        if ($inv && ! in_array($inv->status, ['cancelled'], true)) {
            $amount = min($amount, $inv->balance());
            if ($amount <= 0) {
                return back()->withErrors(['cheque' => 'The linked invoice is already fully paid.']);
            }
        }
        $payment = Payment::create([
            'number' => NextNumber::take(setting('payments.prefix', 'PAY-'), 'payments'),
            'public_token' => Str::random(32),
            'client_id' => $cheque->client_id, 'invoice_id' => $cheque->invoice_id,
            'amount' => $amount, 'method' => 'cheque', 'reference' => $cheque->cheque_no.($cheque->bank ? ' · '.$cheque->bank : ''),
            'paid_on' => $data['cleared_on'], 'notes' => 'Cleared from '.$cheque->number.'.',
        ]);
        $cheque->update(['status' => 'cleared', 'cleared_on' => $data['cleared_on'], 'payment_id' => $payment->id]);
        activity('cheque-cleared', $cheque, 'Cheque '.$cheque->cheque_no.' cleared '.money($amount));

        return back()->with('ok', 'Cleared — payment '.$payment->number.' of '.money($amount).' booked.');
    }

    public function bounce(Request $r, PdcCheque $cheque)
    {
        abort_if(! in_array($cheque->status, ['held', 'deposited'], true), 400, 'Only held or deposited cheques can bounce.');
        $data = $r->validate(['notes' => 'nullable|string']);
        $cheque->update(['status' => 'bounced', 'notes' => trim(($cheque->notes ? $cheque->notes."\n" : '').'Bounced '.now()->toDateString().($data['notes'] ? ': '.$data['notes'] : '.'))]);
        activity('cheque-bounced', $cheque, 'Cheque '.$cheque->cheque_no.' bounced');

        return back()->with('warn', 'Marked bounced — follow up with '.($cheque->client->company ?: $cheque->client->name).'.');
    }

    public function cancel(PdcCheque $cheque)
    {
        abort_if($cheque->status === 'cleared', 400, 'A cleared cheque stays on record.');
        $cheque->update(['status' => 'cancelled']);

        return back()->with('ok', 'Cheque cancelled.');
    }

    protected function validated(Request $r): array
    {
        return $r->validate([
            'client_id' => 'required|exists:clients,id', 'invoice_id' => 'nullable|exists:invoices,id',
            'cheque_no' => 'required|string|max:40', 'bank' => 'nullable|string|max:120',
            'amount' => 'required|numeric|min:0.01', 'cheque_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);
    }

    protected function nextNumber(): string
    {
        return 'CHQ-'.str_pad((string) ((PdcCheque::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
