@extends('layouts.app')
@section('title','Payments')
@section('content')
@php
  $methods = setting_lines('payments.methods', ['cash', 'bank', 'upi', 'razorpay', 'other']);
  $methodVal = $method ?? '';
  $hasFilters = ($q ?? '') !== '' || $methodVal !== '';
  $methodUrl = fn ($m) => route('payments.index', array_merge(request()->except(['page', 'method']), $m ? ['method' => $m] : []));
  $monthName = now()->format('F');
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 12h.01M18 12h.01"/></svg>
    </span>
    <div>
      <h1>Payments</h1>
      <div class="crumb">{{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('receipt', $stats['total']) }} · {{ money($stats['received']) }} received · {{ money($stats['month']) }} in {{ $monthName }}</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('payments.split') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"/><path d="M21 3 3 21"/><path d="M21 16v5h-5"/><path d="m16 16 5 5"/></svg>
      Split receipt
    </a>
    <a class="btn sm" href="{{ route('payments.create') }}">+ Record payment</a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric"><span class="ix-metric-lbl">All receipts</span><b class="ix-metric-val">{{ $stats['total'] }}</b><span class="ix-metric-sub">recorded against invoices</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Received</span><b class="ix-metric-val is-money">{{ money($stats['received']) }}</b><span class="ix-metric-sub">all time</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">{{ $monthName }}</span><b class="ix-metric-val is-money">{{ money($stats['month']) }}</b><span class="ix-metric-sub">collected this month</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Average receipt</span><b class="ix-metric-val is-money">{{ money($stats['average']) }}</b><span class="ix-metric-sub">per receipt</span></div>
</div>

<div class="card ix-card" data-ajax-region="payments-list">
  <div class="card-head ix-card-head">
    <div><h3>Receipt register</h3><span class="hint">{{ $payments->total() }} {{ \Illuminate\Support\Str::plural('receipt', $payments->total()) }}{{ $hasFilters ? ' matching filters' : ' in this workspace' }}</span></div>
  </div>

  <div class="ix-statuses noprint">
    <a class="ix-chip {{ $methodVal === '' ? 'on' : '' }}" href="{{ $methodUrl('') }}"><i style="background:var(--dc-ink2)"></i>All <b>{{ $stats['total'] }}</b></a>
    @foreach($methods as $m)
      <a class="ix-chip {{ $methodVal === $m ? 'on' : '' }}" href="{{ $methodUrl($m) }}"><i style="background:var(--dc-accent)"></i>{{ ucfirst($m) }} <b>{{ $stats['methods'][$m] ?? 0 }}</b></a>
    @endforeach
  </div>

  <form method="GET" data-ajax-filter data-region="payments-list" class="ix-filters noprint">
    <label class="ix-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search receipt number, reference or client…" autocomplete="off">
    </label>
    <select class="in ix-filter" name="method">
      <option value="">All methods</option>
      @foreach($methods as $m)
        <option value="{{ $m }}" {{ $methodVal === $m ? 'selected' : '' }}>{{ ucfirst($m) }}</option>
      @endforeach
    </select>
    <button class="btn sec sm ix-filter-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filter</button>
    @if($hasFilters)<a class="btn sm sec ix-clear-btn" href="{{ route('payments.index') }}">Clear</a>@endif
  </form>

  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table py-table">
      <thead>
        <tr><th class="ix-c-main">Receipt</th><th class="ix-c-client">Client</th><th class="ix-c-invoice">Invoice</th><th class="ix-c-method">Method</th><th class="ix-c-value">Amount</th><th class="ix-c-actions" aria-label="Actions"></th></tr>
      </thead>
      <tbody>
      @forelse($payments as $p)
        @php
          $client = $p->client;
          $clientName = $client?->company ?: ($client?->name ?: 'No client');
          $contact = $client?->company ? $client?->name : ($client?->phone ?: $client?->email);
        @endphp
        <tr>
          <td class="ix-c-main">
            <div class="ix-main">
              <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 12h.01M18 12h.01"/></svg></span>
              <div class="ix-main-txt">
                <a class="ix-name" href="{{ route('payments.show', $p) }}">{{ $p->number ?: 'Receipt #'.$p->id }}</a>
                <span class="ix-sub">{{ $p->paid_on ? d($p->paid_on, 'j M Y') : 'no date' }}</span>
              </div>
            </div>
          </td>
          <td class="ix-c-client">
            <div class="ix-main-txt">
              <span class="ix-client">{{ $clientName }}</span>
              @if($contact)<span class="ix-sub">{{ $contact }}</span>@endif
            </div>
          </td>
          <td class="ix-c-invoice">
            <div class="ix-main-txt">
              @if($p->invoice)
                <a class="ix-name" href="{{ route('invoices.show', $p->invoice) }}">{{ $p->invoice->number }}</a>
                <span class="ix-sub">against invoice</span>
              @else
                <span class="ix-client">Advance</span>
                <span class="ix-sub">not linked yet</span>
              @endif
            </div>
          </td>
          <td class="ix-c-method">
            <div class="ix-main-txt">
              <span class="ix-tag">{{ $p->method ?: 'other' }}</span>
              @if($p->reference)<span class="ix-sub">{{ $p->reference }}</span>@endif
            </div>
          </td>
          <td class="ix-c-value"><span class="ix-money">{{ money($p->amount) }}</span></td>
          <td class="ix-c-actions">
            <div class="ix-actions">
              <a class="ix-act" href="{{ route('payments.show', $p) }}" title="View receipt"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></a>
              <form method="POST" action="{{ route('payments.destroy', $p) }}" data-confirm="Delete payment of {{ money($p->amount) }}? Invoice totals will update.">@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete payment"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 12h.01M18 12h.01"/></svg></span><strong>{{ $hasFilters ? 'No payments match these filters' : 'No payments recorded yet' }}</strong><p>{{ $hasFilters ? 'Try a different search, method or clear the active filters.' : 'Record a receipt when a client pays an invoice.' }}</p><div class="ix-empty-actions">@if($hasFilters)<a class="btn sm sec" href="{{ route('payments.index') }}">Clear filters</a>@else<a class="btn sm" href="{{ route('payments.create') }}">+ Record payment</a>@endif</div></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $payments->links() }}
</div>
@endsection
