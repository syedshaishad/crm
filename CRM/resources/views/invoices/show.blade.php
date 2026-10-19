@extends('layouts.app')
@section('title','Invoice '.$invoice->number)
@section('content')
@php
  $invLogo = setting('invoice.logo_path') ?: ($brand['logo_path'] ?? '');
  $invDark = setting('invoice.logo_path') ? (setting('invoice.logo_dark_path') ?: ($brand['logo_dark_path'] ?? '')) : ($brand['logo_dark_path'] ?? '');
  $balance = $invoice->balance();
  $isPaid = $balance <= 0 && $invoice->total > 0;
  $isOver = $invoice->status === 'overdue' && $balance > 0;
  $companyState = setting('company.state', '');
  $buyerState = $invoice->client->state ?? '';
  $intra = $invoice->isIntraState();
  $halfRate = $invoice->gst_rate / 2;
@endphp
@if($isPaid)
  <div class="due-banner paid">Paid in full: {{ money($invoice->paid_amount) }} received.@if(($invoice->writeoff_amount ?? 0) > 0) {{ money($invoice->writeoff_amount) }} written off.@endif Thank you.</div>
@elseif($isOver)
  <div class="due-banner over">Overdue: {{ money($balance) }} was due {{ d($invoice->due_date, 'j F Y') }}.</div>
@elseif($balance > 0)
  @if($invoice->due_date)
  <div class="due-banner due">{{ money($balance) }} due by {{ d($invoice->due_date, 'j F Y') }}.</div>
  @else
  <div class="due-banner due">{{ money($balance) }} due.</div>
  @endif
@endif

<div class="inv-hero noprint">
  <div><div class="inv-num">{{ $invoice->number }}</div>
    <div class="inv-chips">
      <span class="badge {{ $invoice->status==='paid' ? 'b-green' : ($invoice->status==='overdue' ? 'b-red' : ($invoice->status==='cancelled' ? 'b-grey' : 'b-blue')) }}">{{ ucfirst($invoice->status) }}</span>
      <span class="chip">Issued {{ d($invoice->issue_date, 'j M Y') }}</span>
      @if($invoice->due_date)<span class="chip {{ $isOver ? 'overdue' : '' }}">Due {{ d($invoice->due_date, 'j M Y') }}</span>@endif
      @if($invoice->reverse_charge)<span class="chip overdue">Reverse charge</span>@endif
      @if($invoice->creator)<span class="chip">Prepared by {{ $invoice->creator->name }}</span>@endif
      @if($invoice->assignee)<span class="chip">Assigned to {{ $invoice->assignee->name }}</span>@endif
      @if($invoice->isForeign())<span class="chip">Billed in {{ $invoice->currency }} @ {{ rtrim(rtrim($invoice->exchange_rate,'0'),'.') }} ≈ {{ money($invoice->foreignTotal(), $invoice->currency) }}</span>@endif
    </div></div>
  <div class="btnrow inv-actions">
    <button class="btn sm" onclick="window.print()">Print / PDF</button>
    @if($balance > 0 && $invoice->status !== 'cancelled')<a class="btn sec sm" href="{{ route('payments.create',['invoice_id'=>$invoice->id,'client_id'=>$invoice->client_id]) }}">Record payment</a>@endif
    <form method="POST" action="{{ route('invoices.email',$invoice) }}" data-ajax-post data-refresh="page" style="display:inline">@csrf<button class="btn sec sm">Email to client</button></form>
    <div class="has-drop">
      <button type="button" class="btn sec sm" data-drop-toggle aria-label="More actions">•••</button>
      <div class="drop">
        @if(in_array($invoice->status,['sent','partial','overdue'],true) && $invoice->paid_amount < $invoice->total)<form method="POST" action="{{ route('invoices.remind',$invoice) }}" data-ajax-post data-refresh="page">@csrf<button class="menu-item" type="submit">Send reminder</button></form><form method="POST" action="{{ route('invoices.whatsapp',$invoice) }}" data-ajax-post data-refresh="page">@csrf<button class="menu-item" type="submit">WhatsApp</button></form>@endif
        @if(late_fee_amount($invoice) > 0)<form method="POST" action="{{ route('invoices.latefee',$invoice) }}" data-confirm="Apply a late fee of {{ money(late_fee_amount($invoice)) }} to {{ $invoice->number }}? This adds a zero-GST line, once only.">@csrf<button class="menu-item" type="submit">Apply late fee ({{ money(late_fee_amount($invoice)) }})</button></form>@endif
        @if($invoice->late_fee_on)<span class="menu-item" style="cursor:default">Late fee applied {{ d($invoice->late_fee_on,'j M Y') }}</span>@endif
        <form method="POST" action="{{ route('invoices.duplicate',$invoice) }}" data-confirm="Clone {{ $invoice->number }} as a new draft?">@csrf<button class="menu-item" type="submit">Clone as draft</button></form>
        <a class="menu-item" href="{{ route('credits.create',['invoice_id'=>$invoice->id]) }}">Credit note</a>
        <a class="menu-item" href="{{ route('challans.create',['invoice_id'=>$invoice->id]) }}">Challan</a>
        <form method="POST" action="{{ route('invoices.sent',$invoice) }}" data-ajax-post data-refresh="page">@csrf<button class="menu-item" type="submit">Mark sent</button></form>
        <a class="menu-item" href="{{ route('invoices.edit',$invoice) }}">Edit</a>
        <button class="menu-item" type="button" data-modal-open="recurring-modal">Make recurring</button>
        @if($balance > 0 && $invoice->status !== 'cancelled' && auth()->user()->canManageInvoices())<form method="POST" action="{{ route('invoices.writeoff',$invoice) }}" data-confirm="Write off {{ money($balance) }} on {{ $invoice->number }}? The invoice closes without cash. Only for small uncollectable balances.">@csrf<input type="hidden" name="writeoff_note" value=""><button class="menu-item danger" type="submit">Write off {{ money($balance) }}</button></form>@endif
        <form method="POST" action="{{ route('invoices.destroy',$invoice) }}" data-confirm="Delete invoice {{ $invoice->number }}? This cannot be undone.">@csrf @method('DELETE')<button class="menu-item danger" type="submit">Delete</button></form>
      </div>
    </div>
  </div>
</div>

@if($invoice->public_token)
<div class="card noprint"><div class="card-head"><h3>Client public link</h3><span class="btnrow" style="gap:8px"><button class="btn sec sm" onclick="navigator.clipboard.writeText('{{ route('invoices.public', $invoice->public_token) }}');this.textContent='Copied!';setTimeout(()=>this.textContent='Copy link',1500)">Copy link</button><form method="POST" action="{{ route('invoices.link',$invoice) }}" data-ajax-post data-refresh="page" style="display:inline" data-confirm="Generate a new link? The old one stops working.">@csrf<button class="btn sec sm">New link</button></form></span></div>
<p class="hint" style="margin:0">No login needed — the client can view and download (PDF) this invoice.</p></div>
@else
<div class="card noprint"><div class="card-head"><h3>Client public link</h3><form method="POST" action="{{ route('invoices.link',$invoice) }}" style="display:inline">@csrf<button class="btn sec sm">Create link</button></form></div></div>
@endif

@php
$smsText = 'Dear '.($invoice->client->company ?: ($invoice->client->name ?? 'Customer')).', invoice '.$invoice->number.' of Rs. '.number_format(max(0,$invoice->total-$invoice->paid_amount),2).' from '.($brand['company_name'] ?? '').' is pending.'
  .($invoice->public_token ? ' View & pay: '.route('invoices.public',$invoice->public_token) : '')
  .' Please ignore if already paid.';
@endphp
@if($invoice->public_token && in_array($invoice->status,['sent','partial','overdue'],true) && $invoice->paid_amount < $invoice->total)
<div class="card noprint"><div class="card-head"><h3>Collect via SMS</h3><button class="btn sec sm" data-sms="{{ $smsText }}" onclick="navigator.clipboard.writeText(this.dataset.sms);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy SMS text',1500)">Copy SMS text</button></div>
<p class="hint" style="margin:0;white-space:pre-line">{{ $smsText }}</p></div>
@endif

<div class="card noprint"><div class="card-head"><h3>Reminders</h3>@if($invoice->last_nudged_at)<span class="hint">last nudged {{ $invoice->last_nudged_at->diffForHumans() }}</span>@endif</div>
@if($reminders->isNotEmpty())
<div class="tbl-scroll"><table class="tbl"><tr><th>Sent</th><th>Channel</th><th>To</th><th>By</th><th>Status</th></tr>
@foreach($reminders as $g)<tr><td class="nowrap">{{ d($g->created_at,'j M Y, h:i A') }}</td><td>{{ ucfirst($g->channel) }}</td><td class="nowrap">{{ $g->recipient }}</td><td>{{ $g->source === 'auto' ? 'Auto (nightly)' : 'Manual' }}</td><td>@if($g->status==='sent')<span class="badge b-green">sent</span>@else<span class="badge b-red" title="{{ $g->error }}">failed</span>@endif</td></tr>@endforeach</table></div>
@else
<p class="hint" style="margin:0">No reminders sent yet — auto nudges run nightly for overdue invoices, max once every 3 days.</p>
@endif
</div>

<div class="card">
@include('invoices.doc-'.invoice_theme(), ['bankAlways' => true])
</div>

<div class="card"><div class="card-head"><h3>Payments ({{ $invoice->payments->count() }})</h3>@if($invoice->paid_amount < $invoice->total && $invoice->status !== 'cancelled')<a class="btn sm sec noprint" href="{{ route('payments.create',['invoice_id'=>$invoice->id,'client_id'=>$invoice->client_id]) }}">+ Record</a>@endif</div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Receipt no</th><th>Date</th><th style="text-align:right">Amount</th><th>Method</th><th class="noprint"></th></tr>
@forelse($invoice->payments as $p)<tr><td class="nowrap"><a href="{{ route('payments.show',$p) }}">{{ $p->number ?? '—' }}</a></td><td class="nowrap">{{ d($p->paid_on) }}</td><td class="nowrap" style="text-align:right;font-variant-numeric:tabular-nums">{{ money($p->amount) }}</td><td><span class="badge b-grey">{{ $p->method }}</span></td>
<td class="noprint"><div class="row-actions"><a class="icon-btn" href="{{ route('payments.show',$p) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>Receipt</a><form method="POST" action="{{ route('payments.destroy',$p) }}" style="display:inline" data-confirm="Delete this payment of {{ money($p->amount) }}? The invoice balance will update.">@csrf @method('DELETE')<button class="icon-btn danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>Delete</button></form></div></td></tr>
@empty<tr><td colspan="5"><div class="empty">No payments recorded yet.</div></td></tr>@endforelse</table></div></div>
@if(($advances ?? collect())->isNotEmpty())
<div class="card noprint"><div class="card-head"><h3>Adjust advance</h3><span class="hint">unlinked receipts from this client</span></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Receipt</th><th>Date</th><th style="text-align:right">Amount</th><th></th></tr>
@foreach($advances as $a)<tr><td class="nowrap"><a href="{{ route('payments.show',$a) }}">{{ $a->number ?? '—' }}</a></td><td class="nowrap">{{ d($a->paid_on) }}</td><td class="nowrap" style="text-align:right">{{ money($a->amount) }}</td><td style="text-align:right"><form method="POST" action="{{ route('invoices.advance',$invoice) }}" style="display:inline" data-confirm="Adjust {{ $a->number }} ({{ money($a->amount) }}) against {{ $invoice->number }}?">@csrf<input type="hidden" name="payment_id" value="{{ $a->id }}"><button class="btn sm sec">Adjust</button></form></td></tr>@endforeach</table></div></div>
@endif
@if($tdsEntries->isNotEmpty())
<div class="card"><div class="card-head"><h3>TDS claimed</h3><span class="hint">counts toward paid · 26AS</span></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Deducted on</th><th>Section</th><th style="text-align:right">Rate</th><th style="text-align:right">Base</th><th style="text-align:right">TDS</th></tr>
@foreach($tdsEntries as $t)<tr><td class="nowrap">{{ d($t->deducted_on) }}</td><td class="nowrap">{{ $t->section }}</td><td class="nowrap" style="text-align:right">{{ $t->rate }}%</td><td class="nowrap" style="text-align:right">{{ money($t->base_amount) }}</td><td class="nowrap" style="text-align:right"><b>{{ money($t->amount) }}</b></td></tr>@endforeach</table></div></div>
@endif
@if(setting('tax.tds_enabled','0')==='1' && in_array($invoice->status,['sent','partial','overdue'],true) && $invoice->paid_amount < $invoice->total)
<div class="card noprint"><div class="card-head"><h3>Claim TDS</h3><span class="hint">balance {{ money(max(0,$invoice->total-$invoice->paid_amount)) }}</span></div>
<form method="POST" action="{{ route('invoices.tds.claim',$invoice) }}" data-ajax-post data-refresh="page" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">@csrf
<div><label class="f">Section</label><select class="in" name="tds_section" style="margin:0" required><option value="">Select…</option>@foreach(tds_sections() as $code => $rate)<option value="{{ $code }}">{{ $code }} · {{ $rate }}%</option>@endforeach</select></div>
<div><label class="f">TDS amount (₹)</label><input class="in" type="number" step="0.01" min="0.01" name="tds_amount" style="margin:0" required placeholder="0.00"></div>
<div><button class="btn sm">Claim TDS</button></div>
</form></div>
@endif
<div class="modal" id="recurring-modal" role="dialog" aria-label="Make recurring">
  <div class="modal-bg" data-modal-close></div>
  <div class="modal-card">
    <h3>Repeat {{ $invoice->number }}</h3>
    <p class="hint" style="margin:0 0 12px">Auto-creates a draft copy on each run day. Nothing sends itself.</p>
    <form method="POST" action="{{ route('invoices.recurring.store',$invoice) }}">@csrf
    <div class="form-grid">
    <div><label class="f">Every</label><select class="in" name="frequency" style="margin-bottom:0">@foreach(\Modules\Invoices\RecurringProfile::frequencies() as $fv=>$fl)<option value="{{ $fv }}">{{ $fl }}</option>@endforeach</select></div>
    <div><label class="f">First run</label><input class="in" type="date" name="next_run_on" value="{{ date('Y-m-d', strtotime('+1 month')) }}" style="margin-bottom:0" required></div>
    <div><label class="f">Free trial (days)</label><input class="in" type="number" min="0" max="90" name="trial_days" value="0" style="margin-bottom:0"></div>
    <div><label class="f">Bill monthly on day</label><input class="in" type="number" min="1" max="28" name="billing_day" placeholder="e.g. 5" style="margin-bottom:0"></div>
    </div>
    <label class="hint" style="display:flex;gap:8px;align-items:center;margin-top:12px"><input type="checkbox" name="prorate_first" value="1" style="width:auto;margin:0"> Prorate the first bill (charge only the remaining days)</label>
    <label class="hint" style="display:flex;gap:8px;align-items:center;margin-top:12px"><input type="checkbox" name="auto_send" value="1" style="width:auto;margin:0"> Email each run to the client automatically</label>
    <div class="form-actions" style="margin-top:16px"><button class="btn" type="submit">Create schedule</button><button class="btn sec" data-modal-close type="button">Cancel</button></div></form>
  </div>
</div>
@endsection
