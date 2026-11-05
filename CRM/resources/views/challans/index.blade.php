@extends('layouts.app')
@section('title','Delivery challans')
@section('content')
@php
  $statuses = \Modules\Challans\DeliveryChallan::statuses();
  $statusVal = $status ?? '';
  $hasFilters = ($q ?? '') !== '' || $statusVal !== '';
  $statusUrl = fn ($s) => route('challans.index', array_merge(request()->except(['page', 'status']), $s ? ['status' => $s] : []));
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 17V5H3v12h11Z"/><path d="M14 8h4l3 4v5h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>
    </span>
    <div>
      <h1>Delivery challans</h1>
      <div class="crumb">{{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('challan', $stats['total']) }} · {{ $stats['issued'] }} issued · {{ money($stats['issued_value']) }} dispatched</div>
    </div>
  </div>
  <div class="ix-head-actions noprint"><a class="btn sm" href="{{ route('challans.create') }}">+ New challan</a></div>
</div>

<div class="ix-metrics">
  <div class="ix-metric"><span class="ix-metric-lbl">All challans</span><b class="ix-metric-val">{{ $stats['total'] }}</b><span class="ix-metric-sub">{{ $stats['draft'] }} draft · {{ $stats['cancelled'] }} cancelled</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Issued</span><b class="ix-metric-val">{{ $stats['issued'] }}</b><span class="ix-metric-sub">ready for dispatch</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Draft</span><b class="ix-metric-val">{{ $stats['draft'] }}</b><span class="ix-metric-sub">awaiting issue</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Issued value</span><b class="ix-metric-val is-money">{{ money($stats['issued_value']) }}</b><span class="ix-metric-sub">across issued challans</span></div>
</div>

<div class="card ix-card" data-ajax-region="dc-list">
  <div class="card-head ix-card-head">
    <div><h3>Challans</h3><span class="hint">{{ $challans->total() }} {{ \Illuminate\Support\Str::plural('challan', $challans->total()) }}{{ $hasFilters ? ' matching filters' : ' in this workspace' }}</span></div>
  </div>

  <div class="ix-statuses noprint">
    <a class="ix-chip {{ $statusVal === '' ? 'on' : '' }}" href="{{ $statusUrl('') }}"><i style="background:var(--dc-ink2)"></i>All <b>{{ $stats['total'] }}</b></a>
    @foreach($statuses as $st => $label)
      @php $dot = ['draft' => 'var(--dc-blue)', 'issued' => 'var(--dc-green)', 'cancelled' => 'var(--dc-muted)'][$st] ?? 'var(--dc-muted)'; @endphp
      <a class="ix-chip {{ $statusVal === $st ? 'on' : '' }}" href="{{ $statusUrl($st) }}"><i style="background:{{ $dot }}"></i>{{ $label }} <b>{{ $stats[$st] ?? 0 }}</b></a>
    @endforeach
  </div>

  <form method="GET" data-ajax-filter data-region="dc-list" class="ix-filters noprint">
    <label class="ix-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search challan number or client…" autocomplete="off">
    </label>
    <select class="in ix-filter" name="status">
      <option value="">All statuses</option>
      @foreach($statuses as $st => $label)
        <option value="{{ $st }}" {{ $statusVal === $st ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
    <button class="btn sec sm ix-filter-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filter</button>
    @if($hasFilters)<a class="btn sm sec ix-clear-btn" href="{{ route('challans.index') }}">Clear</a>@endif
  </form>

  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table ch-table">
      <thead>
        <tr><th class="ix-c-main">Challan</th><th class="ix-c-client">Client</th><th class="ix-c-qty">Qty</th><th class="ix-c-value">Value</th><th class="ix-c-status">Status</th><th class="ix-c-actions"></th></tr>
      </thead>
      <tbody>
      @forelse($challans as $c)
        @php
          $st = $c->status ?? 'draft';
          $stateClass = ['draft' => 'is-blue', 'issued' => 'is-green', 'cancelled' => 'is-grey'][$st] ?? 'is-grey';
          $clientName = $c->client?->company ?: $c->client?->name ?: 'No client';
          $contact = $c->client?->phone ?: $c->client?->email;
        @endphp
        <tr>
          <td class="ix-c-main">
            <div class="ix-main">
              <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 17V5H3v12h11Z"/><path d="M14 8h4l3 4v5h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg></span>
              <div class="ix-main-txt">
                <a class="ix-name" href="{{ route('challans.show', $c) }}">{{ $c->number }}</a>
                <span class="ix-sub">{{ $c->challan_date ? d($c->challan_date) : 'No date' }}@if($c->invoice) · {{ $c->invoice->number }}@endif</span>
              </div>
            </div>
          </td>
          <td class="ix-c-client">
            <div class="ix-main-txt">
              <span class="ix-client">{{ $clientName }}</span>
              @if($contact)<span class="ix-sub">{{ $contact }}</span>@endif
            </div>
          </td>
          <td class="ix-c-qty">
            <div class="ix-qty">
              <span class="ix-count">{{ rtrim(rtrim(number_format((float) $c->total_qty, 2), '0'), '.') ?: '0' }}</span>
              <span class="ix-sub">{{ $c->items_count }} {{ \Illuminate\Support\Str::plural('line', $c->items_count) }}</span>
            </div>
          </td>
          <td class="ix-c-value"><span class="ix-money">{{ money($c->subtotal) }}</span></td>
          <td class="ix-c-status"><span class="ix-state {{ $stateClass }}"><i></i>{{ $statuses[$st] ?? ucfirst($st) }}</span></td>
          <td class="ix-c-actions">
            <div class="ix-actions">
              <a class="ix-act" href="{{ route('challans.show', $c) }}" title="Open challan"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></a>
              @if($st === 'draft')<a class="ix-act" href="{{ route('challans.edit', $c) }}" title="Edit draft"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>@endif
              <form method="POST" action="{{ route('challans.destroy', $c) }}" data-confirm="Delete challan {{ $c->number }}?">@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete challan"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 17V5H3v12h11Z"/><path d="M14 8h4l3 4v5h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg></span><strong>{{ $hasFilters ? 'No challans match these filters' : 'No challans yet' }}</strong><p>{{ $hasFilters ? 'Try a different search or clear the active filters.' : 'Create your first challan when goods are ready to leave.' }}</p><div class="ix-empty-actions">@if($hasFilters)<a class="btn sm sec" href="{{ route('challans.index') }}">Clear filters</a>@else<a class="btn sm" href="{{ route('challans.create') }}">+ New challan</a>@endif</div></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $challans->links() }}
</div>
@endsection
