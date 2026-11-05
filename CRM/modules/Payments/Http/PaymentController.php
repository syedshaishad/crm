<?php

namespace Modules\Payments\Http;

use App\Notifications\PaymentReceived;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Branding\Brand;
use Modules\Clients\Client;
use Modules\Core\Numbering\NextNumber;
use Modules\Invoices\CreditNote;
use Modules\Invoices\Invoice;
use Modules\Payments\Payment;
use Modules\Settings\SettingStore;
use Razorpay\Api\Api;

class PaymentController extends Controller
{
    public function __construct()
    {
        // Public receipt/checkout + server webhook stay open — the rest needs billing access.
        $this->middleware('perm:billing')->except(['publicView', 'publicOrder', 'publicVerify', 'webhook']);
    }
    public function index(Request $r)
    {
        $q = Payment::with(['invoice', 'client'])->orderByDesc('id');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhere('reference', 'like', $s)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $s)->orWhere('company', 'like', $s)));
        }
        if ($r->filled('method')) {
            $q->where('method', $r->method);
        }

        $base = Payment::query();
        $count = (clone $base)->count();
        $sum = (float) (clone $base)->sum('amount');
        $stats = [
            'total' => $count,
            'received' => $sum,
            'month' => (float) (clone $base)->whereBetween('paid_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum('amount'),
            'average' => $count > 0 ? $sum / $count : 0.0,
            'methods' => (clone $base)->selectRaw('method, COUNT(*) as c')->groupBy('method')->pluck('c', 'method')->all(),
        ];

        return view('payments.index', ['payments' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'method' => $r->method, 'stats' => $stats]);
    }

    public function create(Request $r)
    {
        // Don't open the form for invoices that need no money.
        if ($r->filled('invoice_id') && $inv = Invoice::find($r->invoice_id)) {
            if ($inv->status === 'cancelled' || $inv->balance() <= 0) {
                return redirect()->route('invoices.show', $inv)->withErrors(['payment' => 'This invoice is fully paid — no more payments needed.']);
            }
        }

        return view('payments.form', [
            'payment' => new Payment(['invoice_id' => $r->invoice_id, 'client_id' => $r->client_id]),
            'invoices' => Invoice::orderByDesc('id')->get(), 'clients' => Client::orderBy('name')->get(),
            'razorpay_enabled' => config('razorpay.enabled'),
            'razorpay_key' => config('razorpay.key_id'),
        ]);
    }

    public function store(Request $r, SettingStore $s)
    {
        $data = $r->validate([
            'invoice_id' => 'nullable|exists:invoices,id', 'client_id' => 'required|exists:clients,id',
            'amount' => 'required|numeric|min:0.01', 'method' => 'required|string',
            'reference' => 'nullable|string', 'paid_on' => 'required|date', 'notes' => 'nullable|string',
            'razorpay_payment_id' => 'nullable|string', 'razorpay_order_id' => 'nullable|string',
            'razorpay_signature' => 'nullable|string',
            'tds_section' => 'nullable|string|max:20', 'tds_rate' => 'nullable|numeric|min:0|max:30',
            'tds_amount' => 'nullable|numeric|min:0',
        ]);
        $tdsEnabled = setting('tax.tds_enabled', '0') === '1';
        $tds = round((float) ($data['tds_amount'] ?? 0), 2);
        unset($data['tds_section'], $data['tds_rate'], $data['tds_amount']);
        // Block payments against settled/cancelled invoices (even via direct POST).
        $inv = ! empty($data['invoice_id']) ? Invoice::find($data['invoice_id']) : null;
        if ($inv) {
            if ($inv->status === 'cancelled' || $inv->balance() <= 0) {
                return back()->withErrors(['invoice' => 'This invoice is already fully paid.'])->withInput();
            }
            // Cash + claimed TDS together must never exceed the outstanding balance.
            $bal = $inv->balance();
            if (round((float) $data['amount'] + ($tdsEnabled ? $tds : 0), 2) > $bal + 0.009) {
                return back()->withErrors(['amount' => 'Cash + TDS exceeds the outstanding balance of '.money($bal).'.'])->withInput();
            }
            if ($tdsEnabled && $tds > 0 && empty($r->input('tds_section'))) {
                return back()->withErrors(['tds_section' => 'Pick the TDS section for the claimed amount.'])->withInput();
            }
        } elseif ($tds > 0) {
            return back()->withErrors(['tds_amount' => 'TDS can only be claimed against an invoice.'])->withInput();
        }
        if (($data['method'] ?? '') === 'razorpay') {
            $err = $this->verifyRazorpay($data);
            if ($err) {
                return back()->withErrors(['method' => $err])->withInput();
            }
        }
        $data['number'] = $this->nextNumber($s);
        $data['public_token'] = Str::random(32);
        // Loyalty redemption settles like cash but draws down the client's points.
        $loyaltyUsed = 0;
        if (($data['method'] ?? '') === 'loyalty') {
            $bal = (float) (Client::find($data['client_id'])->loyalty_balance ?? 0);
            if (round((float) $data['amount'], 2) > $bal + 0.009) {
                return back()->withErrors(['amount' => 'Loyalty balance is only '.money($bal).' — lower the amount or use another method.'])->withInput();
            }
            $loyaltyUsed = round((float) $data['amount'], 2);
        }
        $payment = Payment::create($data);
        if ($loyaltyUsed > 0) {
            Client::where('id', $payment->client_id)->decrement('loyalty_balance', $loyaltyUsed);
        } else {
            // Earn loyalty on real receipts (never on redemptions).
            $earn = (float) setting('loyalty.earn_percent', 0);
            if ($earn > 0) {
                Client::where('id', $payment->client_id)->increment('loyalty_balance', round((float) $data['amount'] * $earn / 100, 2));
            }
        }
        if ($inv && $tdsEnabled && $tds > 0) {
            DB::table('tds_entries')->insert([
                'kind' => 'receivable', 'invoice_id' => $inv->id, 'payment_id' => $payment->id,
                'client_id' => $payment->client_id, 'section' => strtoupper($r->input('tds_section')),
                'rate' => (float) $r->input('tds_rate', 0), 'base_amount' => round((float) $data['amount'] + $tds, 2),
                'amount' => $tds, 'deducted_on' => $data['paid_on'], 'fy' => fy($data['paid_on']),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            Payment::syncInvoice($inv->id);
        }
        notify_roles(['admin', 'manager'], new PaymentReceived($payment));

        return redirect()->route('payments.show', $payment)->with('ok', 'Payment '.$payment->number.' recorded.'.($tds > 0 && $inv ? ' TDS of '.money($tds).' claimed.' : ''));
    }

    /** Split one receipt across many open invoices (oldest due first). */
    public function split(Request $r)
    {
        $clients = Client::orderBy('name')->get();
        $client = $r->filled('client_id') ? Client::find($r->client_id) : null;
        $open = collect();
        if ($client) {
            $open = Invoice::where('client_id', $client->id)->whereNotIn('status', ['draft', 'cancelled', 'paid'])
                ->orderBy('due_date')->orderBy('id')->get()->filter(fn ($i) => $i->balance() > 0)->values();
        }

        return view('payments.split', compact('clients', 'client', 'open'));
    }

    public function splitStore(Request $r, SettingStore $s)
    {
        $data = $r->validate([
            'client_id' => 'required|exists:clients,id',
            'method' => 'required|string', 'reference' => 'nullable|string|max:255',
            'paid_on' => 'required|date', 'notes' => 'nullable|string',
            'alloc' => 'required|array|min:1', 'alloc.*' => 'nullable|numeric|min:0',
        ]);
        if (($data['method'] ?? '') === 'loyalty') {
            return back()->withErrors(['method' => 'Loyalty redemption is one invoice at a time — use Record payment.'])->withInput();
        }
        $ref = $data['reference'] ?: 'Split '.now()->format('d M Y');
        $made = [];
        $total = 0;
        foreach ($data['alloc'] as $invId => $amt) {
            $amt = round((float) $amt, 2);
            if ($amt <= 0) {
                continue;
            }
            $inv = Invoice::where('id', $invId)->where('client_id', $data['client_id'])
                ->whereNotIn('status', ['draft', 'cancelled'])->first();
            if (! $inv || $amt > $inv->balance() + 0.009) {
                return back()->withErrors(['alloc' => 'Allocation for '.($inv->number ?? 'invoice').' exceeds its balance.'])->withInput();
            }
            $made[] = Payment::create([
                'number' => $this->nextNumber($s), 'public_token' => Str::random(32),
                'client_id' => $data['client_id'], 'invoice_id' => $inv->id, 'amount' => $amt,
                'method' => $data['method'], 'reference' => $ref, 'paid_on' => $data['paid_on'],
                'notes' => $data['notes'] ?? null,
            ]);
            $total += $amt;
        }
        if (empty($made)) {
            return back()->withErrors(['alloc' => 'Enter at least one amount to split.'])->withInput();
        }
        $earn = (float) setting('loyalty.earn_percent', 0);
        if ($earn > 0) {
            Client::where('id', $data['client_id'])->increment('loyalty_balance', round($total * $earn / 100, 2));
        }
        notify_roles(['admin', 'manager'], new PaymentReceived($made[0]));

        return redirect()->route('payments.index')->with('ok', 'Split '.money($total).' across '.count($made).' invoices ('.$ref.').');
    }
    /** Printable payment receipt (receipt number = payment number). */
    public function show(Payment $payment)
    {
        $payment->load(['invoice', 'client']);

        return view('payments.show', compact('payment'));
    }

    /** Email the receipt (+ public download link) to the client. */
    public function email(Payment $payment)
    {
        $payment->load(['invoice', 'client']);
        if (empty($payment->client->email)) {
            return back()->withErrors(['email' => 'Client has no email address.']);
        }
        if (! $payment->public_token) {
            $payment->update(['public_token' => Str::random(32)]);
        }
        $brand = app(Brand::class)->all();
        $data = [
            'company_name' => $brand['company_name'] ?? config('app.name'), 'client_name' => $payment->client->name,
            'receipt_number' => $payment->number ?? '—', 'amount' => money($payment->amount),
            'method' => ucfirst($payment->method), 'date' => d($payment->paid_on, 'j F Y'),
            'invoice_number' => $payment->invoice->number ?? 'Advance',
            'link' => route('payments.public', $payment->public_token),
        ];
        try {
            Mail::send('emails.template', ['body' => render_template('email.receipt_body', $data)], function ($m) use ($payment, $data) {
                $m->to($payment->client->email, $payment->client->name)
                    ->subject(render_template('email.receipt_subject', $data));
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => 'Could not send email: '.$e->getMessage().' — check Settings → Integrations (SMTP).']);
        }

        return back()->with('ok', 'Receipt emailed to '.$payment->client->email);
    }

    public function destroy(Payment $payment)
    {
        // Reversing a credit allocation frees the credit note balance back up.
        if ($payment->method === 'credit' && $payment->reference) {
            if ($cn = CreditNote::where('number', $payment->reference)->first()) {
                $cn->decrement('applied_amount', min((float) $payment->amount, (float) $cn->applied_amount));
                if ($cn->fresh()->remaining() > 0 && $cn->status === 'applied') {
                    $cn->update(['status' => 'open']);
                }
            }
        }
        $payment->delete();

        return back()->with('ok', 'Payment moved to Trash.');
    }

    /** (Re)generate the no-login public receipt link for sharing with the client. */
    public function publicLink(Payment $payment)
    {
        $payment->update(['public_token' => Str::random(32)]);

        return back()->with('ok', 'Public receipt link ready — share it with your client.');
    }

    /** Public receipt view + download for clients (token-guarded, no login). */
    public function publicView(string $token)
    {
        $payment = Payment::where('public_token', $token)->with(['invoice', 'client'])->firstOrFail();

        return view('payments.public', compact('payment'));
    }

    /** Create a Razorpay order server-side for checkout.js (per-install keys). */
    public function razorpayOrder(Request $r)
    {
        if (! config('razorpay.enabled')) {
            abort(400, 'Razorpay is not configured.');
        }
        $data = $r->validate(['invoice_id' => 'nullable|exists:invoices,id', 'client_id' => 'nullable|exists:clients,id', 'amount' => 'required|numeric|min:1']);
        $inv = ! empty($data['invoice_id']) ? Invoice::find($data['invoice_id']) : null;
        if ($inv) {
            if ($inv->status === 'cancelled' || ($inv->total > 0 && $inv->paid_amount >= $inv->total)) {
                abort(400, 'Invoice is already fully paid.');
            }
            $bal = $inv->balance();
            if (round((float) $data['amount'], 2) > $bal + 0.009) {
                abort(400, 'Amount exceeds the outstanding balance.');
            }
        }
        $api = new Api(config('razorpay.key_id'), config('razorpay.key_secret'));
        $order = $api->order->create([
            'amount' => (int) round($data['amount'] * 100),
            'currency' => setting('app.currency', 'INR'),
            'receipt' => 'inv-'.($data['invoice_id'] ?? 'adv').'-'.time(),
            'notes' => ['invoice_id' => $data['invoice_id'] ?? '', 'client_id' => $data['client_id'] ?? ($inv->client_id ?? '')],
        ]);

        return response()->json(['key' => config('razorpay.key_id'), 'order_id' => $order['id'], 'amount' => $data['amount']]);
    }

    /**
     * Verify a Razorpay checkout callback before recording the payment.
     * Returns an error string, or null when the payment is genuine.
     */
    protected function verifyRazorpay(array $data): ?string
    {
        if (! config('razorpay.enabled') || ! config('razorpay.key_secret')) {
            return 'Online payments are not configured on this installation.';
        }
        $payId = $data['razorpay_payment_id'] ?? '';
        $orderId = $data['razorpay_order_id'] ?? '';
        $sig = $data['razorpay_signature'] ?? '';
        if (! $payId || ! $orderId || ! $sig) {
            return 'Incomplete online payment — please retry the checkout.';
        }
        // Offline HMAC check: proves the callback came from Razorpay.
        $expected = hash_hmac('sha256', $orderId.'|'.$payId, config('razorpay.key_secret'));
        if (! hash_equals($expected, $sig)) {
            return 'Payment signature mismatch — not recorded.';
        }
        if (Payment::where('razorpay_payment_id', $payId)->exists()) {
            return 'This online payment was already recorded.';
        }
        // Online cross-check against Razorpay: amount + captured status.
        try {
            $api = new Api(config('razorpay.key_id'), config('razorpay.key_secret'));
            $fetched = $api->payment->fetch($payId);
            if (! in_array($fetched->status ?? '', ['captured', 'authorized'], true)) {
                return 'Payment is not captured yet (status: '.($fetched->status ?? 'unknown').').';
            }
            if ((int) ($fetched->amount ?? -1) !== (int) round($data['amount'] * 100)) {
                return 'Paid amount does not match the recorded amount.';
            }
        } catch (\Throwable $e) {
            // API unreachable (test keys / offline) — signature already proves origin.
            Log::warning('Razorpay cross-check skipped: '.$e->getMessage());
        }

        return null;
    }

    protected function nextNumber(SettingStore $s): string
    {
        return NextNumber::take($s->get('payments.prefix', 'PAY-'), 'payments');
    }

    /** Public checkout step 1 (no login): create a Razorpay order capped at the
     *  live balance. Token identifies the invoice; amount is never trusted. */
    public function publicOrder(Request $r, string $token)
    {
        if (! config('razorpay.enabled')) {
            abort(400, 'Online payments are not configured.');
        }
        $invoice = Invoice::where('public_token', $token)->firstOrFail();
        $data = $r->validate(['amount' => 'required|numeric|min:1']);
        if ($invoice->status === 'cancelled') {
            abort(400, 'This invoice is cancelled.');
        }
        $bal = $invoice->balance();
        if ($bal <= 0) {
            abort(400, 'Invoice is already fully paid.');
        }
        $amount = min(round((float) $data['amount'], 2), $bal);
        $api = new Api(config('razorpay.key_id'), config('razorpay.key_secret'));
        $order = $api->order->create([
            'amount' => (int) round($amount * 100),
            'currency' => setting('app.currency', 'INR'),
            'receipt' => 'pub-'.$invoice->id.'-'.time(),
            'notes' => ['invoice_id' => $invoice->id, 'client_id' => $invoice->client_id],
        ]);

        return response()->json(['key' => config('razorpay.key_id'), 'order_id' => $order['id'], 'amount' => $amount]);
    }

    /** Public checkout step 2 (no login): verify HMAC + record the payment. */
    public function publicVerify(Request $r, string $token)
    {
        $invoice = Invoice::with('client')->where('public_token', $token)->firstOrFail();
        $data = $r->validate([
            'amount' => 'required|numeric|min:1',
            'razorpay_payment_id' => 'required|string|max:100', 'razorpay_order_id' => 'required|string|max:100',
            'razorpay_signature' => 'required|string|max:255',
        ]);
        if ($invoice->status === 'cancelled') {
            return response()->json(['error' => 'This invoice is cancelled.'], 422);
        }
        $bal = $invoice->balance();
        if ($bal <= 0) {
            return response()->json(['error' => 'Invoice is already fully paid.'], 422);
        }
        $data['amount'] = min(round((float) $data['amount'], 2), $bal);
        if ($err = $this->verifyRazorpay($data)) {
            return response()->json(['error' => $err], 422);
        }
        $s = app(SettingStore::class);
        $payment = Payment::create([
            'number' => $this->nextNumber($s), 'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id,
            'amount' => $data['amount'], 'method' => 'razorpay', 'reference' => $data['razorpay_order_id'],
            'razorpay_payment_id' => $data['razorpay_payment_id'], 'razorpay_order_id' => $data['razorpay_order_id'],
            'paid_on' => now()->toDateString(), 'notes' => 'Paid online by client.',
            'public_token' => Str::random(32),
        ]);
        $left = $invoice->fresh()->balance();

        return response()->json([
            'ok' => true, 'receipt_url' => route('payments.public', $payment->public_token),
            'receipt_number' => $payment->number, 'balance' => max(0, $left),
        ]);
    }

    /** Razorpay webhook (per-install keys; no central server). */
    public function webhook(Request $r)
    {
        $secret = config('razorpay.webhook_secret');
        if ($secret) {
            $sig = $r->header('X-Razorpay-Signature', '');
            $expected = hash_hmac('sha256', $r->getContent(), $secret);
            if (! hash_equals($expected, $sig)) {
                abort(403, 'Bad signature');
            }
        }
        $event = $r->input('event', '');
        // Late capture / double-submit safety net: record captured payments we missed.
        if ($event === 'payment.captured' && is_array($entity = $r->input('payload.payment.entity'))) {
            $this->recordWebhookPayment($entity);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Idempotent webhook recorder: creates the payment once per
     * razorpay_payment_id, mapping invoice/client from order notes.
     */
    protected function recordWebhookPayment(array $entity): void
    {
        $payId = $entity['id'] ?? '';
        if (! $payId || Payment::where('razorpay_payment_id', $payId)->exists()) {
            return;
        }
        $notes = $entity['notes'] ?? [];
        if (! is_array($notes) && $orderId = ($entity['order_id'] ?? '')) {
            try {
                $api = new Api(config('razorpay.key_id'), config('razorpay.key_secret'));
                $notes = (array) ($api->order->fetch($orderId)->notes ?? []);
            } catch (\Throwable $e) {
                $notes = [];
            }
        }
        $inv = ! empty($notes['invoice_id']) ? Invoice::find($notes['invoice_id']) : null;
        $clientId = $notes['client_id'] ?? $inv->client_id ?? null;
        if (! $clientId) {
            return;
        }
        $amount = round(((int) ($entity['amount'] ?? 0)) / 100, 2);
        if ($amount <= 0) {
            return;
        }
        if ($inv) {
            if ($inv->status === 'cancelled') {
                return;
            }
            $bal = $inv->balance();
            if ($bal <= 0 || $amount > $bal + 0.009) {
                return;
            }
        }
        $s = app(SettingStore::class);
        Payment::create([
            'number' => $this->nextNumber($s),
            'invoice_id' => $inv?->id, 'client_id' => $clientId, 'amount' => $amount,
            'method' => 'razorpay', 'reference' => $entity['order_id'] ?? null,
            'razorpay_payment_id' => $payId, 'razorpay_order_id' => $entity['order_id'] ?? null,
            'paid_on' => now()->toDateString(),
            'notes' => 'Recorded via Razorpay webhook.',
        ]);
    }
}

