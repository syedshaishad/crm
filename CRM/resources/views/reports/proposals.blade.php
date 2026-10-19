@extends('layouts.app')
@section('title','Proposal analytics')
@section('content')
<div class="card">
<div class="card-head"><h3>Proposals · {{ $from }} to {{ $to }}</h3><span class="hint">{{ $rows->count() }} quotes</span></div>
<form method="GET" class="noprint" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:12px">
<div><label class="f">From</label><input class="in" style="margin:0" type="date" name="from" value="{{ $from }}"></div>
<div><label class="f">To</label><input class="in" style="margin:0" type="date" name="to" value="{{ $to }}"></div>
<div><button class="btn sec sm">Apply</button></div></form>
<div class="grid stats cols-4" style="margin-bottom:14px">
  <div class="stat acc" style="--acc:#30d158"><div class="lbl">Won</div><b>{{ money($won) }}</b><div class="sub">{{ $wonC }} accepted · {{ $decided > 0 ? round($won / $decided * 100) : 0 }}% of decided value</div></div>
  <div class="stat acc" style="--acc:#ff453a"><div class="lbl">Lost</div><b>{{ money($lost) }}</b><div class="sub">declined + expired</div></div>
  <div class="stat acc" style="--acc:#0a84ff"><div class="lbl">Win rate</div><b>{{ $decidedC > 0 ? round($wonC / $decidedC * 100) : 0 }}%</b><div class="sub">{{ $wonC }} of {{ $decidedC }} decided</div></div>
  <div class="stat acc" style="--acc:#ff9f0a"><div class="lbl">Awaiting reply</div><b>{{ money($ageingT) }}</b><div class="sub">open quotes</div></div>
</div>
<div class="cols-2">
<div><div class="fsec" style="margin-top:0">Funnel</div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Stage</th><th style="text-align:right">Count</th><th style="text-align:right">Value</th></tr>
@foreach(['sent','accepted','invoiced','declined','expired','draft'] as $st)<tr><td>{{ ucfirst($st) }}</td><td class="nowrap" style="text-align:right">{{ $funnel[$st]['c'] ?? 0 }}</td><td class="nowrap" style="text-align:right">{{ money($funnel[$st]['t'] ?? 0) }}</td></tr>@endforeach</table></div></div>
<div><div class="fsec" style="margin-top:0">Ageing of open quotes</div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Age</th><th style="text-align:right">Count</th></tr>
@foreach($buckets as $b=>$c)<tr><td>{{ $b }}</td><td class="nowrap" style="text-align:right">{{ $c }}</td></tr>@endforeach</table></div></div>
</div>
<div class="fsec">Top quoted clients</div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Client</th><th style="text-align:right">Quotes</th><th style="text-align:right">Value</th></tr>
@forelse($byClient as $name=>$g)<tr><td>{{ $name }}</td><td class="nowrap" style="text-align:right">{{ $g['c'] }}</td><td class="nowrap" style="text-align:right">{{ money($g['t']) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">No proposals in this period.</div></td></tr>@endforelse</table></div>
<div class="cols-2">
<div><div class="fsec">Why we lose</div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Reason</th><th style="text-align:right">Count</th><th style="text-align:right">Value</th></tr>
@forelse($byReason as $rk=>$g)<tr><td>{{ $reasons[$rk] ?? 'Not recorded' }}</td><td class="nowrap" style="text-align:right">{{ $g['c'] }}</td><td class="nowrap" style="text-align:right">{{ money($g['t']) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">No declined proposals in this period.</div></td></tr>@endforelse</table></div></div>
<div><div class="fsec">Lost to competitors</div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Competitor</th><th style="text-align:right">Count</th><th style="text-align:right">Value</th></tr>
@forelse($byCompetitor as $name=>$g)<tr><td>{{ $name }}</td><td class="nowrap" style="text-align:right">{{ $g['c'] }}</td><td class="nowrap" style="text-align:right">{{ money($g['t']) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">Nobody named yet — record it when a deal is lost.</div></td></tr>@endforelse</table></div></div>
</div>
</div>
@endsection
