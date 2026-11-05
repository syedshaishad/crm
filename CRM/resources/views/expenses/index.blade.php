@extends('layouts.app')
@section('title','Expenses')
@section('content')
@php
  $statusVal = $status ?? '';
  $hasFilters = ($q ?? '') !== '' || ($category ?? '') !== '' || (string) ($project_id ?? '') !== '' || $statusVal !== '';
  $statusUrl = fn ($s) => route('expenses.index', array_merge(request()->except(['page', 'status']), $s ? ['status' => $s] : []));
  $labels = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
  $stateClasses = ['pending' => 'is-amber', 'approved' => 'is-green', 'rejected' => 'is-red'];
  $canApprove = auth()->user()->isAdmin() || auth()->user()->isManager();
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg>
    </span>
    <div>
      <h1>Expenses</h1>
      <div class="crumb">{{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('expense', $stats['total']) }} · {{ money($stats['approved']) }} approved · {{ $stats['pending'] }} awaiting approval</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('expenses.recurring') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>
      Recurring
    </a>
    <a class="btn sm" href="{{ route('expenses.create') }}">+ New expense</a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric"><span class="ix-metric-lbl">All expenses</span><b class="ix-metric-val">{{ $stats['total'] }}</b><span class="ix-metric-sub">{{ $stats['approved_count'] }} approved · {{ $stats['rejected'] }} rejected</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Pending approval</span><b class="ix-metric-val">{{ $stats['pending'] }}</b><span class="ix-metric-sub">waiting on a manager</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Approved spend</span><b class="ix-metric-val is-money">{{ money($stats['approved']) }}</b><span class="ix-metric-sub">across all time</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">{{ now()->format('F') }}</span><b class="ix-metric-val is-money">{{ money($stats['month']) }}</b><span class="ix-metric-sub">spent this month</span></div>
</div>

<div class="card ix-card" data-ajax-region="expenses-list">
  <div class="card-head ix-card-head">
    <div><h3>Expense log</h3><span class="hint">{{ $expenses->total() }} {{ \Illuminate\Support\Str::plural('expense', $expenses->total()) }}{{ $hasFilters ? ' matching filters' : ' in this workspace' }}</span></div>
  </div>

  <div class="ix-statuses noprint">
    <a class="ix-chip {{ $statusVal === '' ? 'on' : '' }}" href="{{ $statusUrl('') }}"><i style="background:var(--dc-ink2)"></i>All <b>{{ $stats['total'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'pending' ? 'on' : '' }}" href="{{ $statusUrl('pending') }}"><i style="background:var(--dc-amber)"></i>Pending <b>{{ $stats['pending'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'approved' ? 'on' : '' }}" href="{{ $statusUrl('approved') }}"><i style="background:var(--dc-green)"></i>Approved <b>{{ $stats['approved_count'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'rejected' ? 'on' : '' }}" href="{{ $statusUrl('rejected') }}"><i style="background:var(--dc-red)"></i>Rejected <b>{{ $stats['rejected'] }}</b></a>
  </div>

  <form method="GET" data-ajax-filter data-region="expenses-list" class="ix-filters noprint">
    <label class="ix-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search title, vendor or notes…" autocomplete="off">
    </label>
    <select class="in ix-filter" name="category">
      <option value="">All categories</option>
      @foreach($categories as $c)
        <option {{ ($category ?? '') === $c ? 'selected' : '' }}>{{ $c }}</option>
      @endforeach
    </select>
    <select class="in ix-filter" name="project_id">
      <option value="">All projects</option>
      @foreach($projects as $p)
        <option value="{{ $p->id }}" {{ (string) ($project_id ?? '') === (string) $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
      @endforeach
    </select>
    <input type="hidden" name="status" value="{{ $statusVal }}">
    <button class="btn sec sm ix-filter-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filter</button>
    @if($hasFilters)<a class="btn sm sec ix-clear-btn" href="{{ route('expenses.index') }}">Clear</a>@endif
  </form>

  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table ex-table">
      <thead>
        <tr><th class="ix-c-main">Expense</th><th class="ix-c-project">Project</th><th class="ix-c-category">Category</th><th class="ix-c-status">Status</th><th class="ix-c-value">Amount</th><th class="ix-c-actions" aria-label="Actions"></th></tr>
      </thead>
      <tbody>
      @forelse($expenses as $e)
        @php
          $st = $e->status ?: 'approved';
          $sub = array_filter([$e->number, $e->spent_on ? d($e->spent_on, 'j M Y') : null, $e->vendor]);
        @endphp
        <tr>
          <td class="ix-c-main">
            <div class="ix-main">
              <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg></span>
              <div class="ix-main-txt">
                <a class="ix-name" href="{{ route('expenses.show', $e) }}">{{ $e->title }}</a>
                <span class="ix-sub">{{ implode(' · ', $sub) }}</span>
              </div>
            </div>
          </td>
          <td class="ix-c-project">
            <div class="ix-main-txt">
              @if($e->project)
                <a class="ix-client" href="{{ route('projects.show', $e->project) }}">{{ $e->project->name }}</a>
                <span class="ix-sub">linked project</span>
              @else
                <span class="ix-client">General</span>
                <span class="ix-sub">no project</span>
              @endif
            </div>
          </td>
          <td class="ix-c-category"><span class="ix-tag">{{ $e->category ?: 'general' }}</span></td>
          <td class="ix-c-status"><span class="ix-state {{ $stateClasses[$st] ?? 'is-grey' }}"><i></i>{{ $labels[$st] ?? ucfirst($st) }}</span></td>
          <td class="ix-c-value"><span class="ix-money">{{ money($e->amount) }}</span></td>
          <td class="ix-c-actions">
            <div class="ix-actions">
              <a class="ix-act" href="{{ route('expenses.show', $e) }}" title="View expense"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></a>
              @if($e->receipt_path)<a class="ix-act" href="{{ asset('storage/'.$e->receipt_path) }}" target="_blank" rel="noopener" title="View receipt"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6.5"/><path d="m21 3-9 9M15 3h6v6"/></svg></a>@endif
              @if($st === 'pending' && $canApprove)
                <form method="POST" action="{{ route('expenses.approve', $e) }}" data-confirm="Approve {{ money($e->amount) }} for {{ $e->title }}?">@csrf<button class="ix-act" title="Approve expense"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg></button></form>
              @endif
              <a class="ix-act" href="{{ route('expenses.edit', $e) }}" title="Edit expense"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
              <form method="POST" action="{{ route('expenses.destroy', $e) }}" data-confirm="Delete {{ $e->title }}?">@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete expense"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg></span><strong>{{ $hasFilters ? 'No expenses match these filters' : 'No expenses logged yet' }}</strong><p>{{ $hasFilters ? 'Try a different search, category or project.' : 'Log your first expense to track spend by project and category.' }}</p><div class="ix-empty-actions">@if($hasFilters)<a class="btn sm sec" href="{{ route('expenses.index') }}">Clear filters</a>@else<a class="btn sm" href="{{ route('expenses.create') }}">+ New expense</a>@endif</div></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $expenses->links() }}
</div>
@endsection
