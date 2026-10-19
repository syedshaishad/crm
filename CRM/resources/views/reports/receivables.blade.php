@extends('layouts.app')
@section('title','Receivables ageing')
@section('content')
<div class="card">
<div class="card-head"><h3>Ageing · {{ money($totals->sum()) }} open</h3></div>
<div class="grid stats cols-4" style="margin-bottom:14px">
@foreach($totals as $b=>$t)<div class="stat acc" style="--acc:{{ $b==='90+ days' ? '#ff453a' : ($b==='Not due yet' ? '#30d158' : '#ff9f0a') }}"><div class="lbl">{{ $b }}</div><b>{{ money($t) }}</b><div class="sub">{{ count($buckets[$b]) }} invoices</div></div>@endforeach
</div>
@foreach($buckets as $b=>$g)
@if(count($g))
<div class="fsec">{{ $b }} — {{ money($totals[$b]) }}</div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Invoice</th><th>Client</th><th>Due</th><th style="text-align:right">Balance</th></tr>
@foreach($g as $i)<tr><td class="nowrap"><b>{{ $i['number'] }}</b></td><td>{{ $i['company'] ?: $i['name'] }}</td><td class="nowrap">{{ d($i['due_date']) ?: '—' }}</td><td class="nowrap" style="text-align:right"><b>{{ money($i['bal']) }}</b></td></tr>@endforeach</table></div>
@endif
@endforeach
@if($rows->isEmpty())<div class="empty">Nothing open — every invoice is settled.</div>@endif
</div>
@endsection
