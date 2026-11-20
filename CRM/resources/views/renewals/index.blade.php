@extends('layouts.app')
@section('title','Renewals')
@section('content')
@php
  $statuses = ['upcoming', 'drafted', 'won', 'lost', 'ignored', 'all'];
  $urlFor = fn ($s) => route('renewals.index', $s === 'upcoming' ? [] : ['status' => $s]);
  $months = $all->whereIn('status', ['upcoming', 'drafted'])
    ->filter(fn ($r) => $r->renewal_date)
    ->groupBy(fn ($r) => $r->renewal_date->format('Y-m'))
    ->map(fn ($g, $k) => ['label' => \Carbon\Carbon::createFromFormat('Y-m', $k)->format('M Y'), 'amount' => $g->sum('amount')])
    ->sortKeys()
    ->take(8);
  $monthMax = max(1, $months->max('amount'));
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.3 6.3"/><path d="M20 4v5h-5"/></svg></span>
    <div>
      <h1>Renewals</h1>
      <div class="crumb">{{ $stats['upcoming'] }} upcoming · {{ $stats['drafted'] }} drafted · {{ money($stats['value']) }} in play</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <form method="POST" action="{{ route('renewals.sync') }}">@csrf<button class="btn sm sec">Refresh pipeline</button></form>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric"><span class="ix-metric-lbl">Upcoming</span><b class="ix-metric-val">{{ $stats['upcoming'] }}</b><span class="ix-metric-sub">not drafted yet</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Draft proposals</span><b class="ix-metric-val">{{ $stats['drafted'] }}</b><span class="ix-metric-sub">awaiting review</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Won</span><b class="ix-metric-val">{{ $stats['won'] }}</b><span class="ix-metric-sub">renewed</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Lost</span><b class="ix-metric-val">{{ $stats['lost'] }}</b><span class="ix-metric-sub">churned</span></div>
</div>

@if($months->isNotEmpty())
<div class="card" style="margin-bottom:14px">
  <div class="card-head"><div><h2>Renewal value by month</h2><span class="hint">Open and drafted renewals</span></div></div>
  <div style="display:flex;gap:14px;align-items:flex-end;min-height:120px;overflow-x:auto;padding:8px 2px 2px">
    @foreach($months as $m)
    <div style="flex:1 0 72px;min-width:72px;text-align:center">
      <div style="height:{{ max(4, (int) round($m['amount'] / $monthMax * 84)) }}px;background:var(--dc-accent);border-radius:7px 7px 0 0;margin:0 auto 6px;width:70%"></div>
      <small class="hint">{{ $m['label'] }}</small>
      <div class="hint" style="font-size:11px">{{ money($m['amount']) }}</div>
    </div>
    @endforeach
  </div>
</div>
@endif

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h2>Pipeline</h2><span class="hint">From AMC contracts, expiry documents and recurring billing</span></div>
  </div>
  <div class="ix-filters" style="display:flex;gap:6px;flex-wrap:wrap;margin:0 0 12px">
    @foreach($statuses as $s)
    <a class="ix-chip {{$status === $s ? 'on' : ''}}" @if($status === $s ? 'on' : '') aria-current="page" @endif href="{{ $urlFor($s) }}">{{ ucfirst($s) }}</a>
    @endforeach
  </div>
  @if($rows->isEmpty())
    <p class="empty">Nothing here yet. Press <b>Refresh pipeline</b> to pull in renewal dates from AMC, expiry documents and recurring billing.</p>
  @else
  <div class="tbl-scroll roomy">
    <table class="tbl">
      <thead><tr><th scope="col">Renewal</th><th scope="col">Source</th><th scope="col">Client</th><th scope="col">Date</th><th scope="col">In</th><th scope="col">Value</th><th scope="col" class="noprint">Action</th></tr></thead>
      <tbody>
        @foreach($rows as $r)
        <tr>
          <td><b>{{ $r->title }}</b></td>
          <td><span class="chip">{{ ucfirst($r->source_type) }}</span></td>
          <td>{{ $r->client ? ($r->client->company ?: $r->client->name) : '—' }}</td>
          <td>
            {{ $r->renewal_date ? d($r->renewal_date, 'j M Y') : '—' }}
            @if($r->daysAway() !== null)
              <span class="hint">{{ $r->risk() === 'overdue' ? abs($r->daysAway()).' days late' : $r->daysAway().' days' }}</span>
            @endif
          </td>
          <td><span class="badge b-{{ $r->status === 'won' ? 'green' : ($r->status === 'lost' ? 'red' : 'grey') }}">{{ ucfirst($r->status) }}</span></td>
          <td>{{ money($r->amount) }}</td>
          <td class="noprint">
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              @if($r->status === 'upcoming' && $r->client_id)
              <form method="POST" action="{{ route('renewals.draft', $r) }}">@csrf<button class="btn sm sec">Draft proposal</button></form>
              @elseif($r->proposal_id)
              <a class="btn sm sec" href="{{ route('proposals.edit', $r->proposal_id) }}">Open draft</a>
              @endif
              @if(! in_array($r->status, ['won', 'ignored'], true))
              <form method="POST" action="{{ route('renewals.mark', $r) }}">@csrf<input type="hidden" name="status" value="won"><button class="btn sm">Won</button></form>
              @endif
              @if(! in_array($r->status, ['lost', 'ignored'], true))
              <form method="POST" action="{{ route('renewals.mark', $r) }}">@csrf<input type="hidden" name="status" value="lost"><button class="btn sm sec">Lost</button></form>
              @endif
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="pg-rows">{{ $rows->links() }}</div>
@endif
</div>
@endsection
