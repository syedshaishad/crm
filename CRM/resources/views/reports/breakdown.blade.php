@extends('layouts.app')
@section('title','Report breakdowns')
@section('content')
<div class="card">
  <div class="card-head"><h3>Filters</h3></div>
  <form method="GET" class="noprint" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div><label class="f">From</label><input class="in" style="margin:0" type="date" name="from" value="{{ $from }}"></div>
    <div><label class="f">To</label><input class="in" style="margin:0" type="date" name="to" value="{{ $to }}"></div>
    <div><button class="btn sec">Apply</button></div>
  </form>
</div>

<div class="card" style="margin-top:22px"><div class="card-head"><h3>Invoices by status</h3><span class="hint">{{ $byStatus->sum('c') }} invoices</span></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Status</th><th style="text-align:right">Count</th><th style="text-align:right">Total</th></tr>
@forelse($byStatus as $r)<tr><td><span class="badge">{{ ucfirst($r->status) }}</span></td><td class="nowrap" style="text-align:right">{{ $r->c }}</td><td class="nowrap" style="text-align:right"><b>{{ money($r->t) }}</b></td></tr>@empty<tr><td colspan="3"><div class="empty">No invoices in range.</div></td></tr>@endforelse</table></div></div>

<div class="card" style="margin-top:22px"><div class="card-head"><h3>Collections by method</h3><span class="hint">{{ money($byMethod->sum('t')) }} received</span></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Method</th><th style="text-align:right">Receipts</th><th style="text-align:right">Amount</th></tr>
@forelse($byMethod as $r)<tr><td><span class="badge b-grey">{{ ucfirst($r->method ?? '—') }}</span></td><td class="nowrap" style="text-align:right">{{ $r->c }}</td><td class="nowrap" style="text-align:right"><b>{{ money($r->t) }}</b></td></tr>@empty<tr><td colspan="3"><div class="empty">No collections in range.</div></td></tr>@endforelse</table></div></div>

<div class="cols-2">
<div class="card"><div class="card-head"><h3>Top clients</h3></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Client</th><th>Invoices</th><th style="text-align:right">Total</th></tr>
@forelse($byClient as $r)<tr><td>{{ $r->name }}</td><td>{{ $r->invoices }}</td><td style="text-align:right">{{ money($r->total) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">No data.</div></td></tr>@endforelse</table></div></div>
<div class="card"><div class="card-head"><h3>Expenses by project</h3></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Project</th><th style="text-align:right">Spent</th><th style="text-align:right">Billed</th><th style="text-align:right">Profit</th></tr>
@forelse($byProject as $r)@php $b = (float)($projBilled[$r->project] ?? 0); $pf = round($b - $r->t, 2); @endphp<tr><td>{{ $r->project }}</td><td style="text-align:right">{{ money($r->t) }}</td><td style="text-align:right">{{ money($b) }}</td><td style="text-align:right">@if($pf < 0)<b style="color:var(--bad)">{{ money($pf) }}</b>@else<b>{{ money($pf) }}</b>@endif</td></tr>@empty<tr><td colspan="4"><div class="empty">No data.</div></td></tr>@endforelse</table></div></div>
</div>
@endsection
