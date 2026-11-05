@extends('layouts.app')
@section('title','AMC & warranty')
@section('content')
@php
  $stateLabels = ['active' => 'Active', 'expiring' => 'Expiring', 'expired' => 'Expired', 'unscheduled' => 'Unscheduled'];
  $stateVal = $state ?? '';
  $hasFilters = ($q ?? '') !== '' || $stateVal !== '';
  $stateUrl = fn ($s) => route('amc.index', array_merge(request()->except(['page', 'state']), $s ? ['state' => $s] : []));
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
    </span>
    <div>
      <h1>AMC &amp; warranty</h1>
      <div class="crumb">{{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('contract', $stats['total']) }} · {{ $stats['active'] }} active · {{ $stats['expiring'] }} expiring soon</div>
    </div>
  </div>
  <div class="ix-head-actions noprint"><a class="btn sm" href="{{ route('amc.create') }}">+ New contract</a></div>
</div>

<div class="ix-metrics">
  <div class="ix-metric"><span class="ix-metric-lbl">All contracts</span><b class="ix-metric-val">{{ $stats['total'] }}</b><span class="ix-metric-sub">{{ $stats['unscheduled'] }} unscheduled</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Active</span><b class="ix-metric-val">{{ $stats['active'] }}</b><span class="ix-metric-sub">covered now</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Expiring soon</span><b class="ix-metric-val">{{ $stats['expiring'] }}</b><span class="ix-metric-sub">within 30 days</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Expired</span><b class="ix-metric-val">{{ $stats['expired'] }}</b><span class="ix-metric-sub">needs renewal</span></div>
</div>

<div class="card ix-card" data-ajax-region="amc-list">
  <div class="card-head ix-card-head">
    <div><h3>Contracts</h3><span class="hint">{{ $contracts->total() }} {{ \Illuminate\Support\Str::plural('contract', $contracts->total()) }}{{ $hasFilters ? ' matching filters' : ' in this workspace' }}</span></div>
  </div>

  <div class="ix-statuses noprint">
    <a class="ix-chip {{ $stateVal === '' ? 'on' : '' }}" href="{{ $stateUrl('') }}"><i style="background:var(--dc-ink2)"></i>All <b>{{ $stats['total'] }}</b></a>
    @foreach($stateLabels as $st => $label)
      @php $dot = ['active' => 'var(--dc-green)', 'expiring' => 'var(--dc-amber)', 'expired' => 'var(--dc-red)', 'unscheduled' => 'var(--dc-muted)'][$st]; @endphp
      <a class="ix-chip {{ $stateVal === $st ? 'on' : '' }}" href="{{ $stateUrl($st) }}"><i style="background:{{ $dot }}"></i>{{ $label }} <b>{{ $stats[$st] ?? 0 }}</b></a>
    @endforeach
  </div>

  <form method="GET" data-ajax-filter data-region="amc-list" class="ix-filters noprint">
    <label class="ix-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search number, title or client…" autocomplete="off">
    </label>
    <select class="in ix-filter" name="state">
      <option value="">All states</option>
      @foreach($stateLabels as $st => $label)
        <option value="{{ $st }}" {{ $stateVal === $st ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
    <button class="btn sec sm ix-filter-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filter</button>
    @if($hasFilters)<a class="btn sm sec ix-clear-btn" href="{{ route('amc.index') }}">Clear</a>@endif
  </form>

  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table am-table">
      <thead>
        <tr><th class="ix-c-main">Contract</th><th class="ix-c-client">Client</th><th class="ix-c-type">Type</th><th class="ix-c-ends">Ends</th><th class="ix-c-value">Value</th><th class="ix-c-status">Status</th><th class="ix-c-actions"></th></tr>
      </thead>
      <tbody>
      @forelse($contracts as $c)
        @php
          $st = $c->state();
          $stateClass = ['active' => 'is-green', 'expiring' => 'is-amber', 'expired' => 'is-red', 'unscheduled' => 'is-grey'][$st] ?? 'is-grey';
          $clientName = $c->client?->company ?: $c->client?->name ?: 'No client';
          $contact = $c->client?->phone ?: $c->client?->email;
          $days = $c->end_date ? (int) now()->startOfDay()->diffInDays($c->end_date->copy()->startOfDay(), false) : null;
          $endsHint = $days === null ? 'No end date' : ($days < 0 ? abs($days).' '.\Illuminate\Support\Str::plural('day', abs($days)).' ago' : ($days === 0 ? 'Today' : 'In '.$days.' '.\Illuminate\Support\Str::plural('day', $days)));
        @endphp
        <tr>
          <td class="ix-c-main">
            <div class="ix-main">
              <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg></span>
              <div class="ix-main-txt">
                <a class="ix-name" href="{{ route('amc.show', $c) }}">{{ $c->title ?: $c->number }}</a>
                <span class="ix-sub">{{ $c->number }}@if($c->product) · {{ $c->product->name }}@endif</span>
              </div>
            </div>
          </td>
          <td class="ix-c-client">
            <div class="ix-main-txt">
              <span class="ix-client">{{ $clientName }}</span>
              @if($contact)<span class="ix-sub">{{ $contact }}</span>@endif
            </div>
          </td>
          <td class="ix-c-type"><span class="ix-tag">{{ strtoupper($c->type ?: 'amc') }}</span></td>
          <td class="ix-c-ends">
            <div class="ix-main-txt">
              <span class="ix-vehicle">{{ $c->end_date ? d($c->end_date) : 'Not scheduled' }}</span>
              <span class="ix-sub {{ $st === 'expired' ? 'is-danger' : ($st === 'expiring' ? 'is-warn' : '') }}">{{ $endsHint }}</span>
            </div>
          </td>
          <td class="ix-c-value"><span class="ix-money">{{ money($c->value) }}</span></td>
          <td class="ix-c-status"><span class="ix-state {{ $stateClass }}"><i></i>{{ $stateLabels[$st] ?? ucfirst($st) }}</span></td>
          <td class="ix-c-actions">
            <div class="ix-actions">
              <a class="ix-act" href="{{ route('amc.show', $c) }}" title="Open contract"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></a>
              <a class="ix-act" href="{{ route('amc.edit', $c) }}" title="Edit contract"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
              <form method="POST" action="{{ route('amc.destroy', $c) }}" data-confirm="Delete contract {{ $c->number }}?">@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete contract"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg></span><strong>{{ $hasFilters ? 'No contracts match these filters' : 'No contracts yet' }}</strong><p>{{ $hasFilters ? 'Try a different search or clear the active filters.' : 'Track AMC and warranty coverage for your clients here.' }}</p><div class="ix-empty-actions">@if($hasFilters)<a class="btn sm sec" href="{{ route('amc.index') }}">Clear filters</a>@else<a class="btn sm" href="{{ route('amc.create') }}">+ New contract</a>@endif</div></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $contracts->links() }}
</div>
@endsection
