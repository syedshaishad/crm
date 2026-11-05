@extends('layouts.app')
@section('title','Invoices')
@section('content')
@php
  $statusVal = $status ?? '';
  $hasFilters = ($q ?? '') !== '' || $statusVal !== '';
  $statusUrl = fn ($s) => route('invoices.index', array_merge(request()->except(['page', 'status']), $s ? ['status' => $s] : []));
  $labels = ['draft' => 'Draft', 'sent' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'];
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h7M9 17h7"/></svg>
    </span>
    <div>
      <h1>Invoices</h1>
      <div class="crumb">{{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('invoice', $stats['total']) }} · {{ money($stats['outstanding']) }} outstanding · {{ $stats['overdue'] }} overdue</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('invoices.recurring') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>
      Recurring
    </a>
    <a class="btn sm" href="{{ route('invoices.create') }}">+ New invoice</a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric"><span class="ix-metric-lbl">All invoices</span><b class="ix-metric-val">{{ $stats['total'] }}</b><span class="ix-metric-sub">{{ $stats['draft'] }} draft · {{ $stats['cancelled'] }} cancelled</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Invoiced</span><b class="ix-metric-val is-money">{{ money($stats['invoiced']) }}</b><span class="ix-metric-sub">billed and sent</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Outstanding</span><b class="ix-metric-val is-money">{{ money($stats['outstanding']) }}</b><span class="ix-metric-sub">{{ $stats['overdue'] }} overdue · {{ $stats['partial'] }} part paid</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Collected</span><b class="ix-metric-val is-money">{{ money($stats['collected']) }}</b><span class="ix-metric-sub">{{ $stats['paid'] }} paid in full</span></div>
</div>

<div class="card ix-card" data-ajax-region="invoices-list">
  <div class="card-head ix-card-head">
    <div><h3>Invoice register</h3><span class="hint">{{ $invoices->total() }} {{ \Illuminate\Support\Str::plural('invoice', $invoices->total()) }}{{ $hasFilters ? ' matching filters' : ' in this workspace' }}</span></div>
  </div>

  <div class="ix-statuses noprint">
    <a class="ix-chip {{ $statusVal === '' ? 'on' : '' }}" href="{{ $statusUrl('') }}"><i style="background:var(--dc-ink2)"></i>All <b>{{ $stats['total'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'draft' ? 'on' : '' }}" href="{{ $statusUrl('draft') }}"><i style="background:var(--dc-blue)"></i>Draft <b>{{ $stats['draft'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'sent' ? 'on' : '' }}" href="{{ $statusUrl('sent') }}"><i style="background:var(--dc-amber)"></i>Unpaid <b>{{ $stats['sent'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'partial' ? 'on' : '' }}" href="{{ $statusUrl('partial') }}"><i style="background:var(--dc-amber)"></i>Partial <b>{{ $stats['partial'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'overdue' ? 'on' : '' }}" href="{{ $statusUrl('overdue') }}"><i style="background:var(--dc-red)"></i>Overdue <b>{{ $stats['overdue'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'paid' ? 'on' : '' }}" href="{{ $statusUrl('paid') }}"><i style="background:var(--dc-green)"></i>Paid <b>{{ $stats['paid'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'cancelled' ? 'on' : '' }}" href="{{ $statusUrl('cancelled') }}"><i style="background:var(--dc-muted)"></i>Cancelled <b>{{ $stats['cancelled'] }}</b></a>
  </div>

  <form method="GET" data-ajax-filter data-region="invoices-list" class="ix-filters noprint">
    <label class="ix-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search invoice number or client…" autocomplete="off">
    </label>
    <select class="in ix-filter" name="status">
      <option value="">All statuses</option>
      @foreach($labels as $st => $lbl)
        <option value="{{ $st }}" {{ $statusVal === $st ? 'selected' : '' }}>{{ $lbl }}</option>
      @endforeach
    </select>
    <button class="btn sec sm ix-filter-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filter</button>
    @if($hasFilters)<a class="btn sm sec ix-clear-btn" href="{{ route('invoices.index') }}">Clear</a>@endif
  </form>

  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table in-table">
      <thead>
        <tr><th class="ix-c-main">Invoice</th><th class="ix-c-client">Client</th><th class="ix-c-status">Status</th>@if(auth()->user()->canManageInvoices())<th class="ix-c-assigned">Assigned</th>@endif<th class="ix-c-total">Total</th><th class="ix-c-balance">Balance</th><th class="ix-c-actions" aria-label="Actions"></th></tr>
      </thead>
      <tbody>
      @forelse($invoices as $i)
        @php
          $st = $i->status ?? 'draft';
          $stateClass = ['draft' => 'is-blue', 'sent' => 'is-blue', 'partial' => 'is-amber', 'paid' => 'is-green', 'overdue' => 'is-red', 'cancelled' => 'is-grey'][$st] ?? 'is-grey';
          $balance = $i->balance();
          $client = $i->client;
          $clientName = $client?->company ?: ($client?->name ?: 'No client');
          $contact = $client?->company ? $client?->name : ($client?->phone ?: $client?->email);
          $isOverdue = $st === 'overdue' || ($balance > 0 && $i->due_date && $i->due_date->isPast() && ! in_array($st, ['draft', 'cancelled'], true));
          $balanceNote = $st === 'cancelled' ? 'cancelled' : ($balance <= 0 ? 'settled' : ($isOverdue ? 'overdue · due '.d($i->due_date, 'j M') : ($i->due_date ? 'due '.d($i->due_date, 'j M') : 'no due date')));
        @endphp
        <tr>
          <td class="ix-c-main">
            <div class="ix-main">
              <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h7M9 17h7"/></svg></span>
              <div class="ix-main-txt">
                <a class="ix-name" href="{{ route('invoices.show', $i) }}">{{ $i->number }}</a>
                <span class="ix-sub">{{ $i->issue_date ? 'issued '.d($i->issue_date, 'j M Y') : 'no issue date' }}</span>
              </div>
            </div>
          </td>
          <td class="ix-c-client">
            <div class="ix-main-txt">
              <span class="ix-client">{{ $clientName }}</span>
              @if($contact)<span class="ix-sub">{{ $contact }}</span>@endif
            </div>
          </td>
          <td class="ix-c-status"><span class="ix-state {{ $stateClass }}"><i></i>{{ $labels[$st] ?? ucfirst($st) }}</span></td>
          @if(auth()->user()->canManageInvoices())
            <td class="ix-c-assigned">
              <div class="ix-main-txt">
                <span class="ix-client">{{ $i->assignee->name ?? 'Unassigned' }}</span>
                <span class="ix-sub">{{ $i->assignee ? 'owner' : 'no owner' }}</span>
              </div>
            </td>
          @endif
          <td class="ix-c-total"><span class="ix-money">{{ money($i->total) }}</span></td>
          <td class="ix-c-balance">
            <div class="ix-stack-end">
              <span class="ix-money">{{ $balance > 0 ? money($balance) : '—' }}</span>
              <span class="ix-sub {{ $isOverdue ? 'is-danger' : '' }}">{{ $balanceNote }}</span>
            </div>
          </td>
          <td class="ix-c-actions">
            <div class="ix-actions">
              <a class="ix-act" href="{{ route('invoices.show', $i) }}" title="Open invoice"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></a>
              <a class="ix-act" href="{{ route('invoices.edit', $i) }}" title="Edit invoice"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
              <form method="POST" action="{{ route('invoices.destroy', $i) }}" data-confirm="Delete invoice {{ $i->number }} ({{ money($i->total) }})? This cannot be undone.">@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete invoice"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="{{ auth()->user()->canManageInvoices() ? 7 : 6 }}"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h7M9 17h7"/></svg></span><strong>{{ $hasFilters ? 'No invoices match these filters' : 'No invoices yet' }}</strong><p>{{ $hasFilters ? 'Try a different search or clear the active filters.' : 'Raise your first invoice to start tracking what is owed.' }}</p><div class="ix-empty-actions">@if($hasFilters)<a class="btn sm sec" href="{{ route('invoices.index') }}">Clear filters</a>@else<a class="btn sm" href="{{ route('invoices.create') }}">+ New invoice</a>@endif</div></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $invoices->links() }}
</div>
@endsection
