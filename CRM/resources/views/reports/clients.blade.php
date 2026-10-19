@extends('layouts.app')
@section('title','Client profitability')
@section('content')
<div class="card">
<div class="card-head"><h3>Profitability · {{ $from }} to {{ $to }}</h3></div>
<form method="GET" class="noprint" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:12px">
<div><label class="f">From</label><input class="in" style="margin:0" type="date" name="from" value="{{ $from }}"></div>
<div><label class="f">To</label><input class="in" style="margin:0" type="date" name="to" value="{{ $to }}"></div>
<div><button class="btn sec sm">Apply</button></div></form>
<p class="hint" style="margin:0 0 12px">Cash received minus approved project costs. General (project-less) expenses stay out — they belong to the business, not a client.</p>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>#</th><th>Client</th><th style="text-align:right">Received</th><th style="text-align:right">Project costs</th><th style="text-align:right">Profit</th></tr>
@forelse($rows as $i=>$r)<tr><td>{{ $i + 1 }}</td><td><a href="{{ route('clients.show',$r['client']->id) }}">{{ $r['client']->company ?: $r['client']->name }}</a></td><td class="nowrap" style="text-align:right">{{ money($r['received']) }}</td><td class="nowrap" style="text-align:right">{{ money($r['costs']) }}</td><td class="nowrap" style="text-align:right"><b style="{{ $r['profit'] < 0 ? 'color:var(--bad)' : '' }}">{{ money($r['profit']) }}</b></td></tr>@empty<tr><td colspan="5"><div class="empty">No clients.</div></td></tr>@endforelse</table></div>
</div>
@endsection
