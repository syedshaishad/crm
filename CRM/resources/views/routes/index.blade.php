@extends('layouts.app')
@section('title','Delivery routes')
@section('content')
@php
  $statuses = \Modules\Challans\DeliveryRoute::statuses();
  $statusVal = $status ?? '';
  $hasFilters = ($q ?? '') !== '' || $statusVal !== '';
  $statusUrl = fn ($s) => route('routes.index', array_merge(request()->except(['page', 'status']), $s ? ['status' => $s] : []));
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20 4 17V5l5 3 6-3 5 3v12l-5 3-6-3Z"/><path d="M9 8v12M15 5v12"/></svg>
    </span>
    <div>
      <h1>Delivery routes</h1>
      <div class="crumb">{{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('route', $stats['total']) }} · {{ $stats['today'] }} scheduled today</div>
    </div>
  </div>
  <div class="ix-head-actions noprint"><a class="btn sm" href="{{ route('routes.create') }}">+ Plan route</a></div>
</div>

<div class="ix-metrics">
  <div class="ix-metric"><span class="ix-metric-lbl">Today</span><b class="ix-metric-val">{{ $stats['today'] }}</b><span class="ix-metric-sub">scheduled for today</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Planned</span><b class="ix-metric-val">{{ $stats['planned'] }}</b><span class="ix-metric-sub">ready to start</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">On route</span><b class="ix-metric-val">{{ $stats['on_route'] }}</b><span class="ix-metric-sub">moving now</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Completed</span><b class="ix-metric-val">{{ $stats['done'] }}</b><span class="ix-metric-sub">all stops done</span></div>
</div>

<div class="card ix-card" data-ajax-region="routes-list">
  <div class="card-head ix-card-head">
    <div><h3>Routes</h3><span class="hint">{{ $routes->total() }} {{ \Illuminate\Support\Str::plural('route', $routes->total()) }}{{ $hasFilters ? ' matching filters' : ' in this workspace' }}</span></div>
  </div>

  <div class="ix-statuses noprint">
    <a class="ix-chip {{ $statusVal === '' ? 'on' : '' }}" href="{{ $statusUrl('') }}"><i style="background:var(--dc-ink2)"></i>All <b>{{ $stats['total'] }}</b></a>
    @foreach($statuses as $st => $label)
      @php $dot = ['planned' => 'var(--dc-muted)', 'on_route' => 'var(--dc-blue)', 'done' => 'var(--dc-green)'][$st] ?? 'var(--dc-muted)'; @endphp
      <a class="ix-chip {{ $statusVal === $st ? 'on' : '' }}" href="{{ $statusUrl($st) }}"><i style="background:{{ $dot }}"></i>{{ $label }} <b>{{ $stats[$st] ?? 0 }}</b></a>
    @endforeach
  </div>

  <form method="GET" data-ajax-filter data-region="routes-list" class="ix-filters noprint">
    <label class="ix-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search route, vehicle or driver…" autocomplete="off">
    </label>
    <select class="in ix-filter" name="status">
      <option value="">All statuses</option>
      @foreach($statuses as $st => $label)
        <option value="{{ $st }}" {{ $statusVal === $st ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
    <button class="btn sec sm ix-filter-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filter</button>
    @if($hasFilters)<a class="btn sm sec ix-clear-btn" href="{{ route('routes.index') }}">Clear</a>@endif
  </form>

  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table rt-table">
      <thead>
        <tr><th class="ix-c-main">Route</th><th class="ix-c-vehicle">Vehicle &amp; driver</th><th class="ix-c-stops">Stops</th><th class="ix-c-progress">Progress</th><th class="ix-c-status">Status</th><th class="ix-c-actions"></th></tr>
      </thead>
      <tbody>
      @forelse($routes as $r)
        @php
          $st = $r->status ?? 'planned';
          $stateClass = ['planned' => 'is-grey', 'on_route' => 'is-blue', 'done' => 'is-green'][$st] ?? 'is-grey';
          $stopTotal = (int) $r->stops_count;
          $stopDone = (int) $r->stops_done_count;
          $pct = $stopTotal > 0 ? (int) round($stopDone / $stopTotal * 100) : 0;
        @endphp
        <tr>
          <td class="ix-c-main">
            <div class="ix-main">
              <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20 4 17V5l5 3 6-3 5 3v12l-5 3-6-3Z"/><path d="M9 8v12M15 5v12"/></svg></span>
              <div class="ix-main-txt">
                <a class="ix-name" href="{{ route('routes.show', $r) }}">{{ $r->number }}</a>
                <span class="ix-sub">{{ $r->route_date ? d($r->route_date) : 'No date' }}</span>
              </div>
            </div>
          </td>
          <td class="ix-c-vehicle">
            <div class="ix-main-txt">
              <span class="ix-vehicle">{{ $r->vehicle_no ?: 'No vehicle assigned' }}</span>
              @if($r->driver)<span class="ix-sub">{{ $r->driver }}</span>@endif
            </div>
          </td>
          <td class="ix-c-stops"><span class="ix-stops">{{ $r->stops_count }} {{ \Illuminate\Support\Str::plural('stop', $r->stops_count) }}</span></td>
          <td class="ix-c-progress">
            <div class="ix-progress-head"><span>{{ $stopDone }}/{{ $stopTotal }} done</span><b>{{ $pct }}%</b></div>
            <div class="ix-progress"><i style="width:{{ $pct }}%;--ix-fill:{{ $pct >= 100 ? 'var(--dc-green)' : 'var(--dc-accent)' }}"></i></div>
          </td>
          <td class="ix-c-status"><span class="ix-state {{ $stateClass }}"><i></i>{{ $statuses[$st] ?? ucfirst($st) }}</span></td>
          <td class="ix-c-actions">
            <div class="ix-actions">
              <a class="ix-act" href="{{ route('routes.show', $r) }}" title="Open route"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></a>
              <form method="POST" action="{{ route('routes.destroy', $r) }}" data-confirm="Delete route {{ $r->number }}?">@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete route"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20 4 17V5l5 3 6-3 5 3v12l-5 3-6-3Z"/><path d="M9 8v12M15 5v12"/></svg></span><strong>{{ $hasFilters ? 'No routes match these filters' : 'No routes yet' }}</strong><p>{{ $hasFilters ? 'Try a different search or clear the active filters.' : 'Plan a route from issued challans to start dispatching.' }}</p><div class="ix-empty-actions">@if($hasFilters)<a class="btn sm sec" href="{{ route('routes.index') }}">Clear filters</a>@else<a class="btn sm" href="{{ route('routes.create') }}">+ Plan route</a>@endif</div></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $routes->links() }}
</div>
@endsection
