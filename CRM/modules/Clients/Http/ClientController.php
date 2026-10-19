<?php

namespace Modules\Clients\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Branding\Brand;
use Modules\Clients\Client;
use Modules\Clients\ClientProductPrice;
use Modules\Expenses\Expense;
use Modules\Payments\Payment;

class ClientController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:sales')->except(['ledger', 'ledgerExport', 'statement']);
        $this->middleware('perm:billing')->only(['ledger', 'ledgerExport', 'statement']);
    }

    public function index(Request $r)
    {
        $q = Client::withSum('invoices as billed', 'total')->withSum('invoices as collected', 'paid_amount')->orderByDesc('id');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('name', 'like', $s)->orWhere('company', 'like', $s)->orWhere('email', 'like', $s)->orWhere('phone', 'like', $s));
        }
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }
        $stats = [
            'total' => Client::count(),
            'active' => Client::where('status', 'active')->count(),
            'new_month' => Client::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count(),
            'outstanding' => max(0, (float) \DB::table('invoices')->whereNotIn('status', ['cancelled'])->sum('total') - (float) \DB::table('invoices')->whereNotIn('status', ['cancelled'])->sum('paid_amount')),
        ];

        return view('clients.index', ['clients' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'status' => $r->status, 'stats' => $stats]);
    }

    public function show(Client $client)
    {
        $client->load(['projects.tasks', 'projects.documents', 'invoices.payments', 'documents']);
        $outstanding = max(0, $client->invoices->sum('total') - $client->invoices->sum('paid_amount'));
        $prices = ClientProductPrice::with('product')->where('client_id', $client->id)->orderBy('id')->get();
        $products = \Modules\Products\Product::active()->orderBy('name')->get(['id', 'name', 'rate']);

        return view('clients.show', compact('client', 'outstanding', 'prices', 'products'));
    }

    /** Client-specific catalog rate used by the invoice/proposal picker. */
    public function priceStore(Request $r, Client $client)
    {
        $data = $r->validate([
            'product_id' => 'required|exists:products,id', 'rate' => 'required|numeric|min:0',
        ]);
        ClientProductPrice::updateOrCreate(
            ['client_id' => $client->id, 'product_id' => $data['product_id']],
            ['rate' => $data['rate']]
        );

        return back()->with('ok', 'Special rate saved.');
    }

    public function priceDestroy(Client $client, ClientProductPrice $price)
    {
        if ($price->client_id !== $client->id) {
            abort(404);
        }
        $price->delete();

        return back()->with('ok', 'Special rate removed — picker falls back to catalog rate.');
    }

    /** Picker support: {product_id: rate} overrides for a client. */
    public function prices(Client $client)
    {
        return response()->json(
            ClientProductPrice::where('client_id', $client->id)->pluck('rate', 'product_id')
        );
    }

    /**
     * Account ledger: every filed invoice (debit), every payment and every
     * credit note (credit) in date order with a running balance.
     * Drafts and cancelled invoices never enter the books.
     */
    public function ledger(Client $client)
    {
        $data = $this->ledgerData($client);

        return view('clients.ledger', array_merge(compact('client'), $data));
    }

    public function ledgerExport(Client $client)
    {
        $data = $this->ledgerData($client);
        $rows = collect($data['rows'])->map(fn ($e) => [
            'Date' => $e['date'], 'Type' => $e['type'], 'Reference' => $e['ref'],
            'Details' => $e['details'], 'Debit' => number_format($e['debit'], 2, '.', ''),
            'Credit' => number_format($e['credit'], 2, '.', ''), 'Balance' => number_format($e['balance'], 2, '.', ''),
        ]);

        return csv_response("ledger-{$client->id}-".now()->toDateString().'.csv', $rows);
    }

    /** Email the full ledger statement with pay links for open invoices. */
    public function statement(Client $client)
    {
        if (empty($client->email)) {
            return back()->withErrors(['email' => 'Client has no email address.']);
        }
        $data = $this->ledgerData($client);
        if (empty($data['rows'])) {
            return back()->withErrors(['email' => 'Nothing to send — this ledger is empty.']);
        }
        try {
            $brand = app(Brand::class)->all();
        } catch (\Throwable $e) {
            $brand = ['company_name' => config('app.name')];
        }
        $fmt = fn ($v) => ($v < 0 ? '−' : '+').money(abs($v));
        $rows = collect($data['rows'])->map(fn ($e) => $e['date'].' · '.$e['type'].' '.$e['ref'].' · '.$fmt($e['debit'] - $e['credit']).' · bal '.money($e['balance']))->implode("\n");
        $paylinks = [];
        foreach ($client->invoices()->whereNotIn('status', ['draft', 'cancelled'])->orderBy('due_date')->get() as $inv) {
            if ($inv->balance() <= 0) {
                continue;
            }
            if (! $inv->public_token) {
                $inv->update(['public_token' => Str::random(32)]);
            }
            $paylinks[] = 'Pay '.$inv->number.' ('.money($inv->balance()).'): '.route('invoices.public', $inv->public_token);
        }
        $tpl = [
            'company_name' => $brand['company_name'] ?? config('app.name'), 'client_name' => $client->name,
            'billed' => money($data['totals']['billed']), 'received' => money($data['totals']['collected'] + $data['totals']['tds']),
            'balance' => money($data['totals']['balance']), 'rows' => $rows,
            'paylinks' => $paylinks ? "Pay online:\n".implode("\n", $paylinks) : '',
        ];
        try {
            Mail::send('emails.template', ['body' => render_template('email.statement_body', $tpl)], function ($m) use ($client, $tpl) {
                $m->to($client->email, $client->name)->subject(render_template('email.statement_subject', $tpl));
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => 'Could not send email: '.$e->getMessage().' — check Settings → Integrations (SMTP).']);
        }

        return back()->with('ok', 'Statement emailed to '.$client->email);
    }

    public function ledgerData(Client $client, bool $portal = false): array
    {
        $entries = [];
        $invoices = $client->invoices()->whereNotIn('status', ['draft', 'cancelled'])->orderBy('issue_date')->orderBy('id')->get();
        foreach ($invoices as $inv) {
            $entries[] = [
                'sort' => $inv->issue_date->toDateString().'/'.$inv->id,
                'date' => $inv->issue_date->toDateString(), 'type' => 'Invoice', 'ref' => $inv->number,
                'details' => ucfirst($inv->status), 'debit' => (float) $inv->total, 'credit' => 0.0,
                'link' => $portal
                    ? ($inv->public_token ? route('invoices.public', $inv->public_token) : null)
                    : route('invoices.show', $inv),
            ];
        }
        $payments = Payment::with('invoice')->where('client_id', $client->id)->orderBy('paid_on')->orderBy('id')->get();
        foreach ($payments as $p) {
            $pdate = $p->paid_on->toDateString();
            $entries[] = [
                'sort' => $pdate.'/'.$p->id,
                'date' => $pdate, 'type' => 'Payment', 'ref' => $p->number ?: 'PAY-'.$p->id,
                'details' => ($p->method === 'upi' ? 'UPI' : ucfirst($p->method ?? '')).($p->invoice_id ? ' · '.$p->invoice?->number : ' · advance'),
                'debit' => 0.0, 'credit' => (float) $p->amount,
                'link' => $portal
                    ? ($p->public_token ? route('payments.public', $p->public_token) : null)
                    : ($p->invoice_id ? route('invoices.show', $p->invoice_id) : null),
            ];
        }
        $notes = \Modules\Invoices\CreditNote::where('client_id', $client->id)->orderByRaw('COALESCE(date, DATE(created_at))')->orderBy('id')->get();
        foreach ($notes as $n) {
            $ndate = substr((string) ($n->date ?? $n->created_at), 0, 10);
            $entries[] = [
                'sort' => $ndate.'/'.$n->id,
                'date' => (string) $ndate, 'type' => 'Credit note', 'ref' => $n->number,
                'details' => ucfirst($n->status ?? 'open'), 'debit' => 0.0, 'credit' => (float) $n->amount,
                'link' => $portal ? null : route('credits.show', $n),
            ];
        }
        $tdsRows = \Illuminate\Support\Facades\DB::table('tds_entries')->leftJoin('invoices', 'invoices.id', '=', 'tds_entries.invoice_id')->where('tds_entries.client_id', $client->id)->where('tds_entries.kind', 'receivable')->orderBy('deducted_on')->orderBy('tds_entries.id')->get(['tds_entries.*', 'invoices.number as invoice']);
        foreach ($tdsRows as $t) {
            $entries[] = [
                'sort' => $t->deducted_on.'/'.$t->id,
                'date' => (string) $t->deducted_on, 'type' => 'TDS', 'ref' => $t->section.' · '.$t->rate.'%',
                'details' => $t->invoice ? 'Deducted · '.$t->invoice : 'Deducted', 'debit' => 0.0, 'credit' => (float) $t->amount,
                'link' => $portal ? null : ($t->invoice_id ? route('invoices.show', $t->invoice_id) : null),
            ];
        }
        usort($entries, fn ($a, $b) => strcmp($a['sort'], $b['sort']));
        $bal = 0.0;
        foreach ($entries as &$e) {
            $bal = round($bal + $e['debit'] - $e['credit'], 2);
            $e['balance'] = $bal;
        }
        unset($e);
        $totals = [
            'billed' => round(array_sum(array_column($entries, 'debit')), 2),
            'collected' => round(collect($entries)->where('type', 'Payment')->sum('credit'), 2),
            'credits' => round(collect($entries)->where('type', 'Credit note')->sum('credit'), 2),
            'tds' => round(collect($entries)->where('type', 'TDS')->sum('credit'), 2),
            'balance' => $bal,
        ];

        return ['rows' => $entries, 'totals' => $totals];
    }

    public function create()
    {
        return view('clients.form', ['client' => new Client]);
    }

    public function store(Request $r)
    {
        $data = $r->validate(['name' => 'required|string', 'company' => 'nullable|string', 'email' => 'nullable|email', 'phone' => 'nullable|string', 'address' => 'nullable|string', 'gstin' => 'nullable|string|max:20', 'pan' => 'nullable|string|max:10', 'state' => 'nullable|string|max:100', 'status' => 'nullable|string', 'birthday' => 'nullable|date', 'credit_limit' => 'nullable|numeric|min:0', 'currency' => 'nullable|string|size:3', 'notes' => 'nullable|string']);
        if ($err = gstin_check($data['gstin'] ?? null)) {
            return back()->withErrors(['gstin' => $err])->withInput();
        }
        if (! empty($data['gstin'])) {
            $data['gstin'] = strtoupper(trim($data['gstin']));
        }
        $data['custom_fields'] = $this->customFields($r);
        Client::create($data);

        return redirect()->route('clients.index')->with('ok', 'Client saved.');
    }

    /** Label/value custom fields from the client form (max 10, blanks dropped). */
    protected function customFields(Request $r): ?array
    {
        $out = [];
        foreach ((array) $r->input('cf_label', []) as $i => $label) {
            $label = trim(substr((string) $label, 0, 60));
            $value = trim(substr((string) ($r->input('cf_value', [])[$i] ?? ''), 0, 255));
            if ($label === '' || $value === '') {
                continue;
            }
            $out[$label] = $value;
            if (count($out) >= 10) {
                break;
            }
        }

        return $out ?: null;
    }

    public function edit(Client $client)
    {
        return view('clients.form', ['client' => $client]);
    }

    public function update(Request $r, Client $client)
    {
        $data = $r->validate(['name' => 'required|string', 'company' => 'nullable|string', 'email' => 'nullable|email', 'phone' => 'nullable|string', 'address' => 'nullable|string', 'gstin' => 'nullable|string|max:20', 'pan' => 'nullable|string|max:10', 'state' => 'nullable|string|max:100', 'status' => 'nullable|string', 'birthday' => 'nullable|date', 'credit_limit' => 'nullable|numeric|min:0', 'currency' => 'nullable|string|size:3', 'notes' => 'nullable|string']);
        $gstinChanged = strtoupper(trim((string) ($data['gstin'] ?? ''))) !== strtoupper(trim((string) ($client->gstin ?? '')));
        if ($gstinChanged && ($err = gstin_check($data['gstin'] ?? null))) {
            return back()->withErrors(['gstin' => $err])->withInput();
        }
        if (! empty($data['gstin'])) {
            $data['gstin'] = strtoupper(trim($data['gstin']));
        }
        $data['custom_fields'] = $this->customFields($r);
        $client->update($data);

        return redirect()->route('clients.index')->with('ok', 'Client updated.');
    }

    public function destroy(Client $client)
    {
        if ($client->invoices()->exists() || Payment::where('client_id', $client->id)->exists()) {
            return back()->withErrors(['client' => 'This client has invoices or payments. Delete those first, or mark the client inactive instead.']);
        }
        Expense::whereIn('project_id', $client->projects()->pluck('id'))->update(['project_id' => null]);
        // Soft-cascade so nothing bypasses Trash at the DB level.
        foreach ($client->proposals()->get() as $proposal) {
            $proposal->items()->delete();
            $proposal->delete();
        }
        foreach ($client->projects()->get() as $project) {
            $project->delete();
        }
        $client->delete();

        return back()->with('ok', 'Client moved to Trash.');
    }

    /** Bulk delete / status-change from the list checkboxes. */
    public function bulk(Request $r)
    {
        $data = $r->validate([
            'action' => 'required|in:delete,status', 'ids' => 'required|array|max:200', 'ids.*' => 'integer',
            'status' => 'required_if:action,status|in:active,inactive',
        ]);
        $done = 0;
        $errors = [];
        foreach (Client::whereIn('id', $data['ids'])->get() as $client) {
            try {
                if ($data['action'] === 'delete') {
                    if ($client->invoices()->exists() || Payment::where('client_id', $client->id)->exists()) {
                        $errors[] = ($client->company ?: $client->name).': has invoices or payments';
                        continue;
                    }
                    Expense::whereIn('project_id', $client->projects()->pluck('id'))->update(['project_id' => null]);
                    $client->delete();
                } else {
                    $client->update(['status' => $data['status']]);
                }
                $done++;
            } catch (\Throwable $e) {
                $errors[] = ($client->company ?: $client->name).': failed';
            }
        }
        return response()->json(['ok' => true, 'done' => $done, 'errors' => array_slice($errors, 0, 5)]);
    }

}
