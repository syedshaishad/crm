@extends('layouts.app')
@section('title','Credit notes')
@section('content')
@php
  $statusVal = $status ?? '';
  $hasFilters = ($q ?? '') !== '' || $statusVal !== '';
  $statusUrl = fn ($s) => route('credits.index', array_merge(request()->except(['page', 'status']), $s ? ['status' => $s] : []));
  $appliedCount = max(0, $stats['total'] - $stats['open']);
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 6.5c0-2-2.2-3-5-3s-5 1-5 3 2.2 2.6 5 3.2 5 1.3 5 3.3-2.2 3-5 3-5-1-5-3"/></svg>
    </span>
    <div>
      <h1>Credit notes</h1>
      <div class="crumb">{{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('note', $stats['total']) }} · {{ money($stats['issued']) }} issued · {{ money($stats['open_value']) }} still open</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('invoices.index') }}">Invoices</a>
    <a class="btn sm" href="{{ route('credits.create') }}">+ New credit note</a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric"><span class="ix-metric-lbl">All notes</span><b class="ix-metric-val">{{ $stats['total'] }}</b><span class="ix-metric-sub">{{ $stats['open'] }} open · {{ $appliedCount }} applied</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Issued</span><b class="ix-metric-val is-money">{{ money($stats['issued']) }}</b><span class="ix-metric-sub">total credit raised</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Applied</span><b class="ix-metric-val is-money">{{ money($stats['applied']) }}</b><span class="ix-metric-sub">settled against invoices</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Open credit</span><b class="ix-metric-val is-money">{{ money($stats['open_value']) }}</b><span class="ix-metric-sub">{{ $stats['open'] }} {{ \Illuminate\Support\Str::plural('note', $stats['open']) }} to apply</span></div>
</div>

<div class="card ix-card" data-ajax-region="credits-list">
  <div class="card-head ix-card-head">
    <div><h3>Credit register</h3><span class="hint">{{ $notes->total() }} {{ \Illuminate\Support\Str::plural('credit note', $notes->total()) }}{{ $hasFilters ? ' matching filters' : ' in this workspace' }}</span></div>
  </div>

  <div class="ix-statuses noprint">
    <a class="ix-chip {{ $statusVal === '' ? 'on' : '' }}" href="{{ $statusUrl('') }}"><i style="background:var(--dc-ink2)"></i>All <b>{{ $stats['total'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'open' ? 'on' : '' }}" href="{{ $statusUrl('open') }}"><i style="background:var(--dc-amber)"></i>Open <b>{{ $stats['open'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'applied' ? 'on' : '' }}" href="{{ $statusUrl('applied') }}"><i style="background:var(--dc-green)"></i>Applied <b>{{ $appliedCount }}</b></a>
  </div>

  <form method="GET" data-ajax-filter data-region="credits-list" class="ix-filters noprint">
    <label class="ix-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search note number or client…" autocomplete="off">
    </label>
    <select class="in ix-filter" name="status">
      <option value="">All statuses</option>
      <option value="open" {{ $statusVal === 'open' ? 'selected' : '' }}>Open</option>
      <option value="applied" {{ $statusVal === 'applied' ? 'selected' : '' }}>Applied</option>
    </select>
    <button class="btn sec sm ix-filter-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filter</button>
    @if($hasFilters)<a class="btn sm sec ix-clear-btn" href="{{ route('credits.index') }}">Clear</a>@endif
  </form>

  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table cn-table">
      <thead>
        <tr><th class="ix-c-main">Credit note</th><th class="ix-c-client">Client</th><th class="ix-c-invoice">Invoice</th><th class="ix-c-status">Status</th><th class="ix-c-value">Amount</th><th class="ix-c-actions" aria-label="Actions"></th></tr>
      </thead>
      <tbody>
      @forelse($notes as $n)
        @php
          $st = $n->status ?: 'open';
          $client = $n->client;
          $clientName = $client?->company ?: ($client?->name ?: 'No client');
          $contact = $client?->company ? $client?->name : ($client?->phone ?: $client?->email);
          $sub = array_filter([$n->date ? d($n->date, 'j M Y') : null, $n->reason]);
        @endphp
        <tr>
          <td class="ix-c-main">
            <div class="ix-main">
              <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 6.5c0-2-2.2-3-5-3s-5 1-5 3 2.2 2.6 5 3.2 5 1.3 5 3.3-2.2 3-5 3-5-1-5-3"/></svg></span>
              <div class="ix-main-txt">
                <a class="ix-name" href="{{ route('credits.show', $n) }}">{{ $n->number }}</a>
                <span class="ix-sub">{{ implode(' · ', $sub) }}</span>
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
              @if($n->invoice)
                <a class="ix-name" href="{{ route('invoices.show', $n->invoice) }}">{{ $n->invoice->number }}</a>
                <span class="ix-sub">against invoice</span>
              @else
                <span class="ix-client">Unlinked</span>
                <span class="ix-sub">apply to an invoice</span>
              @endif
            </div>
          </td>
          <td class="ix-c-status"><span class="ix-state {{ $st === 'applied' ? 'is-green' : 'is-amber' }}"><i></i>{{ ucfirst($st) }}</span></td>
          <td class="ix-c-value">
            <div class="ix-stack-end">
              <span class="ix-money">{{ money($n->amount) }}</span>
              <span class="ix-sub">{{ money($n->applied_amount) }} applied</span>
            </div>
          </td>
          <td class="ix-c-actions">
            <div class="ix-actions">
              <a class="ix-act" href="{{ route('credits.show', $n) }}" title="View credit note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></a>
              <form method="POST" action="{{ route('credits.destroy', $n) }}" data-confirm="Delete credit note {{ $n->number }}?">@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete credit note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 6.5c0-2-2.2-3-5-3s-5 1-5 3 2.2 2.6 5 3.2 5 1.3 5 3.3-2.2 3-5 3-5-1-5-3"/></svg></span><strong>{{ $hasFilters ? 'No credit notes match these filters' : 'No credit notes yet' }}</strong><p>{{ $hasFilters ? 'Try a different search or clear the active filters.' : 'Raise a credit note for refunds, rate corrections or over-billing.' }}</p><div class="ix-empty-actions">@if($hasFilters)<a class="btn sm sec" href="{{ route('credits.index') }}">Clear filters</a>@else<a class="btn sm" href="{{ route('credits.create') }}">+ New credit note</a>@endif</div></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $notes->links() }}
</div>
@endsection
