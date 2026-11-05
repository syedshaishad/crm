<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Receipt {{ $payment->number ?? '' }} — {{ $brand['company_name'] ?? config('app.name') }}</title>
@if(!empty($brand['favicon_path']))<link rel="icon" href="{{ asset('storage/'.$brand['favicon_path']) }}">@else<link rel="icon" href="{{ asset('favicon.ico') }}">@endif
<link rel="stylesheet" href="{{ asset('assets/css/app.css').'?v=130' }}">
@include('brand.theme')
</head>
<body style="background:var(--bg)">
@php
  $invLogo = setting('invoice.logo_path') ?: ($brand['logo_path'] ?? '');
@endphp
<div class="sheet" style="max-width:860px;margin-left:auto;margin-right:auto">
  <div class="topnav noprint" style="padding:0 0 12px">
    <span class="brand-pill" title="{{ $brand['company_name'] ?? config('app.name') }}">@include('brand.logo')</span>
    <span class="top-actions"><button class="btn sm" onclick="window.print()">Download / PDF</button></span>
  </div>
  <div class="card">
    <div class="doc-head">
      <div class="doc-brand">
        @if($invLogo)<img class="doc-logo" src="{{ asset('storage/'.$invLogo) }}" alt="company logo">@endif
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
        <div class="sub">Against invoice: @if($payment->invoice){{ $payment->invoice->number }}@else Advance @endif</div></div>
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
  <p class="footer-note noprint">Shared by {{ $brand['company_name'] ?? config('app.name') }}</p>
</div>
<script>(function(){try{var s=localStorage.getItem('theme');if(s==='dark'||s==='light')document.documentElement.setAttribute('data-theme',s);}catch(e){}})();</script>
</body>
</html>
