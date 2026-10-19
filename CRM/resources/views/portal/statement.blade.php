@extends('portal.layout')
@section('title','Account statement')
@section('content')
<div class="page-head"><h1>Statement</h1><div class="crumb">{{ $client->company ?: $client->name }} · every invoice, payment, credit note and TDS with a running balance.</div></div>
<div class="grid stats cols-4" style="margin-bottom:14px">
  <div class="stat"><div class="lbl">Billed</div><b>{{ money($totals['billed']) }}</b></div>
  <div class="stat"><div class="lbl">Paid</div><b>{{ money($totals['collected'] + $totals['tds']) }}</b></div>
  <div class="stat"><div class="lbl">Credit notes</div><b>{{ money($totals['credits']) }}</b></div>
  <div class="stat"><div class="lbl">Balance due</div><b>{{ money($totals['balance']) }}</b></div>
</div>
<div class="card">
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Date</th><th>Type</th><th>Reference</th><th>Details</th><th style="text-align:right">Debit</th><th style="text-align:right">Credit</th><th style="text-align:right">Balance</th><th></th></tr>
@forelse($rows as $e)<tr><td class="nowrap">{{ d($e['date']) }}</td><td>{{ $e['type'] }}</td><td><b>{{ $e['ref'] }}</b></td><td>{{ $e['details'] }}</td><td class="nowrap" style="text-align:right">@if($e['debit'] > 0){{ money($e['debit']) }}@else<span class="hint">—</span>@endif</td><td class="nowrap" style="text-align:right">@if($e['credit'] > 0){{ money($e['credit']) }}@else<span class="hint">—</span>@endif</td><td class="nowrap" style="text-align:right"><b>{{ money($e['balance']) }}</b></td><td style="text-align:right">@if(!empty($e['link']))<a class="btn sm sec" href="{{ $e['link'] }}">Open</a>@else<span class="hint">—</span>@endif</td></tr>@empty<tr><td colspan="8"><div class="empty">Nothing on your account yet.</div></td></tr>@endforelse</table></div>
</div>
@endsection
