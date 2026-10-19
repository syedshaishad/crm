@extends('layouts.app')
@section('title','Ledger — '.$client->name)
@section('content')
<div class="page-head"><h1>Ledger</h1><div class="crumb">{{ $client->company ?: $client->name }}{{ $client->gstin ? ' · '.$client->gstin : '' }}</div></div>
<div class="noprint" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
  <a class="btn sm sec" href="{{ route('clients.show',$client) }}">← Client</a>
  <a class="btn sm sec" href="{{ route('clients.ledger.export',$client) }}">Export CSV</a>
  <form method="POST" action="{{ route('clients.statement',$client) }}" data-ajax-post data-refresh="page" style="display:inline" data-confirm="Email the full statement to {{ $client->email ?: 'this client' }}?">@csrf<button class="btn sm">Email statement</button></form>
  <button class="btn sm sec" onclick="window.print()">Print / PDF</button>
</div>

<div class="grid stats">
  <div class="stat acc" style="--acc:#0a84ff"><div class="lbl">Billed</div><b>{{ money($totals['billed']) }}</b><div class="sub">filed invoices</div></div>
  <div class="stat acc" style="--acc:#30d158"><div class="lbl">Collected</div><b>{{ money($totals['collected']) }}</b><div class="sub">payments received</div></div>
  <div class="stat acc" style="--acc:#ff9f0a"><div class="lbl">Credit notes</div><b>{{ money($totals['credits']) }}</b><div class="sub">open +
applied</div></div>
  @if($totals['tds'] > 0)<div class="stat acc" style="--acc:#bf5af2"><div class="lbl">TDS claimed</div><b>{{ money($totals['tds']) }}</b><div class="sub">26AS · settles dues</div></div>@endif
  <div class="stat acc" style="--acc:{{ $totals['balance'] > 0 ? '#ff453a' : '#30d158' }}"><div class="lbl">Balance due</div><b>{{ money($totals['balance']) }}</b><div class="sub">{{ $totals['balance'] > 0 ? 'receivable' : 'settled' }}</div></div>
</div>

<div class="card" style="margin-top:22px">
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Date</th><th>Type</th><th>Reference</th><th>Details</th><th style="text-align:right">Debit</th><th style="text-align:right">Credit</th><th style="text-align:right">Balance</th></tr>
@forelse($rows as $e)<tr><td class="nowrap">{{ d($e['date']) }}</td><td><span class="badge {{ $e['type']==='Invoice' ? 'b-blue' : ($e['type']==='Payment' ? 'b-green' : ($e['type']==='TDS' ? 'b-amber' : 'b-grey')) }}">{{ $e['type'] }}</span></td><td class="nowrap">@if(!empty($e['link']))<a href="{{ $e['link'] }}"><b>{{ $e['ref'] }}</b></a>@else<b>{{ $e['ref'] }}</b>@endif</td><td>{{ $e['details'] }}</td><td class="nowrap" style="text-align:right">{{ $e['debit'] > 0 ? money($e['debit']) : '—' }}</td><td class="nowrap" style="text-align:right">{{ $e['credit'] > 0 ? money($e['credit']) : '—' }}</td><td class="nowrap" style="text-align:right"><b>{{ money($e['balance']) }}</b></td></tr>@empty<tr><td colspan="7"><div class="empty">No ledger entries yet.</div></td></tr>@endforelse</table></div>
<p class="hint" style="margin:10px 0 0">Drafts and cancelled invoices never enter the books. Payments link to their invoice where known.</p></div>
@endsection
