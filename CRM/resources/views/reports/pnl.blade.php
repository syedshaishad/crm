@extends('layouts.app')
@section('title','Profit & loss')
@section('content')
<div class="card">
<div class="card-head"><h3>P&L · {{ d($from,'M Y') }}</h3><span class="btnrow noprint" style="gap:8px"><a class="btn sec sm" href="{{ route('reports.pnl',['month'=>$prev]) }}">← {{ d($prev.'-01','M Y') }}</a>@if($next)<a class="btn sec sm" href="{{ route('reports.pnl',['month'=>$next]) }}">{{ d($next.'-01','M Y') }} →</a>@endif</span></div>
<div class="grid stats cols-4" style="margin-bottom:14px">
  <div class="stat acc" style="--acc:#30d158"><div class="lbl">Revenue in</div><b>{{ money($revenue) }}</b><div class="sub">collections</div></div>
  <div class="stat acc" style="--acc:#ff453a"><div class="lbl">Costs out</div><b>{{ money($costs) }}</b><div class="sub">approved spend</div></div>
  <div class="stat acc" style="--acc:#0a84ff"><div class="lbl">Profit</div><b style="{{ $revenue - $costs < 0 ? 'color:var(--bad)' : '' }}">{{ money($revenue - $costs) }}</b><div class="sub">{{ $revenue > 0 ? round(($revenue - $costs) / $revenue * 100, 1) : 0 }}% margin</div></div>
</div>
<div class="fsec" style="margin-top:0">Costs by category</div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Category</th><th style="text-align:right">Entries</th><th style="text-align:right">Amount</th><th style="min-width:120px">Share</th></tr>
@forelse($byCat as $c)<tr><td>{{ $c->category ?: '—' }}</td><td class="nowrap" style="text-align:right">{{ $c->c }}</td><td class="nowrap" style="text-align:right">{{ money($c->t) }}</td><td><div class="progress" style="margin:0"><div style="width:{{ $costs > 0 ? round($c->t / $costs * 100) : 0 }}%"></div></div></td></tr>@empty<tr><td colspan="4"><div class="empty">No approved spend this month.</div></td></tr>@endforelse</table></div>
<p class="hint">Cash basis: collections vs approved spend. Pending claims and unpaid invoices stay out.</p>
</div>
@endsection
