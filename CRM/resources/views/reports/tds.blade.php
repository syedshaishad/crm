@extends('layouts.app')
@section('title','TDS register')
@section('content')
<div class="card">
  <div class="card-head"><h3>Financial year</h3><a class="btn sm sec" href="{{ route('reports.tds.export',['fy'=>$fy]) }}">Export CSV</a></div>
  <form method="GET" class="noprint" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div><label class="f">FY (Apr–Mar)</label><select class="in" style="margin:0" name="fy" onchange="this.form.submit()">@foreach($fys as $f)<option value="{{ $f }}" {{ $f===$fy?'selected':'' }}>{{ $f }}</option>@endforeach</select></div>
  </form>
</div>

<div class="grid stats">
  <div class="stat acc" style="--acc:#30d158"><div class="lbl">TDS on income</div><b>{{ money($tot['rec']) }}</b><div class="sub">clients deducted · claim in 26AS</div></div>
  <div class="stat acc" style="--acc:#ff9f0a"><div class="lbl">TDS on expenses</div><b>{{ money($tot['pay']) }}</b><div class="sub">we deducted · deposit it</div></div>
  <div class="stat acc" style="--acc:#0a84ff"><div class="lbl">Net position</div><b>{{ money($tot['rec'] - $tot['pay']) }}</b><div class="sub">FY {{ $fy }}</div></div>
</div>

<div class="card" style="margin-top:22px"><div class="card-head"><h3>Entries</h3><span class="hint">receivables settle their invoices</span></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Date</th><th>Kind</th><th>Section</th><th style="text-align:right">Rate</th><th>Party</th><th>PAN</th><th>Reference</th><th style="text-align:right">Base</th><th style="text-align:right">TDS</th></tr>
@forelse($rows as $e)<tr><td class="nowrap">{{ d($e['date']) }}</td><td><span class="badge {{ $e['kind']==='receivable' ? 'b-green' : 'b-amber' }}">{{ $e['kind']==='receivable' ? 'On income' : 'On expense' }}</span></td><td class="nowrap">{{ $e['section'] }}</td><td class="nowrap" style="text-align:right">{{ $e['rate'] }}%</td><td>{{ $e['party'] }}</td><td class="nowrap">{{ $e['pan'] }}</td><td class="nowrap">@if($e['link'])<a href="{{ $e['link'] }}"><b>{{ $e['ref'] }}</b></a>@else{{ $e['ref'] }}@endif</td><td class="nowrap" style="text-align:right">{{ money($e['base']) }}</td><td class="nowrap" style="text-align:right"><b>{{ money($e['amount']) }}</b></td></tr>@empty<tr><td colspan="9"><div class="empty">No TDS entries in FY {{ $fy }}. Record one from a payment or an expense.</div></td></tr>@endforelse</table></div>
<p class="hint" style="margin:10px 0 0">Receivable TDS counts toward the invoice's paid amount. Match totals with Form 26AS before filing.</p></div>
@endsection
