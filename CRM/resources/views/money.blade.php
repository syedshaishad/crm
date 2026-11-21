@extends('layouts.app')
@section('title','Money')
@section('content')
@php
  $order = ['paid'=>0,'partial'=>1,'sent'=>2,'overdue'=>3,'draft'=>4,'cancelled'=>5];
  $R = 60; $C = round(2 * M_PI * $R, 1); $off = 0;
  $dsegs = [];
  foreach ($pipeline as $p) {
      if (($p->t ?? 0) > 0) $dsegs[] = ['status' => $p->status, 'c' => $p->c, 't' => $p->t, 'frac' => $p->t / $pipelineTotal];
  }
  usort($dsegs, fn ($a, $b) => ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9));

  $inTotal = (float) collect($months)->sum('revenue');
  $outTotal = (float) collect($months)->sum('expenses');
  $netTotal = $inTotal - $outTotal;

  // Trailing three months of actuals plus the three-month forecast, one scale.
  $series = [];
  foreach (array_slice($months, -3) as $t) $series[] = ['label' => $t['label'], 'value' => $t['revenue'], 'fc' => false];
  foreach ($forecast as $f) $series[] = ['label' => $f['label'], 'value' => $f['value'], 'fc' => true];
  $seriesMax = max(1, (float) collect($series)->max('value'));
  $avgFc = (float) ($forecast[0]['value'] ?? 0);
  $pipeCover = $target > 0 ? round($openPipe / $target * 100) : 0;
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="6" width="19" height="13" rx="2.5"/><path d="M2.5 10h19M16 14.5h2.5"/></svg>
    </span>
    <div>
      <h1>Money</h1>
      <div class="crumb">{{ money($collected) }} collected · {{ money($outstanding) }} outstanding{{ $overdueTotal > 0 ? ' · '.money($overdueTotal).' overdue' : '' }}</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    @if(module_visible('Invoices'))<a class="btn sm sec" href="{{ route('invoices.create') }}">+ New invoice</a>@endif
    @if(module_visible('Payments'))<a class="btn sm" href="{{ route('payments.create') }}">Record payment</a>@endif
  </div>
</div>

<div class="money-grid money-grid-top{{ $target > 0 ? '' : ' is-single' }}">
  <section class="card">
    <div class="card-head">
      <div><h2>Cashflow — last 6 months</h2><span class="hint">Money in vs money out</span></div>
      @if(module_visible('Reports'))<a class="hint" href="{{ route('reports.index') }}">Full reports →</a>@endif
    </div>
    <div class="mchart-scroll">
      <div class="mchart" role="img" aria-label="Money in and money out for each of the last six months">
        @foreach($months as $m)
        <div class="mcol">
          <div class="mbars">
            <div class="mbar in" style="height:{{ round($m['revenue'] / $maxBar * 100) }}%" title="In {{ $m['label'] }}: {{ money($m['revenue']) }}"></div>
            <div class="mbar out" style="height:{{ round($m['expenses'] / $maxBar * 100) }}%" title="Out {{ $m['label'] }}: {{ money($m['expenses']) }}"></div>
          </div>
          <span class="mlabel">{{ $m['label'] }}</span>
        </div>
        @endforeach
      </div>
    </div>
    <div class="mlegend">
      <span><i class="mdot in"></i>Money in <b>{{ money($inTotal) }}</b></span>
      <span><i class="mdot out"></i>Money out <b>{{ money($outTotal) }}</b></span>
      <span class="mnet">Net <b class="{{ $netTotal < 0 ? 'neg' : '' }}">{{ money($netTotal) }}</b></span>
    </div>
  </section>

  @if($target > 0)
  <section class="card">
    <div class="card-head">
      <div><h2>Ahead — target &amp; forecast</h2><span class="hint">{{ date('F Y') }}</span></div>
    </div>
    <div class="mline"><span>Collected vs {{ money($target) }} target</span><b>{{ $targetPct }}%</b></div>
    <div class="progress" role="img" aria-label="{{ $targetPct }}% of the monthly target collected"><div style="width:{{ $targetPct }}%"></div></div>
    <div class="mlegend">
      <span><i class="mdot in"></i>Actual</span>
      <span><i class="mdot fc"></i>Forecast</span>
    </div>
    <div class="mchart-scroll">
      <div class="mchart" role="img" aria-label="Last three months collected and the next three months forecast">
        @foreach($series as $s)
        <div class="mcol">
          <div class="mbars">
            <div class="mbar {{ $s['fc'] ? 'fc' : 'in' }}" style="height:{{ round($s['value'] / $seriesMax * 100) }}%" title="{{ $s['fc'] ? 'Forecast' : 'Collected' }} {{ $s['label'] }}: {{ money($s['value']) }}"></div>
          </div>
          <span class="mlabel">{{ $s['label'] }}</span>
        </div>
        @endforeach
      </div>
    </div>
    <div class="mfoot">
      <div><span>Projected / month</span><b>{{ money($avgFc) }}</b></div>
      <div><span>Open pipeline</span><b>{{ money($openPipe) }}</b><small>{{ $pipeCover }}% of target</small></div>
    </div>
  </section>
  @endif
</div>

<section class="card">
  <div class="card-head">
    <div><h2>Money position</h2><span class="hint">Where the money sits</span></div>
    @if(module_visible('Invoices'))<a class="hint" href="{{ route('invoices.index') }}">Invoices →</a>@endif
  </div>
  <div class="mp-body">
    <div class="donut-svg">
      <svg viewBox="0 0 150 150" role="img" aria-label="Invoice value by status">
        <circle cx="75" cy="75" r="{{ $R }}" fill="none" stroke="var(--dc-line)" stroke-width="17"/>
        @foreach($dsegs as $s)
          @php
            $len = round($s['frac'] * $C - (count($dsegs) > 1 ? 5 : 0), 1); $len = max($len, 1);
            $o = -$off; $off += $s['frac'] * $C;
          @endphp
          <circle cx="75" cy="75" r="{{ $R }}" fill="none" stroke="{{ invoice_status_color($s['status']) }}" stroke-width="17" stroke-linecap="round" stroke-dasharray="{{ $len }} {{ $C }}" stroke-dashoffset="{{ round($o, 1) }}" transform="rotate(-90 75 75)"><title>{{ invoice_status_label($s['status']) }}: {{ money($s['t']) }}</title></circle>
        @endforeach
      </svg>
      <div class="donut-center"><b>{{ $collectionRate }}%</b><span>collected</span></div>
    </div>
    <ul class="mp-legend">
      @foreach($dsegs as $s)
      <li>
        <i class="mdot" style="background:{{ invoice_status_color($s['status']) }}"></i>
        <span class="mp-name">{{ invoice_status_label($s['status']) }} <em>({{ $s['c'] }})</em></span>
        <span class="mp-pct">{{ round($s['frac'] * 100) }}%</span>
        <span class="mp-amt">{{ money($s['t']) }}</span>
      </li>
      @endforeach
    </ul>
  </div>
  <div class="mp-summary">
    <div class="mp-stat"><span>Billed</span><b>{{ money($billed) }}</b></div>
    <div class="mp-stat"><span>Collected</span><b>{{ money($collected) }}</b></div>
    <div class="mp-stat"><span>Outstanding</span><b>{{ money($outstanding) }}</b></div>
    <div class="mp-stat is-overdue"><span>Overdue</span><b>{{ money($overdueTotal) }}</b></div>
  </div>
</section>

@if(module_visible('Invoices'))
<div class="money-grid">
  <section class="card">
    <div class="card-head">
      <div><h2>Recent invoices</h2><span class="hint">Latest bills</span></div>
      <div class="btnrow"><a class="btn sm sec" href="{{ route('invoices.create') }}">+ New</a><a class="hint" href="{{ route('invoices.index') }}">View all →</a></div>
    </div>
    <div class="tbl-scroll">
      <table class="tbl">
        <thead><tr><th scope="col">Number</th><th scope="col">Total</th><th scope="col">Status</th></tr></thead>
        <tbody>
        @forelse($recentInvoices as $i)
          <tr>
            <td class="nowrap"><a href="{{ route('invoices.show',$i->id) }}">{{ $i->number }}</a></td>
            <td class="nowrap">{{ money($i->total) }}</td>
            <td>{!! invoice_status_tag($i->status) !!}</td>
          </tr>
        @empty
          <tr><td colspan="3"><div class="empty">No invoices yet.<a class="btn sm" href="{{ route('invoices.create') }}">Create your first invoice</a></div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </section>
  <section class="card">
    <div class="card-head">
      <div><h2>Overdue invoices</h2><span class="hint">Needs chasing</span></div>
      <a class="hint" href="{{ route('invoices.index',['status'=>'overdue']) }}">View all →</a>
    </div>
    <div class="tbl-scroll">
      <table class="tbl">
        <thead><tr><th scope="col">Number</th><th scope="col">Due</th><th scope="col">Total</th></tr></thead>
        <tbody>
        @forelse($overdue as $i)
          <tr>
            <td class="nowrap"><a href="{{ route('invoices.show',$i->id) }}">{{ $i->number }}</a></td>
            <td class="nowrap">{{ d($i->due_date, 'j M Y') }}</td>
            <td class="nowrap">{{ money($i->total) }}</td>
          </tr>
        @empty
          <tr><td colspan="3"><div class="empty">Nothing overdue. Nice.</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@endif
@endsection
