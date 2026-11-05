@extends('layouts.app')
@section('title','Cheques')
@section('content')
@php
  $statuses = \Modules\Cheques\PdcCheque::statuses();
  $statusVal = $status ?? '';
  $hasFilters = ($q ?? '') !== '' || $statusVal !== '';
  $statusUrl = fn ($s) => route('cheques.index', array_merge(request()->except(['page', 'status']), $s ? ['status' => $s] : []));
  $stateClasses = ['held' => 'is-amber', 'deposited' => 'is-blue', 'cleared' => 'is-green', 'bounced' => 'is-red', 'cancelled' => 'is-grey'];
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/></svg>
    </span>
    <div>
      <h1>Cheques</h1>
      <div class="crumb">{{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('cheque', $stats['total']) }} · {{ money($stats['held']) }} held · {{ $stats['due'] }} ready to deposit</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm" href="{{ route('cheques.create') }}">+ New cheque</a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric"><span class="ix-metric-lbl">Held value</span><b class="ix-metric-val is-money">{{ money($stats['held']) }}</b><span class="ix-metric-sub">{{ $stats['held_count'] }} {{ \Illuminate\Support\Str::plural('cheque', $stats['held_count']) }} in the drawer</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Ready to deposit</span><b class="ix-metric-val">{{ $stats['due'] }}</b><span class="ix-metric-sub">{{ money($stats['due_value']) }} due now</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Cleared</span><b class="ix-metric-val">{{ $stats['cleared'] }}</b><span class="ix-metric-sub">{{ money($stats['cleared_value']) }} banked</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Bounced</span><b class="ix-metric-val">{{ $stats['bounced'] }}</b><span class="ix-metric-sub">needs follow-up</span></div>
</div>

<div class="card ix-card" data-ajax-region="cheque-list">
  <div class="card-head ix-card-head">
    <div><h3>Post-dated cheques</h3><span class="hint">{{ $cheques->total() }} {{ \Illuminate\Support\Str::plural('cheque', $cheques->total()) }}{{ $hasFilters ? ' matching filters' : ' on record' }}</span></div>
  </div>

  <div class="ix-statuses noprint">
    <a class="ix-chip {{ $statusVal === '' ? 'on' : '' }}" href="{{ $statusUrl('') }}"><i style="background:var(--dc-ink2)"></i>All <b>{{ $stats['total'] }}</b></a>
    @foreach($statuses as $sk => $sl)
      <a class="ix-chip {{ $statusVal === $sk ? 'on' : '' }}" href="{{ $statusUrl($sk) }}"><i style="background:var(--dc-{{ ['held' => 'amber', 'deposited' => 'blue', 'cleared' => 'green', 'bounced' => 'red', 'cancelled' => 'muted'][$sk] }})"></i>{{ $sl }} <b>{{ $stats['counts'][$sk] ?? 0 }}</b></a>
    @endforeach
  </div>

  <form method="GET" data-ajax-filter data-region="cheque-list" class="ix-filters noprint">
    <label class="ix-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search number, cheque, bank or client…" autocomplete="off">
    </label>
    <select class="in ix-filter" name="status">
      <option value="">All states</option>
      @foreach($statuses as $sk => $sl)
        <option value="{{ $sk }}" {{ $statusVal === $sk ? 'selected' : '' }}>{{ $sl }}</option>
      @endforeach
    </select>
    <button class="btn sec sm ix-filter-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filter</button>
    @if($hasFilters)<a class="btn sm sec ix-clear-btn" href="{{ route('cheques.index') }}">Clear</a>@endif
  </form>

  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table cq-table">
      <thead>
        <tr><th class="ix-c-main">Cheque</th><th class="ix-c-client">Client</th><th class="ix-c-bank">Bank</th><th class="ix-c-date">Date</th><th class="ix-c-status">Status</th><th class="ix-c-value">Amount</th><th class="ix-c-actions" aria-label="Actions"></th></tr>
      </thead>
      <tbody>
      @forelse($cheques as $c)
        @php
          $st = $c->status ?: 'held';
          $client = $c->client;
          $clientName = $client?->company ?: ($client?->name ?: 'No client');
          $contact = $client?->company ? $client?->name : ($client?->phone ?: $client?->email);
        @endphp
        <tr>
          <td class="ix-c-main">
            <div class="ix-main">
              <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/></svg></span>
              <div class="ix-main-txt">
                <a class="ix-name" href="{{ route('cheques.show', $c) }}">{{ $c->number }}</a>
                <span class="ix-sub">{{ $c->cheque_no ? 'cheque '.$c->cheque_no : 'no cheque number' }}</span>
              </div>
            </div>
          </td>
          <td class="ix-c-client">
            <div class="ix-main-txt">
              <span class="ix-client">{{ $clientName }}</span>
              @if($c->invoice)
                <a class="ix-sub" href="{{ route('invoices.show', $c->invoice) }}">{{ $c->invoice->number }}</a>
              @elseif($contact)
                <span class="ix-sub">{{ $contact }}</span>
              @endif
            </div>
          </td>
          <td class="ix-c-bank">
            <div class="ix-main-txt">
              <span class="ix-client">{{ $c->bank ?: 'Unknown bank' }}</span>
              <span class="ix-sub">{{ $c->invoice ? 'against invoice' : 'no invoice linked' }}</span>
            </div>
          </td>
          <td class="ix-c-date">
            <div class="ix-main-txt">
              <span class="ix-money">{{ $c->cheque_date ? d($c->cheque_date, 'j M Y') : '—' }}</span>
              @if($c->isDue())<span class="ix-sub is-warn">deposit now</span>@endif
            </div>
          </td>
          <td class="ix-c-status"><span class="ix-state {{ $stateClasses[$st] ?? 'is-grey' }}"><i></i>{{ $statuses[$st] ?? ucfirst($st) }}</span></td>
          <td class="ix-c-value"><span class="ix-money">{{ money($c->amount) }}</span></td>
          <td class="ix-c-actions">
            <div class="ix-actions">
              <a class="ix-act" href="{{ route('cheques.show', $c) }}" title="Open cheque"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></a>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/></svg></span><strong>{{ $hasFilters ? 'No cheques match these filters' : 'No cheques on record' }}</strong><p>{{ $hasFilters ? 'Try a different search, state or clear the active filters.' : 'Record a post-dated cheque to track it until it clears.' }}</p><div class="ix-empty-actions">@if($hasFilters)<a class="btn sm sec" href="{{ route('cheques.index') }}">Clear filters</a>@else<a class="btn sm" href="{{ route('cheques.create') }}">+ New cheque</a>@endif</div></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $cheques->links() }}
</div>
@endsection
