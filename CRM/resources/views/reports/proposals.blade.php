@extends('layouts.app')
@section('title','Proposal analytics')
@section('content')
@php
  $stageTone = ['sent' => 'blue', 'accepted' => 'green', 'invoiced' => 'green', 'declined' => 'red', 'expired' => 'amber', 'draft' => 'grey'];
@endphp
<x-page-header title="Proposal analytics" :crumb="d($from, 'j M Y').' → '.d($to, 'j M Y').' · '.$rows->count().' quotes'" icon='<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6"/><path d="m9.5 14.5 2 2 3.5-4"/>'/>

<div class="card ix-card">
<div class="card-head"><h2>Filters</h2></div>
<form method="GET" class="noprint rp-filter" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
<div><label class="f">From</label><input class="in" style="margin:0" type="date" name="from" value="{{ $from }}" aria-label="Report from date"></div>
<div><label class="f">To</label><input class="in" style="margin:0" type="date" name="to" value="{{ $to }}" aria-label="Report to date"></div>
<div><button class="btn sec sm">Apply</button></div></form>
</div>

<div class="grid stats cols-4">
  <div class="stat"><div class="lbl">Won</div><b>{{ money($won) }}</b><div class="sub">{{ $wonC }} accepted · {{ $decided > 0 ? round($won / $decided * 100) : 0 }}% of decided value</div></div>
  <div class="stat"><div class="lbl">Lost</div><b>{{ money($lost) }}</b><div class="sub">declined + expired</div></div>
  <div class="stat"><div class="lbl">Win rate</div><b>{{ $decidedC > 0 ? round($wonC / $decidedC * 100) : 0 }}%</b><div class="sub">{{ $wonC }} of {{ $decidedC }} decided</div></div>
  <div class="stat"><div class="lbl">Awaiting reply</div><b>{{ money($ageingT) }}</b><div class="sub">open quotes</div></div>
</div>

<div class="cols-2">
<div class="card ix-card"><div class="card-head"><h2>Funnel</h2></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Stage</th><th style="text-align:right">Count</th><th style="text-align:right">Value</th></tr>
@foreach(['sent','accepted','invoiced','declined','expired','draft'] as $st)<tr><td>{!! status_tag(ucfirst($st), $stageTone[$st] ?? 'grey') !!}</td><td class="nowrap" style="text-align:right">{{ $funnel[$st]['c'] ?? 0 }}</td><td class="nowrap" style="text-align:right">{{ money($funnel[$st]['t'] ?? 0) }}</td></tr>@endforeach</table></div></div>
<div class="card ix-card"><div class="card-head"><h2>Ageing of open quotes</h2></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Age</th><th style="text-align:right">Count</th></tr>
@foreach($buckets as $b=>$c)<tr><td>{{ $b }}</td><td class="nowrap" style="text-align:right">{{ $c }}</td></tr>@endforeach</table></div></div>
</div>

<div class="card ix-card"><div class="card-head"><h2>Top quoted clients</h2></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Client</th><th style="text-align:right">Quotes</th><th style="text-align:right">Value</th></tr>
@forelse($byClient as $name=>$g)<tr><td>{{ $name }}</td><td class="nowrap" style="text-align:right">{{ $g['c'] }}</td><td class="nowrap" style="text-align:right">{{ money($g['t']) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">No proposals in this period.</div></td></tr>@endforelse</table></div></div>

<div class="cols-2">
<div class="card ix-card"><div class="card-head"><h2>Why we lose</h2></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Reason</th><th style="text-align:right">Count</th><th style="text-align:right">Value</th></tr>
@forelse($byReason as $rk=>$g)<tr><td>{{ $reasons[$rk] ?? 'Not recorded' }}</td><td class="nowrap" style="text-align:right">{{ $g['c'] }}</td><td class="nowrap" style="text-align:right">{{ money($g['t']) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">No declined proposals in this period.</div></td></tr>@endforelse</table></div></div>
<div class="card ix-card"><div class="card-head"><h2>Lost to competitors</h2></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Competitor</th><th style="text-align:right">Count</th><th style="text-align:right">Value</th></tr>
@forelse($byCompetitor as $name=>$g)<tr><td>{{ $name }}</td><td class="nowrap" style="text-align:right">{{ $g['c'] }}</td><td class="nowrap" style="text-align:right">{{ money($g['t']) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">Nobody named yet — record it when a deal is lost.</div></td></tr>@endforelse</table></div></div>
</div>
@endsection
