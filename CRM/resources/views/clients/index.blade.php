@extends('layouts.app')
@section('title','Clients')
@section('content')
@php
  $total = (int) $stats['total'];
  $active = (int) $stats['active'];
  $inactive = max(0, $total - $active);
  $activePct = $total > 0 ? round($active / $total * 100) : 0;
  $statusVal = $status ?? '';
  $hasFilters = ($q ?? '') !== '' || $statusVal !== '';
  $clearUrl = route('clients.index');
  $statusUrl = function ($s) {
    return route('clients.index', array_merge(request()->except(['page', 'status']), $s ? ['status' => $s] : []));
  };
@endphp

<div class="page-head cl-head">
  <div class="cl-head-main">
    <span class="cl-head-ic" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M16 9h2a2 2 0 0 1 2 2v10M8 7h4M8 11h4M8 15h4M2 21h20"/></svg>
    </span>
    <div>
      <h1>Clients</h1>
      <div class="crumb">{{ $total }} {{ \Illuminate\Support\Str::plural('client', $total) }} · {{ $active }} active · {{ money($stats['outstanding']) }} outstanding</div>
    </div>
  </div>
  <div class="cl-head-actions noprint">
    <button class="btn sm sec" data-modal-open="import-modal">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m-4-4 4 4 4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
      Import
    </button>
    <a class="btn sm" href="{{ route('clients.create') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
      New client
    </a>
  </div>
</div>

<div class="cl-metrics">
  <div class="cl-metric">
    <span class="cl-metric-lbl">Total clients</span>
    <b class="cl-metric-val">{{ $total }}</b>
    <span class="cl-metric-sub">{{ $inactive }} inactive</span>
  </div>
  <div class="cl-metric">
    <span class="cl-metric-lbl">Active</span>
    <b class="cl-metric-val">{{ $active }}</b>
    <span class="cl-metric-sub">{{ $activePct }}% of all clients</span>
  </div>
  <div class="cl-metric">
    <span class="cl-metric-lbl">New this month</span>
    <b class="cl-metric-val">{{ $stats['new_month'] }}</b>
    <span class="cl-metric-sub">Added in {{ now()->format('F') }}</span>
  </div>
  <div class="cl-metric">
    <span class="cl-metric-lbl">Outstanding</span>
    <b class="cl-metric-val is-money">{{ money($stats['outstanding']) }}</b>
    <span class="cl-metric-sub">{{ $stats['outstanding'] > 0 ? 'Across open invoices' : 'No open balances' }}</span>
  </div>
</div>

<div class="card cl-card" data-ajax-region="clients-list">
  <div class="card-head cl-card-head">
    <div>
      <h3>Client list</h3>
      <span class="hint">{{ $clients->total() }} {{ \Illuminate\Support\Str::plural('client', $clients->total()) }}</span>
    </div>
  </div>

  <div class="cl-statuses" aria-label="Clients by status">
    <a class="cl-status-chip {{ $statusVal === '' ? 'on' : '' }}" href="{{ $statusUrl('') }}" @if($statusVal === '') aria-current="page" @endif><i style="background:var(--dc-ink)"></i>All ({{ $total }})</a>
    <a class="cl-status-chip {{ $statusVal === 'active' ? 'on' : '' }}" href="{{ $statusUrl('active') }}" @if($statusVal === 'active') aria-current="page" @endif><i style="background:var(--dc-green)"></i>Active ({{ $active }})</a>
    <a class="cl-status-chip {{ $statusVal === 'inactive' ? 'on' : '' }}" href="{{ $statusUrl('inactive') }}" @if($statusVal === 'inactive') aria-current="page" @endif><i style="background:var(--dc-muted)"></i>Inactive ({{ $inactive }})</a>
  </div>

  <form method="GET" data-ajax-filter data-region="clients-list" class="cl-filters noprint">
    <label class="cl-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg>
      <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search name, company, email, phone…" aria-label="Search clients" autocomplete="off">
    </label>
    <select class="in cl-filter" name="status" aria-label="Filter by status">
      <option value="">All statuses</option>
      <option value="active" {{ $statusVal === 'active' ? 'selected' : '' }}>Active</option>
      <option value="inactive" {{ $statusVal === 'inactive' ? 'selected' : '' }}>Inactive</option>
    </select>
    <button class="btn sec sm cl-filter-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h18l-7 8v5.2L10 20v-7z"/></svg>
      Filter
    </button>
    @if($hasFilters)<a class="btn sm sec cl-clear-btn" href="{{ $clearUrl }}">Clear</a>@endif
  </form>

  <form method="POST" action="{{ route('clients.store') }}" data-ajax-add data-region="clients-list" class="cl-quick noprint">
    @csrf
    <span class="cl-quick-lbl">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
      Quick add
    </span>
    <input class="in" name="name" required placeholder="Client name" aria-label="New client name" autocomplete="off">
    <input class="in" name="company" placeholder="Company" aria-label="New client company" autocomplete="off">
    <input class="in" name="phone" placeholder="Phone" aria-label="New client phone" autocomplete="off">
    <button class="btn sm">Add client</button>
  </form>

  <div class="bulkbar noprint" data-bulk-bar data-bulk-url="{{ route('clients.bulk') }}" data-region="clients-list" hidden>
    <span class="sel"><b data-bulk-count>0</b> selected</span>
    <select data-bulk-value name="status"><option value="">Set status…</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
    <button class="btn sm sec" data-bulk-apply="status">Apply</button>
    <span class="spacer"></span>
    <button class="btn sm danger" data-bulk-apply="delete">Delete</button>
  </div>

  <div class="tbl-scroll roomy cl-table-scroll">
      <table class="tbl cl-table">
        <thead>
          <tr>
            <th class="cl-c-check"><input type="checkbox" data-bulk-all title="Select all" aria-label="Select all clients"></th>
            <th class="cl-c-client">Client</th>
            <th class="cl-c-contact">Contact</th>
            <th class="cl-c-since">Since</th>
            <th class="cl-c-projects">Projects</th>
            <th class="cl-c-status">Status</th>
            <th class="cl-c-outstanding">Outstanding</th>
            <th class="cl-c-actions" aria-label="Actions"></th>
          </tr>
        </thead>
        <tbody>
          @forelse($clients as $c)
            @php
              $bal = max(0, ($c->billed ?? 0) - ($c->collected ?? 0));
              $isActive = ($c->status ?? 'active') === 'active';
              $initials = strtoupper(mb_substr(trim($c->company ?: $c->name), 0, 1));
            @endphp
            <tr>
              <td class="cl-c-check"><input type="checkbox" class="bulk-check" value="{{ $c->id }}" title="Select {{ $c->name }}" aria-label="Select {{ $c->name }}"></td>
              <td class="cl-c-client">
                <div class="cl-client">
                  <span class="cl-avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
                  <div class="cl-client-txt">
                    <a class="cl-client-name" href="{{ route('clients.show', $c) }}">{{ $c->company ?: $c->name }}</a>
                    @if($c->company)<span class="cl-sub">{{ $c->name }}</span>@endif
                  </div>
                </div>
              </td>
              <td class="cl-c-contact">
                <div class="cl-contact">
                  @if($c->phone)<a class="cl-phone" href="tel:{{ preg_replace('/\D+/', '', $c->phone) }}">{{ $c->phone }}</a>@endif
                  @if($c->email)<a class="cl-email" href="mailto:{{ $c->email }}">{{ $c->email }}</a>@endif
                  @if(! $c->phone && ! $c->email)<span class="cl-dash">—</span>@endif
                </div>
              </td>
              <td class="cl-c-since"><span class="cl-since" title="{{ d($c->created_at, 'j M Y') }}">{{ d($c->created_at, 'M Y') }}</span></td>
              <td class="cl-c-projects">
                @if($c->projects_count > 0)
                  <a class="cl-count" href="{{ route('clients.show', $c) }}" title="{{ $c->projects_count }} {{ \Illuminate\Support\Str::plural('project', $c->projects_count) }}">{{ $c->projects_count }}</a>
                @else
                  <span class="cl-dash">—</span>
                @endif
              </td>
              <td class="cl-c-status"><span class="cl-status {{ $isActive ? 'is-active' : 'is-inactive' }}"><i></i>{{ ucfirst($c->status ?? 'active') }}</span></td>
              <td class="cl-c-outstanding">
                @if($bal > 0)
                  <b class="cl-money">{{ money($bal) }}</b>
                @else
                  <span class="cl-settled"><i></i>Settled</span>
                @endif
              </td>
              <td class="cl-c-actions">
                <div class="cl-actions">
                  <a class="cl-act" href="{{ route('clients.show', $c) }}" title="Open client" aria-label="Open {{ $c->name }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/></svg></a>
                  <a class="cl-act" href="{{ route('clients.edit', $c) }}" title="Edit client" aria-label="Edit {{ $c->name }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
                  <form method="POST" action="{{ route('clients.destroy', $c) }}" data-confirm="Delete client {{ $c->name }}? Projects, tasks and invoices will be affected.">@csrf @method('DELETE')<button class="cl-act is-danger" title="Delete client" aria-label="Delete {{ $c->name }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8">
                <div class="cl-empty">
                  <span class="cl-empty-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M16 9h2a2 2 0 0 1 2 2v10M8 7h4M8 11h4M8 15h4M2 21h20"/></svg></span>
                  <strong>No clients found</strong>
                  <p>{{ $hasFilters ? 'Try a different search or clear the filters.' : 'Add your first client to start building your directory.' }}</p>
                  <div class="cl-empty-actions">
                    @if($hasFilters)<a class="btn sm sec" href="{{ $clearUrl }}">Clear filters</a>@endif
                    <a class="btn sm" href="{{ route('clients.create') }}">Add client</a>
                  </div>
                </div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  {{ $clients->links() }}
</div>

<div class="modal" id="import-modal" role="dialog" aria-label="Import clients">
  <div class="modal-bg" data-modal-close></div>
  <div class="modal-card">
    <h3>Import clients</h3>
    <p class="hint" style="margin:0 0 12px">CSV with a header row. Required: <code>name</code>. Optional: <code>company, email, phone, address, gstin, state, status, notes</code>. Duplicates skipped by email, max 1000 rows. <a href="{{ route('export.csv','clients') }}">Download sample</a> · <a href="{{ route('import.index') }}">Full import page</a></p>
    <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data">@csrf<input type="hidden" name="type" value="clients">
    <label class="f">CSV file</label><input class="in" type="file" name="file" accept=".csv,.txt" required>
    <div class="form-actions" style="margin-top:16px"><button class="btn" type="submit">Import</button><button class="btn sec" data-modal-close type="button">Cancel</button></div></form>
  </div>
</div>
@endsection
