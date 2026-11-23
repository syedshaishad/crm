@extends('layouts.app')
@section('title','Report breakdowns')
@section('content')
<x-page-header title="Collections" :crumb="d($from, 'j M Y').' → '.d($to, 'j M Y').' · by status, method and client'" icon='<path d="M12 3v9h9"/><path d="M12 3a9 9 0 1 0 9 9"/>'/>

<div class="card ix-card">
  <div class="card-head"><h2>Filters</h2></div>
  <form method="GET" class="noprint" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div><label class="f">From</label><input class="in" style="margin:0" type="date" name="from" value="{{ $from }}"></div>
    <div><label class="f">To</label><input class="in" style="margin:0" type="date" name="to" value="{{ $to }}"></div>
    <div><button class="btn sec">Apply</button></div>
  </form>
</div>

<div class="card ix-card"><div class="card-head"><h2>Invoices by status</h2><span class="hint">{{ $byStatus->sum('c') }} invoices</span></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Status</th><th style="text-align:right">Count</th><th style="text-align:right">Total</th></tr>
@forelse($byStatus as $r)<tr><td>{!! invoice_status_tag($r->status) !!}</td><td class="nowrap" style="text-align:right">{{ $r->c }}</td><td class="nowrap" style="text-align:right"><b>{{ money($r->t) }}</b></td></tr>@empty<tr><td colspan="3"><div class="empty">No invoices in range.</div></td></tr>@endforelse</table></div></div>

<div class="card ix-card"><div class="card-head"><h2>Collections by method</h2><span class="hint">{{ money($byMethod->sum('t')) }} received</span></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Method</th><th style="text-align:right">Receipts</th><th style="text-align:right">Amount</th></tr>
@forelse($byMethod as $r)<tr><td><span class="ix-tag">{{ ucfirst($r->method ?? '—') }}</span></td><td class="nowrap" style="text-align:right">{{ $r->c }}</td><td class="nowrap" style="text-align:right"><b>{{ money($r->t) }}</b></td></tr>@empty<tr><td colspan="3"><div class="empty">No collections in range.</div></td></tr>@endforelse</table></div></div>

<div class="cols-2">
<div class="card ix-card"><div class="card-head"><h2>Top clients</h2></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Client</th><th>Invoices</th><th style="text-align:right">Total</th></tr>
@forelse($byClient as $r)<tr><td>{{ $r->name }}</td><td>{{ $r->invoices }}</td><td style="text-align:right">{{ money($r->total) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">No data.</div></td></tr>@endforelse</table></div></div>
<div class="card ix-card"><div class="card-head"><h2>Expenses by project</h2></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Project</th><th style="text-align:right">Spent</th><th style="text-align:right">Billed</th><th style="text-align:right">Profit</th></tr>
@forelse($byProject as $r)@php $b = (float)($projBilled[$r->project] ?? 0); $pf = round($b - $r->t, 2); @endphp<tr><td>{{ $r->project }}</td><td style="text-align:right">{{ money($r->t) }}</td><td style="text-align:right">{{ money($b) }}</td><td style="text-align:right">@if($pf < 0)<b style="color:var(--dc-red)">{{ money($pf) }}</b>@else<b>{{ money($pf) }}</b>@endif</td></tr>@empty<tr><td colspan="4"><div class="empty">No data.</div></td></tr>@endforelse</table></div></div>
</div>
@endsection
