@extends('layouts.app')
@section('title','Money')
@section('content')
@php
  $ios = ['paid'=>'#30d158','partial'=>'#ff9f0a','sent'=>'#0a84ff','draft'=>'#8e8e93','overdue'=>'#ff453a','cancelled'=>'#48484a'];
  $R = 60; $C = round(2 * M_PI * $R, 1); $off = 0;
  $dsegs = [];
  foreach ($pipeline as $p) { $dsegs[] = ['status' => $p->status, 'c' => $p->c, 't' => $p->t ?? 0, 'frac' => ($p->t ?? 0) / $pipelineTotal]; }
  $dsegs = array_values(array_filter($dsegs, fn ($s) => $s['frac'] > 0));
@endphp
<div class="cols-2">
<div class="card"><div class="card-head"><div><h3>Cashflow — last 6 months</h3><span class="hint">Money in vs money out</span></div><div class="btnrow" style="gap:8px">@if(module_visible('Reports'))<a class="hint" href="{{ route('reports.index') }}">Full reports →</a>@endif<div class="has-drop"><button type="button" class="icon-btn" data-drop-toggle aria-label="Card menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg>Menu</button><div class="drop">@if(module_visible('Reports'))<a href="{{ route('reports.index') }}">Full reports</a><a href="{{ route('reports.exports') }}">Data exports</a>@endif</div></div></div></div>
  <div class="bars">
  @foreach($months as $m)
    <div class="bar-group"><div class="bar-pair">
      <div class="bar in" style="height:{{ round($m['revenue']/$maxBar*100) }}%" title="In: {{ money($m['revenue']) }}"></div>
      <div class="bar out" style="height:{{ round($m['expenses']/$maxBar*100) }}%" title="Out: {{ money($m['expenses']) }}"></div>
    </div><div class="bar-label">{{ $m['label'] }}</div></div>
  @endforeach
  </div>
  <div class="chart-legend"><span><span class="dot" style="background:#0a84ff"></span>Money in ({{ money(collect($months)->sum('revenue')) }})</span><span><span class="dot" style="background:#3a4668"></span>Money out ({{ money(collect($months)->sum('expenses')) }})</span></div>
</div>
@if(($target ?? 0) > 0)
<div class="card"><div class="card-head"><div><h3>Ahead — target & forecast</h3><span class="hint">{{ date('F Y') }}</span></div><div class="btnrow" style="gap:8px"><div class="has-drop"><button type="button" class="icon-btn" data-drop-toggle aria-label="Card menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg>Menu</button><div class="drop">@if(module_visible('Reports'))<a href="{{ route('reports.index') }}">Full reports</a>@endif @if(module_visible('Invoices'))<a href="{{ route('invoices.index') }}">All invoices</a>@endif</div></div></div></div>
  <div class="mrow" style="display:flex;justify-content:space-between;gap:12px"><span>Collected vs {{ money($target) }} target</span><b>{{ $targetPct }}%</b></div>
  <div class="progress" style="margin:6px 0 12px"><div style="width:{{ $targetPct }}%"></div></div>
  <div class="bars">
  @foreach($forecast as $f)
    <div class="bar-group"><div class="bar-pair">
      <div class="bar in" style="height:{{ round($f['value']/$forecastMax*100) }}%;background:#30d158" title="Forecast: {{ money($f['value']) }}"></div>
    </div><div class="bar-label">{{ $f['label'] }}</div></div>
  @endforeach
  </div>
  <div class="chart-legend"><span><span class="dot" style="background:#30d158"></span>Forecast {{ money($forecast[0]['value'] ?? 0) }}/mo (3-mo avg)</span><span>Open pipeline {{ money($openPipe) }} = {{ $target > 0 ? round($openPipe/$target*100) : 0 }}% of target</span></div>
</div>
@endif
</div>
<div class="card"><div class="card-head"><div><h3>Money position</h3><span class="hint">Where the money sits</span></div><div class="btnrow" style="gap:8px">@if(module_visible('Invoices'))<a class="hint" href="{{ route('invoices.index') }}">Invoices →</a>@endif<div class="has-drop"><button type="button" class="icon-btn" data-drop-toggle aria-label="Card menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg>Menu</button><div class="drop">@if(module_visible('Invoices'))<a href="{{ route('invoices.index') }}">All invoices</a><a href="{{ route('payments.create') }}">Record payment</a>@endif</div></div></div></div>
  <div class="donut-wrap">
    <div class="donut-svg">
      <svg viewBox="0 0 150 150" role="img" aria-label="Money position">
        <circle cx="75" cy="75" r="{{ $R }}" fill="none" stroke="#ece5d3" stroke-width="17"/>
        @foreach($dsegs as $s)
          @php
            $len = round($s['frac'] * $C - (count($dsegs) > 1 ? 5 : 0), 1); $len = max($len, 1);
            $o = -$off; $off += $s['frac'] * $C;
            $col = $ios[$s['status']] ?? '#94a3b8';
          @endphp
          <circle class="seg" cx="75" cy="75" r="{{ $R }}" fill="none" stroke="{{ $col }}" stroke-width="17" stroke-linecap="round" stroke-dasharray="{{ $len }} {{ $C }}" stroke-dashoffset="{{ round($o, 1) }}" transform="rotate(-90 75 75)"><title>{{ ucfirst($s['status']) }}: {{ money($s['t']) }}</title></circle>
        @endforeach
      </svg>
      <div class="donut-center"><b>{{ $collectionRate }}%</b><span>collected</span></div>
    </div>
    <div class="legend">
      @foreach($dsegs as $s)<div class="row"><span class="dot" style="background:{{ $ios[$s['status']] ?? '#94a3b8' }}"></span>{{ ucfirst($s['status']) }} ({{ $s['c'] }})<span class="pct">{{ round($s['frac'] * 100) }}%</span><span class="amt">{{ money($s['t']) }}</span></div>@endforeach
    </div>
  </div>
  <div class="progress"><div style="width:{{ $collectionRate }}%"></div></div>
  <div class="money-rows">
    <div class="mrow"><span>Billed</span><b>{{ money($billed) }}</b></div>
    <div class="mrow"><span>Collected</span><b>{{ money($collected) }}</b></div>
    <div class="mrow"><span>Outstanding</span><b>{{ money($outstanding) }}</b></div>
    @if($overdueTotal > 0)<div class="mrow"><span>Overdue</span><b style="color:var(--bad)">{{ money($overdueTotal) }}</b></div>@endif
  </div>
</div>
<div class="cols-2">
@if(module_visible('Invoices'))
<div class="card"><div class="card-head"><div><h3>Recent invoices</h3><span class="hint">Latest bills</span></div><div class="btnrow" style="gap:8px"><a class="btn sm sec" href="{{ route('invoices.create') }}">+ New</a><div class="has-drop"><button type="button" class="icon-btn" data-drop-toggle aria-label="Card menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg>Menu</button><div class="drop"><a href="{{ route('invoices.index') }}">View all invoices</a><a href="{{ route('invoices.create') }}">New invoice</a></div></div></div></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Number</th><th>Total</th><th>Status</th></tr>
@forelse($recentInvoices as $i)<tr><td><a href="{{ route('invoices.show',$i->id) }}">{{ $i->number }}</a></td><td>{{ money($i->total) }}</td><td><span class="badge {{ $i->status==='paid' ? 'b-green' : ($i->status==='overdue' ? 'b-red' : (in_array($i->status,['sent','partial']) ? 'b-blue' : 'b-grey')) }}">{{ $i->status }}</span></td></tr>@empty<tr><td colspan="3"><div class="empty">No invoices yet.<br><br><a class="btn sm" href="{{ route('invoices.create') }}">Create your first invoice</a></div></td></tr>@endforelse
</table></div></div>
@endif
<div class="card"><div class="card-head"><div><h3>Overdue invoices</h3><span class="hint">Needs chasing</span></div><div class="btnrow" style="gap:8px"><a class="hint" href="{{ route('invoices.index',['status'=>'overdue']) }}">View all →</a><div class="has-drop"><button type="button" class="icon-btn" data-drop-toggle aria-label="Card menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg>Menu</button><div class="drop"><a href="{{ route('invoices.index',['status'=>'overdue']) }}">View all overdue</a><a href="{{ route('payments.create') }}">Record payment</a></div></div></div></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Number</th><th>Due</th><th>Total</th></tr>
@forelse($overdue as $i)<tr><td><a href="{{ route('invoices.show',$i->id) }}">{{ $i->number }}</a></td><td>{{ d($i->due_date) }}</td><td>{{ money($i->total) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">Nothing overdue. Nice.</div></td></tr>@endforelse
</table></div></div>
</div>
@endsection
