@extends('layouts.app')
@section('title','Receivables ageing')
@section('content')
<x-page-header title="Receivables ageing" :crumb="money($totals->sum()).' open · '.($rows->count()).' invoices'" icon='<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>'/>

<div class="grid stats cols-4">
@foreach($totals as $b=>$t)<div class="stat"><div class="lbl">{{ $b }}</div><b>{{ money($t) }}</b><div class="sub">{{ count($buckets[$b]) }} {{ \Illuminate\Support\Str::plural('invoice', count($buckets[$b])) }}</div></div>@endforeach
</div>

<div class="card ix-card">
<div class="card-head"><h2>Open invoices by bucket</h2><span class="hint">due date decides the bucket</span></div>
@foreach($buckets as $b=>$g)
@if(count($g))
<div class="fsec">{{ $b }} — {{ money($totals[$b]) }}</div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Invoice</th><th>Client</th><th>Due</th><th style="text-align:right">Balance</th></tr>
@foreach($g as $i)<tr><td class="nowrap"><b>{{ $i['number'] }}</b></td><td>{{ $i['company'] ?: $i['name'] }}</td><td class="nowrap">{{ d($i['due_date'], 'j M Y') ?: '—' }}</td><td class="nowrap" style="text-align:right"><b>{{ money($i['bal']) }}</b></td></tr>@endforeach</table></div>
@endif
@endforeach
@if($rows->isEmpty())<div class="empty">Nothing open — every invoice is settled.</div>@endif
</div>
@endsection
