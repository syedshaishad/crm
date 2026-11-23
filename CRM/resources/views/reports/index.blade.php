@extends('layouts.app')
@section('title','Reports')
@section('content')
@php
  // iOS system palette — the Apple Health / Fitness look.
  
  $catPalette = ['var(--dc-blue)','var(--dc-green)','var(--dc-amber)','var(--dc-red)','var(--dc-accent)','var(--dc-muted)'];
  // Compact axis labels in Indian units: ₹4.2L, ₹56k.
  $short = fn ($v) => '₹'.($v >= 10000000 ? round($v / 10000000, 1).' Cr' : ($v >= 100000 ? round($v / 100000, 1).'L' : ($v >= 1000 ? round($v / 1000, 1).'k' : round($v))));
  // Catmull-Rom → cubic Bézier smoothing for the trend line.
  // $top/$base clamp control points so flat-zero stretches never dip under the axis.
  $smooth = function ($pts, $top, $base) {
    $n = count($pts);
    if ($n < 2) return '';
    if ($n === 2) return "M{$pts[0]['x']},{$pts[0]['y']} L{$pts[1]['x']},{$pts[1]['y']}";
    $clamp = fn ($y) => round(max($top, min($base, $y)), 1);
    $d = "M{$pts[0]['x']},{$pts[0]['y']}";
    for ($i = 0; $i < $n - 1; $i++) {
      $p0 = $pts[max(0, $i - 1)]; $p1 = $pts[$i]; $p2 = $pts[$i + 1]; $p3 = $pts[min($n - 1, $i + 2)];
      $c1x = round($p1['x'] + ($p2['x'] - $p0['x']) / 6, 1); $c1y = $clamp($p1['y'] + ($p2['y'] - $p0['y']) / 6);
      $c2x = round($p2['x'] - ($p3['x'] - $p1['x']) / 6, 1); $c2y = $clamp($p2['y'] + ($p3['y'] - $p1['y']) / 6);
      $d .= " C$c1x,$c1y $c2x,$c2y {$p2['x']},{$p2['y']}";
    }
    return $d;
  };
  $pieTotal = max(1, $byStatus->sum('t'));
  $catTotal = max(1, $byCategory->sum('t'));
  $trendFirst = $trend[0]['value'] ?? 0; $trendLast = end($trend)['value'] ?? 0;
  $delta = $trendFirst > 0 ? round(($trendLast - $trendFirst) / $trendFirst * 100, 1) : ($trendLast > 0 ? 100 : 0);
@endphp
<x-page-header title="Reports" :crumb="d($from, 'j M Y').' → '.d($to, 'j M Y').' · cash basis'" icon='<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>'>
  <a class="btn sm sec" href="{{ route('reports.exports',['from'=>$from,'to'=>$to]) }}">All exports →</a>
</x-page-header>

<div class="card ix-card">
  <div class="card-head"><h2>Filters</h2></div>
  <form method="GET" class="noprint rp-filter" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div><label class="f">From</label><input class="in" style="margin:0" type="date" name="from" value="{{ $from }}" aria-label="Report from date"></div>
    <div><label class="f">To</label><input class="in" style="margin:0" type="date" name="to" value="{{ $to }}" aria-label="Report to date"></div>
    <div><button class="btn sec">Apply</button></div>
  </form>
</div>

<div class="grid stats">
  <div class="stat"><div class="lbl">Revenue</div><b>{{ money($revenue) }}</b><div class="sub">collected in range</div></div>
  <div class="stat"><div class="lbl">Expenses</div><b>{{ money($expenses) }}</b><div class="sub">spent in range</div></div>
  <div class="stat"><div class="lbl">Profit</div><b>{{ money($revenue - $expenses) }}</b><div class="sub">{{ $revenue - $expenses >= 0 ? 'healthy' : 'loss' }}</div></div>
</div>

<div class="card ix-card"><div class="card-head"><h2>Revenue trend — last 6 months</h2><span style="display:flex;gap:8px;align-items:center"><span class="delta {{ $delta > 0.05 ? 'up' : ($delta < -0.05 ? 'down' : 'flat') }}">{{ $delta > 0.05 ? '▲' : ($delta < -0.05 ? '▼' : '●') }} {{ abs($delta) }}%</span><span class="hint">{{ money(collect($trend)->sum('value')) }} total</span></span></div>
@php
  $W = 640; $H = 210; $PL = 52; $PR = 16; $PT = 18; $PB = 30;
  $plotW = $W - $PL - $PR; $plotH = $H - $PT - $PB; $tMax = max(1, $trendMax);
  $pts = [];
  foreach ($trend as $i => $t) {
    $x = $PL + (count($trend) > 1 ? $i * ($plotW / (count($trend) - 1)) : $plotW / 2);
    $y = $PT + $plotH - ($t['value'] / $tMax) * $plotH;
    $pts[] = ['x' => round($x, 1), 'y' => round($y, 1), 'label' => $t['label'], 'value' => $t['value']];
  }
  $line = $smooth($pts, $PT, $H - $PB);
  $area = $line . " L{$pts[count($pts) - 1]['x']}," . ($H - $PB) . " L{$pts[0]['x']}," . ($H - $PB) . ' Z';
  $last = end($pts);
@endphp
<svg class="trend" viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="Revenue trend">
  <defs><linearGradient id="revfill" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0" stop-color="var(--dc-blue)" stop-opacity="0.32"/><stop offset="1" stop-color="var(--dc-blue)" stop-opacity="0.02"/>
  </linearGradient></defs>
  @for($g = 0; $g <= 3; $g++)
    @php $gy = $PT + $g * ($plotH / 3); $gv = $tMax * (1 - $g / 3); @endphp
    <line class="gridln" x1="{{ $PL }}" y1="{{ round($gy, 1) }}" x2="{{ $W - $PR }}" y2="{{ round($gy, 1) }}"/>
    <text class="ax" x="{{ $PL - 8 }}" y="{{ round($gy + 4, 1) }}" text-anchor="end">{{ $short($gv) }}</text>
  @endfor
  <path class="area" d="{{ $area }}" fill="url(#revfill)"/>
  <path class="ln" d="{{ $line }}" fill="none" stroke="var(--dc-blue)" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
  @foreach($pts as $i => $p)
    @if($i === count($pts) - 1)
      <circle class="halo" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="9" fill="var(--dc-blue)" opacity="0.18"/>
      <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="5.5" fill="var(--dc-blue)" stroke="var(--dc-panel)" stroke-width="2.5"><title>{{ $p['label'] }}: {{ money($p['value']) }}</title></circle>
    @else
      <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4" fill="var(--dc-panel)" stroke="var(--dc-blue)" stroke-width="2.5"><title>{{ $p['label'] }}: {{ money($p['value']) }}</title></circle>
    @endif
  @endforeach
</svg>
<div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted);padding:0 {{ $PR }}px 0 {{ $PL - 14 }}px">@foreach($trend as $t)<span>{{ $t['label'] }}</span>@endforeach</div>
</div>

<div class="cols-2">
<div class="card ix-card"><div class="card-head"><h2>Invoices by status</h2><a class="hint" href="{{ route('reports.breakdown',['from'=>$from,'to'=>$to]) }}">Details →</a></div>
  @php
    $R = 60; $C = round(2 * M_PI * $R, 1); $off = 0;
    $segs = [];
    foreach ($byStatus as $p) { $segs[] = ['status' => $p->status, 'c' => $p->c, 't' => $p->t ?? 0, 'frac' => ($p->t ?? 0) / $pieTotal]; }
    $segs = array_values(array_filter($segs, fn ($s) => $s['frac'] > 0));
  @endphp
  <div class="donut-wrap">
    <div class="donut-svg">
      <svg viewBox="0 0 150 150" role="img" aria-label="Invoices by status">
        <circle cx="75" cy="75" r="{{ $R }}" fill="none" stroke="var(--surface-2)" stroke-width="17"/>
        @foreach($segs as $s)
          @php
            $len = round($s['frac'] * $C - (count($segs) > 1 ? 5 : 0), 1); $len = max($len, 1);
            $o = -$off; $off += $s['frac'] * $C;
            $col = invoice_status_color($s['status']);
          @endphp
          <circle class="seg" cx="75" cy="75" r="{{ $R }}" fill="none" stroke="{{ $col }}" stroke-width="17" stroke-linecap="round" stroke-dasharray="{{ $len }} {{ $C }}" stroke-dashoffset="{{ round($o, 1) }}" transform="rotate(-90 75 75)"><title>{{ invoice_status_label($s['status']) }}: {{ money($s['t']) }}</title></circle>
        @endforeach
      </svg>
      <div class="donut-center"><b>{{ $byStatus->sum('c') }}</b><span>invoices</span></div>
    </div>
    <div class="legend">
      @foreach($segs as $s)<div class="row"><span class="dot" style="background:{{ invoice_status_color($s['status']) }}"></span>{{ invoice_status_label($s['status']) }} ({{ $s['c'] }})<span class="pct">{{ round($s['frac'] * 100) }}%</span><span class="amt">{{ money($s['t']) }}</span></div>@endforeach
    </div>
  </div>
</div>
<div class="card ix-card"><div class="card-head"><h2>Expenses by category</h2><span class="hint">{{ money($byCategory->sum('t')) }}</span></div>
  @forelse($byCategory as $i => $r)
  @php $pct = round($r->t / $catTotal * 100); $cc = $catPalette[$i % count($catPalette)]; @endphp
  <div class="cat-row"><div class="top"><span>{{ $pct }}% · {{ $r->category ?? 'general' }} ({{ $r->c }})</span><b>{{ money($r->t) }}</b></div>
  <div class="segbar"><div style="width:{{ $pct }}%;background:linear-gradient(180deg,rgba(255,255,255,.38),rgba(255,255,255,0) 60%),{{ $cc }}"></div></div></div>
  @empty<div class="empty">No expenses in range.</div>@endforelse
</div>
</div>
@endsection
