@extends('layouts.app')
@section('title','Receipt '.($payment->number ?? ''))
@section('content')
@php
  $invLogo = setting('invoice.logo_path') ?: ($brand['logo_path'] ?? '');
  $invDark = setting('invoice.logo_path') ? (setting('invoice.logo_dark_path') ?: ($brand['logo_dark_path'] ?? '')) : ($brand['logo_dark_path'] ?? '');
@endphp
<div class="inv-hero noprint">
  <div><div class="inv-num">{{ $payment->number ?? '—' }}</div>
    <div class="inv-chips">
      <span class="badge b-green">{{ ucfirst($payment->method) }}</span>
      <span class="chip">Received {{ d($payment->paid_on, 'j M Y') }}</span>
      @if($payment->reference)<span class="chip">Ref: {{ $payment->reference }}</span>@endif
    </div></div>
  <div class="btnrow">
    <button class="btn" onclick="window.print()">Print / PDF</button>
    <form method="POST" action="{{ route('payments.email',$payment) }}" data-ajax-post style="display:inline">@csrf<button class="btn sec">Email receipt</button></form>
    <a class="btn sec" href="{{ route('payments.index') }}">All payments</a>
    <form method="POST" action="{{ route('payments.destroy',$payment) }}" style="display:inline" data-confirm="Delete payment {{ $payment->number }}? Invoice totals will update.">@csrf @method('DELETE')<button class="btn danger">Delete</button></form>
  </div>
</div>

@if($payment->public_token)
<div class="card noprint"><div class="card-head"><h3>Client public link</h3><span class="btnrow" style="gap:8px"><button class="btn sec sm" onclick="navigator.clipboard.writeText('{{ route('payments.public', $payment->public_token) }}');this.textContent='Copied!';setTimeout(()=>this.textContent='Copy link',1500)">Copy link</button><form method="POST" action="{{ route('payments.link',$payment) }}" data-ajax-post data-refresh="page" style="display:inline" data-confirm="Generate a new link? The old one stops working.">@csrf<button class="btn sec sm">New link</button></form></span></div>
<p class="hint" style="margin:0">No login needed — the client can view and download (PDF) this receipt.</p></div>
@else
<div class="card noprint"><div class="card-head"><h3>Client public link</h3><form method="POST" action="{{ route('payments.link',$payment) }}" style="display:inline">@csrf<button class="btn sec sm">Create link</button></form></div></div>
@endif

<div class="card">
  <div class="doc-head">
    <div class="doc-brand">
      @if($invLogo)@include('brand.logo', ['light' => $invLogo, 'dark' => $invDark ?? '', 'cls' => 'doc-logo', 'hideName' => true])@endif
      <div><h2>{{ $brand['company_name'] ?? '' }}</h2><div class="doc-sub">{{ $brand['address'] ?? '' }}</div>@if(brand_contact($brand))<div class="doc-sub">{{ brand_contact($brand) }}</div>@endif</div>
    </div>
    <div class="doc-meta"><h3>PAYMENT RECEIPT</h3><div class="inv-no">{{ $payment->number ?? '' }}</div><div class="hint">{{ d($payment->paid_on, 'j F Y') }}</div></div>
  </div>
  <div class="bill-grid">
    <div class="bill-box"><div class="cap">Received from</div><b>{{ $payment->client->company ?? $payment->client->name ?? '' }}</b>
      @if($payment->client && $payment->client->company)<div class="sub">Attn: {{ $payment->client->name }}</div>@endif
      <div class="sub">{{ $payment->client->address ?? '' }}</div>
      <div class="sub">GSTIN: {{ $payment->client->gstin ?? 'Unregistered' }}</div></div>
    <div class="bill-box"><div class="cap">Payment details</div>
      <div class="sub">Receipt no: <b>{{ $payment->number ?? '—' }}</b></div>
      <div class="sub">Date: {{ d($payment->paid_on, 'j F Y') }}</div>
      <div class="sub">Method: {{ ucfirst($payment->method) }}</div>
      @if($payment->reference)<div class="sub">Reference: {{ $payment->reference }}</div>@endif
      <div class="sub">Against invoice: @if($payment->invoice)<a href="{{ route('invoices.show',$payment->invoice) }}">{{ $payment->invoice->number }}</a>@else Advance @endif</div></div>
  </div>
  <div class="tbl-scroll"><table class="tbl"><tr><th style="width:4%">#</th><th>Description</th><th style="width:20%;text-align:right">Amount</th></tr>
  <tr><td>1</td><td>Payment received @if($payment->invoice) against invoice {{ $payment->invoice->number }} @else as advance @endif @if($payment->notes) — {{ $payment->notes }} @endif</td><td class="nowrap" style="text-align:right">{{ money($payment->amount) }}</td></tr></table></div>
  <div class="tot-band">
    <div class="tot-row grand"><span>Total received</span><span>{{ money($payment->amount) }}</span></div>
  </div>
  <div class="words">Amount in words: {{ amount_words($payment->amount) }} only.</div>
  <div class="bill-grid">
    <div class="bill-box"><div class="cap">Declaration</div><div class="sub">Received with thanks the sum stated above.</div></div>
    <div class="bill-box" style="text-align:right"><div class="cap">For {{ $brand['company_name'] ?? '' }}</div><div style="height:52px;display:flex;align-items:flex-end;justify-content:flex-end">@if(setting('invoice.signature_path'))<img src="{{ asset('storage/'.setting('invoice.signature_path')) }}" alt="signature" style="max-height:52px;max-width:180px;object-fit:contain">@endif</div><div class="sub">Authorised Signatory</div></div>
  </div>
</div>
@endsection
