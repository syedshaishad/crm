@extends('layouts.app')
@section('title','Purchase orders')
@section('content')
@php
  $statusVal = $status ?? '';
  $hasFilters = ($q ?? '') !== '' || $statusVal !== '';
  $statusUrl = fn ($s) => route('purchase-orders.index', array_merge(request()->except(['page', 'status']), $s ? ['status' => $s] : []));
  $labels = \Modules\Purchases\PurchaseOrder::statuses();
  $stateClasses = ['draft' => 'is-grey', 'sent' => 'is-blue', 'received' => 'is-green', 'cancelled' => 'is-red'];
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l4 4v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M14 2v5h5M8 12h8M8 16h5"/></svg>
    </span>
    <div>
      <h1>Purchase orders</h1>
      <div class="crumb">{{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('order', $stats['total']) }} · {{ money($stats['received_value']) }} received · {{ $stats['sent'] }} awaiting delivery</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm" href="{{ route('purchase-orders.create') }}">+ New purchase order</a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric"><span class="ix-metric-lbl">All orders</span><b class="ix-metric-val">{{ $stats['total'] }}</b><span class="ix-metric-sub">{{ $stats['received'] }} received · {{ $stats['cancelled'] }} cancelled</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Drafts</span><b class="ix-metric-val">{{ $stats['draft'] }}</b><span class="ix-metric-sub">{{ money($stats['draft_value']) }} not sent yet</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Awaiting delivery</span><b class="ix-metric-val">{{ $stats['sent'] }}</b><span class="ix-metric-sub">{{ money($stats['sent_value']) }} with vendors</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Received value</span><b class="ix-metric-val is-money">{{ money($stats['received_value']) }}</b><span class="ix-metric-sub">booked to expenses</span></div>
</div>

<div class="card ix-card" data-ajax-region="pos-list">
  <div class="card-head ix-card-head">
    <div><h3>Purchase order register</h3><span class="hint">{{ $orders->total() }} {{ \Illuminate\Support\Str::plural('order', $orders->total()) }}{{ $hasFilters ? ' matching filters' : ' in this workspace' }}</span></div>
  </div>

  <div class="ix-statuses noprint">
    <a class="ix-chip {{ $statusVal === '' ? 'on' : '' }}" href="{{ $statusUrl('') }}"><i style="background:var(--dc-ink2)"></i>All <b>{{ $stats['total'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'draft' ? 'on' : '' }}" href="{{ $statusUrl('draft') }}"><i style="background:var(--dc-muted)"></i>Draft <b>{{ $stats['draft'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'sent' ? 'on' : '' }}" href="{{ $statusUrl('sent') }}"><i style="background:var(--dc-blue)"></i>Sent <b>{{ $stats['sent'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'received' ? 'on' : '' }}" href="{{ $statusUrl('received') }}"><i style="background:var(--dc-green)"></i>Received <b>{{ $stats['received'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'cancelled' ? 'on' : '' }}" href="{{ $statusUrl('cancelled') }}"><i style="background:var(--dc-red)"></i>Cancelled <b>{{ $stats['cancelled'] }}</b></a>
  </div>

  <form method="GET" data-ajax-filter data-region="pos-list" class="ix-filters noprint">
    <label class="ix-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search number or vendor…" autocomplete="off">
    </label>
    <input type="hidden" name="status" value="{{ $statusVal }}">
    <button class="btn sec sm ix-filter-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Search</button>
    @if($hasFilters)<a class="btn sm sec ix-clear-btn" href="{{ route('purchase-orders.index') }}">Clear</a>@endif
  </form>

  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table po-table">
      <thead>
        <tr><th class="ix-c-main">Purchase order</th><th class="ix-c-vendor">Vendor</th><th class="ix-c-date">Expected</th><th class="ix-c-status">Status</th><th class="ix-c-value">Total</th><th class="ix-c-actions" aria-label="Actions"></th></tr>
      </thead>
      <tbody>
      @forelse($orders as $o)
        @php
          $st = $o->status ?: 'draft';
          $late = $o->expected_on && $o->expected_on->lt(today()) && in_array($st, ['draft', 'sent'], true);
          $vendorSub = $o->project?->name ?: ($o->vendor_email ?: null);
        @endphp
        <tr>
          <td class="ix-c-main">
            <div class="ix-main">
              <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l4 4v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M14 2v5h5M8 12h8M8 16h5"/></svg></span>
              <div class="ix-main-txt">
                <a class="ix-name" href="{{ route('purchase-orders.show', $o) }}">{{ $o->number }}</a>
                <span class="ix-sub">{{ $o->items_count }} {{ \Illuminate\Support\Str::plural('line', $o->items_count) }}{{ $o->issue_date ? ' · issued '.d($o->issue_date, 'j M Y') : '' }}</span>
              </div>
            </div>
          </td>
          <td class="ix-c-vendor">
            <div class="ix-main-txt">
              <span class="ix-client">{{ $o->vendor }}</span>
              @if($vendorSub)<span class="ix-sub">{{ $vendorSub }}</span>@endif
            </div>
          </td>
          <td class="ix-c-date">
            <div class="ix-main-txt">
              <span class="ix-client">{{ $o->expected_on ? d($o->expected_on, 'j M Y') : '—' }}</span>
              <span class="ix-sub {{ $late ? 'is-danger' : '' }}">{{ $o->expected_on ? ($late ? 'overdue' : 'expected') : 'no date set' }}</span>
            </div>
          </td>
          <td class="ix-c-status"><span class="ix-state {{ $stateClasses[$st] ?? 'is-grey' }}"><i></i>{{ $labels[$st] ?? ucfirst($st) }}</span></td>
          <td class="ix-c-value"><span class="ix-money">{{ money($o->total) }}</span></td>
          <td class="ix-c-actions">
            <div class="ix-actions">
              <a class="ix-act" href="{{ route('purchase-orders.show', $o) }}" title="View purchase order"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></a>
              @if($st === 'draft')
                <a class="ix-act" href="{{ route('purchase-orders.edit', $o) }}" title="Edit draft"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
              @endif
              @if($o->converted_expense_id)
                <a class="ix-act" href="{{ route('expenses.show', $o->converted_expense_id) }}" title="View booked expense"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h5"/></svg></a>
              @endif
              @if($st !== 'received')
                <form method="POST" action="{{ route('purchase-orders.destroy', $o) }}" data-confirm="Delete purchase order {{ $o->number }}?">@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete purchase order"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l4 4v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M14 2v5h5M8 12h8M8 16h5"/></svg></span><strong>{{ $hasFilters ? 'No purchase orders match these filters' : 'No purchase orders yet' }}</strong><p>{{ $hasFilters ? 'Try a different search term or status.' : 'Raise your first order to track vendor spend and book it on delivery.' }}</p><div class="ix-empty-actions">@if($hasFilters)<a class="btn sm sec" href="{{ route('purchase-orders.index') }}">Clear filters</a>@else<a class="btn sm" href="{{ route('purchase-orders.create') }}">+ New purchase order</a>@endif</div></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $orders->links() }}
</div>
@endsection
