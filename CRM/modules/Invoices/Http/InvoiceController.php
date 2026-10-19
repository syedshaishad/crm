<?php

namespace Modules\Invoices\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Clients\Client;
use Modules\Core\Access\AssignsRecords;
use Modules\Core\Numbering\NextNumber;
use Modules\Invoices\Invoice;
use Modules\Invoices\InvoiceCallLog;
use Modules\Invoices\InvoiceMailer;
use Modules\Invoices\RecurringProfile;
use Modules\Invoices\ReminderSender;
use Modules\Payments\Payment;
use Modules\Projects\Project;
use Modules\Settings\SettingStore;

class InvoiceController extends Controller
{
    use AssignsRecords;

    public function __construct()
    {
        // Token-guarded public view stays open — everything else needs billing access.
        $this->middleware('perm:billing')->except(['publicView']);
    }
    public function index(Request $r)
    {
        $q = Invoice::with(['client', 'assignee'])->visibleTo(auth()->user())->orderByDesc('id');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $s)->orWhere('company', 'like', $s)));
        }
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }

        return view('invoices.index', ['invoices' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'status' => $r->status]);
    }

    protected function canView(Invoice $invoice): bool
    {
        return $invoice->isVisibleTo(auth()->user());
    }


    public function create(Request $r, SettingStore $s)
    {
        $prefix = $s->get('invoice.prefix', config('branding.invoice_prefix', 'INV-'));
        $client = $r->client_id ? Client::find($r->client_id) : null;

        return view('invoices.form', ['invoice' => new Invoice(['gst_rate' => $s->get('tax.default_gst_rate', config('gst.default_gst_rate', 18)), 'place_of_supply' => $client->state ?? $s->get('company.state', ''), 'currency' => $client->currency ?? 'INR']), 'clients' => Client::orderBy('name')->get(), 'projects' => Project::orderBy('name')->get(['id', 'name', 'client_id']), 'prefix' => $prefix, 'default_client' => $r->client_id, 'staff' => $this->staffOptions(), 'currencies' => setting_lines('invoice.currencies', ['INR', 'USD', 'EUR', 'GBP', 'AED'])]);
    }

    public function store(Request $r, SettingStore $s)
    {
        // Ghost rows from the picker carry no description — drop them before
        // validation so an untouched blank line never blocks the submit.
        $r->merge(['items' => collect($r->input('items', []))->filter(fn ($it) => trim((string) ($it['description'] ?? '')) !== '')->values()->all()]);
        $data = $r->validate([
            'client_id' => 'required|exists:clients,id', 'project_id' => 'nullable|exists:projects,id',
            'issue_date' => 'required|date', 'due_date' => 'nullable|date',
            'gst_rate' => 'required|numeric', 'notes' => 'nullable|string',
            'place_of_supply' => 'nullable|string|max:100', 'reverse_charge' => 'nullable|boolean',
            'discount' => 'nullable|numeric|min:0', 'eway_bill_no' => 'nullable|string|max:20',
            'currency' => 'nullable|string|size:3', 'exchange_rate' => 'nullable|numeric|min:0.000001',
            'tds_section' => 'nullable|string|max:20', 'tds_rate' => 'nullable|numeric|min:0|max:30',
            'tds_amount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1', 'items.*.description' => 'required|string',
            'items.*.hsn_code' => 'nullable|string|max:20', 'items.*.unit' => 'nullable|string|max:10',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:28',
            'items.*.qty' => 'required|numeric|min:0.01', 'items.*.rate' => 'required|numeric|min:0', 'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);
        $prefix = $s->get('invoice.prefix', 'INV-');
        $number = NextNumber::take($prefix, 'invoices');
        // Foreign currency: rates are typed in the invoice currency and booked
        // in INR at the snapshot rate — every report stays INR, always.
        $currency = strtoupper($data['currency'] ?? 'INR');
        $fx = $currency === 'INR' ? 1 : round((float) ($data['exchange_rate'] ?? 1), 6);
// The invoice, its lines and the TDS entry persist as one unit: a TDS that
        // cannot be booked must not leave a half-built draft behind for the user to
        // clean up — the form comes back with their input instead.
        [$inv, $tdsErr] = DB::transaction(function () use ($data, $number, $currency, $fx, $r) {
            $inv = Invoice::create([
                'number' => $number, 'client_id' => $data['client_id'], 'project_id' => $data['project_id'] ?? null,
                'issue_date' => $data['issue_date'], 'due_date' => $data['due_date'] ?? null,
                'status' => 'draft', 'gst_rate' => $data['gst_rate'], 'notes' => $data['notes'] ?? null,
                'place_of_supply' => $data['place_of_supply'] ?? null, 'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
                'discount' => round((float) ($data['discount'] ?? 0) * $fx, 2), 'public_token' => Str::random(32), 'eway_bill_no' => $data['eway_bill_no'] ?? null,
                'currency' => $currency, 'exchange_rate' => $fx,
                'created_by' => auth()->id(),
                'assigned_to' => $this->resolveAssignee($r, auth()->user(), auth()->id()),
            ]);
            foreach ($data['items'] as $it) {
                $rateInr = round((float) $it['rate'] * $fx, 2);
                $inv->items()->create(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'unit' => $it['unit'] ?? 'NOS', 'tax_rate' => $it['tax_rate'] ?? null, 'qty' => $it['qty'], 'rate' => $rateInr, 'discount_percent' => $it['discount_percent'] ?? 0, 'amount' => round($it['qty'] * $rateInr, 2)]);
            }
            $inv->recalc();

            return [$inv, $this->saveInvoiceTds($inv, $r)];
        });
        if ($tdsErr) {
            return back()->withInput()->withErrors(['tds' => $tdsErr]);
        }

        return redirect()->route('invoices.show', $inv)->with('ok', 'Invoice created.')->with('warn', credit_warn($inv->client));
    }

    /** One-click late fee: zero-GST line per Settings → Billing. Once per invoice. */

    public function applyLateFee(Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403, 'This invoice is not assigned to you.');
        }
        $fee = late_fee_amount($invoice);
        if ($fee <= 0) {
            return back()->withErrors(['late_fee' => 'Late fee does not apply here (check the rules, grace period, or a previous application).']);
        }
        $days = $invoice->due_date ? max(0, (int) now()->diffInDays($invoice->due_date, true)) : 0;
        $invoice->items()->create([
            'description' => 'Late fee — '.$days.' days overdue ('.$invoice->number.')',
            'hsn_code' => null, 'unit' => 'NOS', 'tax_rate' => 0,
            'qty' => 1, 'rate' => $fee, 'amount' => $fee,
        ]);
        $invoice->update(['late_fee_on' => now()->toDateString()]);
        $invoice->recalc();
        activity('late-fee', $invoice, 'Late fee '.money($fee));

        return back()->with('ok', 'Late fee of '.money($fee).' applied.');
    }

    /** Adjust an advance (unlinked payment) against this invoice's balance. */
    public function applyAdvance(Request $r, Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403, 'This invoice is not assigned to you.');
        }
        $data = $r->validate(['payment_id' => 'required|exists:payments,id']);
        $pay = Payment::where('id', $data['payment_id'])->whereNull('invoice_id')->firstOrFail();
        if ((int) $pay->client_id !== (int) $invoice->client_id) {
            return back()->withErrors(['advance' => 'That payment belongs to a different client.']);
        }
        $bal = $invoice->balance();
        if ($bal <= 0) {
            return back()->withErrors(['advance' => 'This invoice has no balance left.']);
        }
        if (round((float) $pay->amount, 2) > $bal + 0.009) {
            return back()->withErrors(['advance' => 'Advance '.money($pay->amount).' exceeds the '.money($bal).' balance — link it to a bigger invoice or record a smaller receipt.']);
        }
        $pay->update(['invoice_id' => $invoice->id]);
        Payment::syncInvoice($invoice->id);
        activity('advance-adjusted', $invoice, 'Advance '.$pay->number.' adjusted to '.$invoice->number);

        return back()->with('ok', 'Advance '.$pay->number.' ('.money($pay->amount).') adjusted — balance '.money($invoice->fresh()->balance()).'.');
    }

    /** Duplicate any invoice as a fresh draft (new number, new public link). */
    public function duplicate(Invoice $invoice, SettingStore $s)
    {
        if (! $this->canView($invoice)) {
            abort(403, 'This invoice is not assigned to you.');
        }
        $prefix = $s->get('invoice.prefix', 'INV-');
        $copy = Invoice::create([
            'number' => NextNumber::take($prefix, 'invoices'), 'client_id' => $invoice->client_id, 'project_id' => $invoice->project_id,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays((int) $s->get('invoice.due_days', 7))->toDateString(),
            'status' => 'draft', 'gst_rate' => $invoice->gst_rate, 'notes' => $invoice->notes,
            'place_of_supply' => $invoice->place_of_supply, 'reverse_charge' => (bool) $invoice->reverse_charge,
            'discount' => $invoice->discount, 'public_token' => Str::random(32),
            'created_by' => auth()->id(), 'assigned_to' => $invoice->assigned_to,
        ]);
        foreach ($invoice->items as $it) {
            $copy->items()->create(['description' => $it->description, 'hsn_code' => $it->hsn_code, 'unit' => $it->unit, 'tax_rate' => $it->tax_rate, 'qty' => $it->qty, 'rate' => $it->rate, 'amount' => $it->amount]);
        }
        $copy->recalc();

        return redirect()->route('invoices.edit', $copy)->with('ok', 'Cloned '.$invoice->number.' as draft '.$copy->number.' — review and send.');
    }

    /** Email a batch of invoices (skips cancelled + failed, reports counts). */
    public function bulkEmail(Request $r)
    {
        $data = $r->validate(['ids' => 'required|array|min:1|max:25', 'ids.*' => 'integer']);
        $sent = 0;
        $skipped = [];
        foreach (Invoice::visibleTo(auth()->user())->whereIn('id', $data['ids'])->with('client')->get() as $inv) {
            if ($inv->status === 'cancelled') {
                $skipped[] = $inv->number.' (cancelled)';
                continue;
            }
            $res = InvoiceMailer::send($inv);
            if (! $res['ok']) {
                $skipped[] = $inv->number.' ('.$res['message'].')';
                continue;
            }
            if ($inv->status === 'draft') {
                $inv->update(['status' => 'sent']);
            }
            $sent++;
        }

        return back()->with('ok', 'Emailed '.$sent.' invoice(s).'.($skipped ? ' Skipped: '.implode(', ', array_slice($skipped, 0, 5)).(count($skipped) > 5 ? '…' : '') : ''));
    }

    /** Log a collection call: outcome + notes, newest first on the invoice. */
    public function logCall(Request $r, Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403, 'This invoice is not assigned to you.');
        }
        $data = $r->validate([
            'outcome' => 'required|in:'.implode(',', array_keys(InvoiceCallLog::outcomes())),
            'notes' => 'nullable|string|max:500',
        ]);
        $invoice->callLogs()->create([
            'client_id' => $invoice->client_id, 'outcome' => $data['outcome'],
            'notes' => $data['notes'] ?? null, 'called_by' => auth()->id(),
        ]);

        return back()->with('ok', 'Call logged.');
    }

    public function show(Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403, 'This invoice is not assigned to you.');
        }
        $invoice->load(['items', 'client', 'payments', 'assignee', 'creator', 'callLogs.caller']);
        $reminders = DB::table('reminder_logs')->where('invoice_id', $invoice->id)->orderByDesc('id')->limit(5)->get();
        $tdsEntries = DB::table('tds_entries')->where('invoice_id', $invoice->id)->where('kind', 'receivable')->orderBy('deducted_on')->orderBy('id')->get();
        $advances = max(0, (float) $invoice->total - (float) $invoice->paid_amount) > 0
            ? Payment::where('client_id', $invoice->client_id)->whereNull('invoice_id')->orderByDesc('paid_on')->limit(10)->get()
            : collect();

        return view('invoices.show', compact('invoice', 'reminders', 'tdsEntries', 'advances'));
    }

    public function edit(Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403, 'This invoice is not assigned to you.');
        }
        $invoice->load(['items', 'client']);
        $tds = DB::table('tds_entries')->where('invoice_id', $invoice->id)->where('kind', 'receivable')->whereNull('payment_id')->first();

        return view('invoices.edit', ['invoice' => $invoice, 'tds' => $tds, 'clients' => Client::orderBy('name')->get(), 'projects' => Project::where('client_id', $invoice->client_id)->orderBy('name')->get(), 'staff' => $this->staffOptions(), 'currencies' => setting_lines('invoice.currencies', ['INR', 'USD', 'EUR', 'GBP', 'AED'])]);
    }

    public function update(Request $r, Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403, 'This invoice is not assigned to you.');
        }
        $r->merge(['items' => collect($r->input('items', []))->filter(fn ($it) => trim((string) ($it['description'] ?? '')) !== '')->values()->all()]);
        $data = $r->validate([
            'client_id' => 'required|exists:clients,id', 'project_id' => 'nullable|exists:projects,id',
            'issue_date' => 'required|date', 'due_date' => 'nullable|date',
            'gst_rate' => 'required|numeric', 'status' => 'required|in:draft,sent,partial,paid,overdue,cancelled',
            'notes' => 'nullable|string',
            'place_of_supply' => 'nullable|string|max:100', 'reverse_charge' => 'nullable|boolean',
            'discount' => 'nullable|numeric|min:0', 'eway_bill_no' => 'nullable|string|max:20',
            'currency' => 'nullable|string|size:3', 'exchange_rate' => 'nullable|numeric|min:0.000001',
            'tds_section' => 'nullable|string|max:20', 'tds_rate' => 'nullable|numeric|min:0|max:30',
            'tds_amount' => 'nullable|numeric|min:0',
            'items' => 'sometimes|array', 'items.*.description' => 'required|string',
            'items.*.hsn_code' => 'nullable|string|max:20', 'items.*.unit' => 'nullable|string|max:10',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:28',
            'items.*.qty' => 'required|numeric|min:0.01', 'items.*.rate' => 'required|numeric|min:0', 'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items_existing' => 'sometimes|array',
            'items_existing.*.description' => 'required|string',
            'items_existing.*.hsn_code' => 'nullable|string|max:20', 'items_existing.*.unit' => 'nullable|string|max:10',
            'items_existing.*.tax_rate' => 'nullable|numeric|min:0|max:28',
            'items_existing.*.qty' => 'required|numeric|min:0.01', 'items_existing.*.rate' => 'required|numeric|min:0', 'items_existing.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'remove_items' => 'sometimes|array', 'remove_items.*' => 'integer',
        ]);
        $currency = strtoupper($data['currency'] ?? $invoice->currency ?? 'INR');
        $fx = $currency === 'INR' ? 1 : round((float) ($data['exchange_rate'] ?? $invoice->exchange_rate ?? 1), 6);
        $invoice->update([
            'client_id' => $data['client_id'], 'project_id' => $data['project_id'] ?? null,
            'issue_date' => $data['issue_date'], 'due_date' => $data['due_date'] ?? null,
            'gst_rate' => $data['gst_rate'], 'status' => $data['status'], 'notes' => $data['notes'] ?? null,
            'place_of_supply' => $data['place_of_supply'] ?? null, 'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
            'discount' => round((float) ($data['discount'] ?? 0) * $fx, 2), 'eway_bill_no' => $data['eway_bill_no'] ?? null,
            'currency' => $currency, 'exchange_rate' => $fx,
            'assigned_to' => auth()->user()->canManageInvoices() ? $this->resolveAssignee($r, auth()->user(), $invoice->assigned_to) : $invoice->assigned_to,
        ]);
        $remove = collect($data['remove_items'] ?? [])->map(fn ($v) => (int) $v);
        foreach ($data['items_existing'] ?? [] as $id => $it) {
            $line = $invoice->items()->find((int) $id);
            if (! $line) {
                continue;
            }
            if ($remove->contains($line->id)) {
                $line->delete();

                continue;
            }
            $rateInr = round((float) $it['rate'] * $fx, 2);
            $line->update(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'unit' => $it['unit'] ?? 'NOS', 'tax_rate' => $it['tax_rate'] ?? null, 'qty' => $it['qty'], 'rate' => $rateInr, 'discount_percent' => $it['discount_percent'] ?? 0, 'amount' => round($it['qty'] * $rateInr, 2)]);
        }
        if ($invoice->items()->whereIn('id', $remove)->exists()) {
            $invoice->items()->whereIn('id', $remove)->delete();
        }
        if (! empty($data['items'])) {
            foreach ($data['items'] as $it) {
                $rateInr = round((float) $it['rate'] * $fx, 2);
                $invoice->items()->create(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'unit' => $it['unit'] ?? 'NOS', 'tax_rate' => $it['tax_rate'] ?? null, 'qty' => $it['qty'], 'rate' => $rateInr, 'discount_percent' => $it['discount_percent'] ?? 0, 'amount' => round($it['qty'] * $rateInr, 2)]);
            }
        }
        // Re-sync totals and roll status to match what is actually paid
        // (cancelled/draft stay as set above).
        Payment::syncInvoice($invoice->id);
        if ($tdsErr = $this->saveInvoiceTds($invoice->fresh(), $r)) {
            return back()->withErrors(['tds' => $tdsErr])->withInput();
        }
        $invoice->fresh()->syncStock();

        return redirect()->route('invoices.show', $invoice)->with('ok', 'Invoice updated.')->with('warn', credit_warn($invoice->client));
    }

    /**
     * TDS claimed up-front on the invoice itself (no payment yet). Only rows
     * without a payment are managed here — payment-time TDS is untouched.
     * Returns an error string when the amount exceeds the invoice total.
     */
    protected function saveInvoiceTds(Invoice $invoice, Request $r): ?string
    {
        DB::table('tds_entries')->where('invoice_id', $invoice->id)->where('kind', 'receivable')->whereNull('payment_id')->delete();
        $amt = round((float) $r->input('tds_amount', 0), 2);
        if (setting('tax.tds_enabled', '0') !== '1' || $amt <= 0 || ! $r->input('tds_section')) {
            $invoice->recalc();

            return null;
        }
        $total = round((float) $invoice->fresh()->total, 2);
        if ($amt > $total + 0.009) {
            return 'TDS of '.money($amt).' exceeds the invoice total of '.money($total).' — adjust it below.';
        }
        $sections = tds_sections();
        $code = strtoupper($r->input('tds_section'));
        DB::table('tds_entries')->insert([
            'kind' => 'receivable', 'invoice_id' => $invoice->id, 'payment_id' => null,
            'client_id' => $invoice->client_id, 'section' => $code,
            'rate' => (float) ($r->input('tds_rate', $sections[$code] ?? 0)), 'base_amount' => $total,
            'amount' => $amt, 'deducted_on' => $invoice->issue_date->toDateString(), 'fy' => fy($invoice->issue_date),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoice->recalc();

        return null;
    }

    public function markSent(Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403);
        }
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return back()->withErrors(['status' => 'A '.$invoice->status.' invoice cannot be marked sent.']);
        }
        $invoice->update(['status' => 'sent']);
        $invoice->fresh()->syncStock();

        return back()->with('ok', 'Invoice marked sent.');
    }

    /**
     * Record an extra TDS claim straight from the invoice page (appends —
     * the create/edit form manages the up-front claim). Settles via recalc.
     */
    public function claimTds(Request $r, Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403);
        }
        if (setting('tax.tds_enabled', '0') !== '1') {
            return back()->withErrors(['tds' => 'Enable TDS under Settings → Tax first.']);
        }
        if (! in_array($invoice->status, ['sent', 'partial', 'overdue'], true)) {
            return back()->withErrors(['tds' => 'TDS can only be claimed on filed, unpaid invoices.']);
        }
        $data = $r->validate([
            'tds_section' => 'required|string|max:20', 'tds_rate' => 'nullable|numeric|min:0|max:30',
            'tds_amount' => 'required|numeric|min:0.01',
        ]);
        $balance = $invoice->balance();
        $amt = round((float) $data['tds_amount'], 2);
        if ($amt > $balance + 0.009) {
            return back()->withErrors(['tds_amount' => 'TDS exceeds the outstanding balance of '.money($balance).'.']);
        }
        $sections = tds_sections();
        $code = strtoupper($data['tds_section']);
        DB::table('tds_entries')->insert([
            'kind' => 'receivable', 'invoice_id' => $invoice->id, 'payment_id' => null,
            'client_id' => $invoice->client_id, 'section' => $code,
            'rate' => (float) ($data['tds_rate'] ?? $sections[$code] ?? 0), 'base_amount' => $balance,
            'amount' => $amt, 'deducted_on' => now()->toDateString(), 'fy' => fy(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Payment::syncInvoice($invoice->id);

        return back()->with('ok', 'TDS of '.money($amt).' claimed on '.$invoice->number.'.');
    }

    public function sendEmail(Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403);
        }
        $res = InvoiceMailer::send($invoice);
        if (! $res['ok']) {
            return back()->withErrors(['email' => $res['message']]);
        }
        $invoice->update(['status' => $invoice->status === 'draft' ? 'sent' : $invoice->status]);
        $invoice->fresh()->syncStock();

        return back()->with('ok', 'Invoice emailed to '.$invoice->client->email);
    }

    /** Manual WhatsApp nudge — same throttle discipline as email (1 day). */
    public function whatsapp(Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403);
        }
        if (! in_array($invoice->status, ['sent', 'partial', 'overdue'], true)) {
            return back()->withErrors(['reminder' => 'Only filed, unpaid invoices can be reminded.']);
        }
        if ($invoice->last_whatsapp_at && $invoice->last_whatsapp_at->gt(now()->subDay())) {
            return back()->withErrors(['reminder' => 'Already sent on WhatsApp '.$invoice->last_whatsapp_at->diffForHumans().'. Limited to one a day.']);
        }
        $res = \Modules\Core\WhatsApp\WhatsAppSender::sendReminder($invoice, 'manual');
        if (! $res['ok']) {
            return back()->withErrors(['reminder' => $res['message']]);
        }

        return back()->with('ok', $res['message']);
    }

    /** Manual overdue nudge — same sender as the nightly job, throttled to once a day. */
    public function remind(Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403);
        }
        if (! in_array($invoice->status, ['sent', 'partial', 'overdue'], true)) {
            return back()->withErrors(['reminder' => 'Only filed, unpaid invoices can be reminded.']);
        }
        if ($invoice->last_nudged_at && $invoice->last_nudged_at->gt(now()->subDay())) {
            return back()->withErrors(['reminder' => 'Already nudged '.$invoice->last_nudged_at->diffForHumans().'. Manual reminders are limited to one a day (auto runs every 3 days).']);
        }
        $res = ReminderSender::send($invoice, 'manual');
        if (! $res['ok']) {
            return back()->withErrors(['reminder' => $res['message']]);
        }

        return back()->with('ok', $res['message']);
    }

    /** Admin audit trail of every reminder attempt. */
    public function reminders()
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Administrators only.');
        }
        $logs = DB::table('reminder_logs')->leftJoin('invoices', 'invoices.id', '=', 'reminder_logs.invoice_id')
            ->leftJoin('clients', 'clients.id', '=', 'reminder_logs.client_id')
            ->orderByDesc('reminder_logs.id')->limit(100)
            ->get(['reminder_logs.*', 'invoices.number as invoice', 'clients.name as client']);
        $followups = DB::table('proposal_followup_logs')->leftJoin('proposals', 'proposals.id', '=', 'proposal_followup_logs.proposal_id')
            ->leftJoin('clients', 'clients.id', '=', 'proposal_followup_logs.client_id')
            ->orderByDesc('proposal_followup_logs.id')->limit(100)
            ->get(['proposal_followup_logs.*', 'proposals.number as proposal', 'clients.name as client']);
        $whatsapps = DB::table('whatsapp_logs')->leftJoin('invoices', 'invoices.id', '=', 'whatsapp_logs.invoice_id')
            ->leftJoin('clients', 'clients.id', '=', 'whatsapp_logs.client_id')
            ->orderByDesc('whatsapp_logs.id')->limit(100)
            ->get(['whatsapp_logs.*', 'invoices.number as invoice', 'clients.name as client']);

        return view('reminders.index', compact('logs', 'followups', 'whatsapps'));
    }

    /** (Re)generate the no-login public link for sharing with the client. */
    public function publicLink(Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403);
        }
        $invoice->update(['public_token' => Str::random(32)]);

        return back()->with('ok', 'Public link ready — share it with your client.');
    }

    /** Public invoice view + download for clients (token-guarded, no login). */
    public function publicView(string $token)
    {
        $invoice = Invoice::where('public_token', $token)->with(['items', 'client', 'payments'])->firstOrFail();

        return view('invoices.public', compact('invoice'));
    }

    public function destroy(Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403);
        }
        if ($invoice->payments()->exists()) {
            return back()->withErrors(['invoice' => 'This invoice has recorded payments. Delete its payments first, or mark it cancelled instead.']);
        }
        $invoice->items()->delete();
        $invoice->delete();

        return redirect()->route('invoices.index')->with('ok', 'Invoice '.$invoice->number.' moved to Trash.');
    }

    /** Write off the remaining balance (manager+ only): closes the invoice without cash. */
    public function writeoff(Request $r, Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403);
        }
        if (! $r->user()->canManageInvoices()) {
            abort(403, 'Only managers and admins can write off balances.');
        }
        if ($invoice->status === 'cancelled') {
            return back()->withErrors(['invoice' => 'Cancelled invoices stay untouched.']);
        }
        $bal = round((float) $invoice->total - (float) $invoice->paid_amount - (float) ($invoice->writeoff_amount ?? 0), 2);
        if ($bal <= 0) {
            return back()->withErrors(['invoice' => 'Nothing to write off — this invoice is settled.']);
        }
        $data = $r->validate(['writeoff_note' => 'nullable|string|max:255']);
        $invoice->update(['writeoff_amount' => round((float) ($invoice->writeoff_amount ?? 0) + $bal, 2)]);
        Payment::syncInvoice($invoice->id);
        activity('invoice-writeoff', $invoice, 'Invoice '.$invoice->number.': wrote off '.money($bal).($data['writeoff_note'] ?? '' ? ' ('.$data['writeoff_note'].')' : ''));

        return back()->with('ok', 'Wrote off '.money($bal).'. The invoice is closed.');
    }

    /** Recurring schedules: auto-generate draft invoices on a cadence. */
    public function recurringIndex()
    {
        return view('invoices.recurring', ['profiles' => RecurringProfile::with(['client', 'project'])->orderByDesc('id')->paginate(perPage())]);
    }

    /** Clone an invoice into a recurring schedule (first run date + cadence). */
    public function recurringStore(Request $r, Invoice $invoice)
    {
        if (! $this->canView($invoice)) {
            abort(403);
        }
        $data = $r->validate([
            'frequency' => 'required|in:weekly,monthly,quarterly,yearly',
            'next_run_on' => 'required|date|after_or_equal:today',
            'auto_send' => 'nullable|boolean',
            'trial_days' => 'nullable|integer|min:0|max:90',
            'prorate_first' => 'nullable|boolean',
            'billing_day' => 'nullable|integer|min:1|max:28',
        ]);
        $invoice->load('items');
        RecurringProfile::create([
            'client_id' => $invoice->client_id, 'project_id' => $invoice->project_id,
            'frequency' => $data['frequency'], 'next_run_on' => $data['next_run_on'],
            'auto_send' => (bool) ($data['auto_send'] ?? false),
            'status' => 'active', 'is_active' => true,
            'trial_ends_on' => ! empty($data['trial_days']) ? now()->addDays((int) $data['trial_days'])->toDateString() : null,
            'prorate_first' => (bool) ($data['prorate_first'] ?? false),
            'billing_day' => $data['frequency'] === 'monthly' ? ($data['billing_day'] ?? null) : null,
            'currency' => $invoice->currency ?? 'INR', 'exchange_rate' => $invoice->exchange_rate ?? 1,
            'gst_rate' => $invoice->gst_rate, 'discount' => $invoice->discount,
            'place_of_supply' => $invoice->place_of_supply, 'notes' => $invoice->notes,
            'items' => $invoice->items->map(fn ($it) => [
                'description' => $it->description, 'hsn_code' => $it->hsn_code, 'qty' => $it->qty,
                'unit' => $it->unit, 'rate' => $it->rate, 'tax_rate' => $it->tax_rate,
                'discount_percent' => $it->discount_percent ?? 0,
            ])->all(),
            'created_by' => auth()->id(),
        ]);

        return back()->with('ok', 'Recurring schedule created — first invoice on '.d($data['next_run_on'], 'j M Y').($data['auto_send'] ?? false ? ', auto-emailed.' : '.'));
    }

    public function recurringToggle(RecurringProfile $profile)
    {
        $next = $profile->status === 'active' ? 'paused' : 'active';
        if ($profile->status === 'cancelled') {
            return back()->withErrors(['schedule' => 'Cancelled subscriptions stay closed — create a fresh one.']);
        }
        $profile->update(['status' => $next, 'is_active' => $next === 'active']);

        return back()->with('ok', $next === 'active' ? 'Subscription resumed.' : 'Subscription paused.');
    }

    /** End a subscription for good (generated invoices are untouched). */
    public function recurringCancel(RecurringProfile $profile)
    {
        $profile->update(['status' => 'cancelled', 'is_active' => false]);

        return back()->with('ok', 'Subscription cancelled — no more invoices will generate.');
    }

    public function recurringAutoSend(RecurringProfile $profile)
    {
        $profile->update(['auto_send' => ! $profile->auto_send]);

        return back()->with('ok', $profile->auto_send ? 'Each run will now email the client.' : 'Runs stay as drafts — nothing sends itself.');
    }

    public function recurringDestroy(RecurringProfile $profile)
    {
        $profile->delete();

        return back()->with('ok', 'Recurring schedule deleted.');
    }
}

