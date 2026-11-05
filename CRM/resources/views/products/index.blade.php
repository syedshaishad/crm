@extends('layouts.app')
@section('title','Products')
@section('content')
@php
  $statusVal = $status ?? '';
  $hasFilters = ($q ?? '') !== '' || $statusVal !== '';
  $statusUrl = fn ($s) => route('products.index', array_merge(request()->except(['page', 'status']), $s ? ['status' => $s] : []));
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>
    </span>
    <div>
      <h1>Products &amp; services</h1>
      <div class="crumb">{{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('item', $stats['total']) }} · {{ $stats['active'] }} active · {{ money($stats['avg_rate']) }} average rate</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <button class="btn sm sec" data-modal-open="import-modal">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m-4-4 4 4 4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
      Import
    </button>
    <a class="btn sm" href="{{ route('products.create') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
      New product
    </a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric"><span class="ix-metric-lbl">Catalog items</span><b class="ix-metric-val">{{ $stats['total'] }}</b><span class="ix-metric-sub">{{ $stats['archived'] }} archived</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Active</span><b class="ix-metric-val">{{ $stats['active'] }}</b><span class="ix-metric-sub">available for invoicing</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Archived</span><b class="ix-metric-val">{{ $stats['archived'] }}</b><span class="ix-metric-sub">hidden from pickers</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Average rate</span><b class="ix-metric-val is-money">{{ money($stats['avg_rate']) }}</b><span class="ix-metric-sub">across active items</span></div>
</div>

<div class="card ix-card" data-ajax-region="products-list">
  <div class="card-head ix-card-head">
    <div><h3>Product catalog</h3><span class="hint">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('item', $products->total()) }}{{ $hasFilters ? ' matching filters' : ' in this workspace' }}</span></div>
  </div>

  <div class="ix-statuses noprint">
    <a class="ix-chip {{ $statusVal === '' ? 'on' : '' }}" href="{{ $statusUrl('') }}"><i style="background:var(--dc-ink2)"></i>All <b>{{ $stats['total'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'active' ? 'on' : '' }}" href="{{ $statusUrl('active') }}"><i style="background:var(--dc-green)"></i>Active <b>{{ $stats['active'] }}</b></a>
    <a class="ix-chip {{ $statusVal === 'archived' ? 'on' : '' }}" href="{{ $statusUrl('archived') }}"><i style="background:var(--dc-muted)"></i>Archived <b>{{ $stats['archived'] }}</b></a>
  </div>

  <form method="GET" data-ajax-filter data-region="products-list" class="ix-filters noprint">
    <label class="ix-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search name, SKU, HSN or description…" autocomplete="off">
    </label>
    <select class="in ix-filter" name="status">
      <option value="">All statuses</option>
      <option value="active" {{ $statusVal === 'active' ? 'selected' : '' }}>Active</option>
      <option value="archived" {{ $statusVal === 'archived' ? 'selected' : '' }}>Archived</option>
    </select>
    <button class="btn sec sm ix-filter-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filter</button>
    @if($hasFilters)<a class="btn sm sec ix-clear-btn" href="{{ route('products.index') }}">Clear</a>@endif
  </form>

  <form method="POST" action="{{ route('products.store') }}" data-ajax-add data-region="products-list" class="ix-quick noprint">
    @csrf
    <span class="ix-quick-lbl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>Quick add</span>
    <input class="in" name="name" required placeholder="Product or service name" autocomplete="off">
    <input class="in" name="rate" type="number" step="0.01" min="0" placeholder="Rate" autocomplete="off">
    <input class="in" name="hsn_code" placeholder="HSN/SAC" autocomplete="off">
    <button class="btn sm">Add product</button>
  </form>

  <div class="bulkbar noprint" data-bulk-bar data-bulk-url="{{ route('products.bulk') }}" data-region="products-list" hidden>
    <span class="sel"><b data-bulk-count>0</b> selected</span>
    <select data-bulk-value name="status"><option value="">Set status…</option><option value="active">Active</option><option value="archived">Archived</option></select>
    <button class="btn sm sec" data-bulk-apply="status">Apply</button>
    <span class="spacer"></span>
    <button class="btn sm danger" data-bulk-apply="delete">Delete</button>
  </div>

  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table pd-table">
      <thead>
        <tr><th class="ix-c-check"><input type="checkbox" data-bulk-all title="Select all" aria-label="Select all products"></th><th class="ix-c-main">Product</th><th class="ix-c-rate">Rate</th><th class="ix-c-tax">GST / HSN</th><th class="ix-c-stock">Stock</th><th class="ix-c-status">Status</th><th class="ix-c-actions" aria-label="Actions"></th></tr>
      </thead>
      <tbody>
      @forelse($products as $p)
        @php
          $onHand = round((float) ($p->opening_stock ?? 0) + (float) ($p->stock_in ?? 0) - (float) ($p->stock_out ?? 0) + (float) ($p->stock_adj ?? 0), 2);
          $low = $p->low_stock_at !== null && $onHand <= (float) $p->low_stock_at;
          $onHandText = rtrim(rtrim(number_format($onHand, 2, '.', ''), '0'), '.') ?: '0';
          $tax = $p->tax_rate !== null ? rtrim(rtrim(number_format((float) $p->tax_rate, 2, '.', ''), '0'), '.') : null;
          $isActive = ($p->status ?? 'active') === 'active';
        @endphp
        <tr>
          <td class="ix-c-check"><input type="checkbox" class="bulk-check" value="{{ $p->id }}" title="Select {{ $p->name }}" aria-label="Select {{ $p->name }}"></td>
          <td class="ix-c-main">
            <div class="ix-main">
              <span class="ix-avatar">
                @if($p->image_path)<img src="{{ asset('storage/'.$p->image_path) }}" alt="{{ $p->name }}">@else<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>@endif
              </span>
              <div class="ix-main-txt">
                <span class="ix-name">{{ $p->name }}</span>
                <span class="ix-sub">{{ $p->sku ?: 'No SKU' }}@if($p->description) · {{ \Illuminate\Support\Str::limit($p->description, 70) }}@endif</span>
              </div>
            </div>
          </td>
          <td class="ix-c-rate">
            <div class="ix-stack-end">
              <span class="ix-money">{{ money($p->rate) }}</span>
              <span class="ix-sub">per {{ $p->unit ?: 'unit' }}</span>
            </div>
          </td>
          <td class="ix-c-tax">
            <div class="ix-main-txt">
              <span class="ix-vehicle">{{ $tax !== null ? $tax.'% GST' : 'No GST' }}</span>
              @if($p->hsn_code)<span class="ix-sub">HSN {{ $p->hsn_code }}</span>@endif
            </div>
          </td>
          <td class="ix-c-stock">
            <div class="ix-stack-end">
              @if($low)
                <span class="ix-low">{{ $onHandText }} {{ $p->unit }}</span>
                <span class="ix-sub is-warn">Low · min {{ rtrim(rtrim(number_format((float) $p->low_stock_at, 2, '.', ''), '0'), '.') }}</span>
              @else
                <span class="ix-stock">{{ $onHandText }} {{ $p->unit }}</span>
                <span class="ix-sub">{{ $p->low_stock_at !== null ? 'Alert at '.rtrim(rtrim(number_format((float) $p->low_stock_at, 2, '.', ''), '0'), '.') : 'No alert set' }}</span>
              @endif
            </div>
          </td>
          <td class="ix-c-status"><span class="ix-state {{ $isActive ? 'is-green' : 'is-grey' }}"><i></i>{{ ucfirst($p->status ?? 'active') }}</span></td>
          <td class="ix-c-actions">
            <div class="ix-actions">
              <a class="ix-act" href="{{ route('products.stock', $p) }}" title="Stock ledger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg></a>
              <a class="ix-act" href="{{ route('products.edit', $p) }}" title="Edit product"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
              <form method="POST" action="{{ route('products.destroy', $p) }}" data-confirm="Delete product {{ $p->name }}? Issued invoices keep their own copies.">@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete product"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="7"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg></span><strong>{{ $hasFilters ? 'No products match these filters' : 'No products yet' }}</strong><p>{{ $hasFilters ? 'Try a different search or clear the active filters.' : 'Quick add an item above, or create one with full details.' }}</p><div class="ix-empty-actions">@if($hasFilters)<a class="btn sm sec" href="{{ route('products.index') }}">Clear filters</a>@else<a class="btn sm" href="{{ route('products.create') }}">+ New product</a>@endif</div></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $products->links() }}
</div>

<div class="modal" id="import-modal" role="dialog" aria-label="Import products">
  <div class="modal-bg" data-modal-close></div>
  <div class="modal-card">
    <h3>Import products</h3>
    <p class="hint" style="margin:0 0 12px">CSV with a header row. Required: <code>name</code>. Optional: <code>sku, description, rate, tax_rate, hsn_code, unit, status</code>. Duplicates skipped by SKU (or name), max 1000 rows. <a href="{{ route('export.csv','products') }}">Download sample</a> · <a href="{{ route('import.index') }}">Full import page</a></p>
    <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data">@csrf<input type="hidden" name="type" value="products">
    <label class="f">CSV file</label><input class="in" type="file" name="file" accept=".csv,.txt" required>
    <div class="form-actions" style="margin-top:16px"><button class="btn" type="submit">Import</button><button class="btn sec" data-modal-close type="button">Cancel</button></div></form>
  </div>
</div>
@endsection
