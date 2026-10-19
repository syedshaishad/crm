@extends('layouts.app')
@section('title','Products')
@section('content')
<div class="grid stats">
  <div class="stat"><div class="lbl">Catalog items</div><b>{{ $stats['total'] }}</b><div class="sub">{{ $stats['active'] }} active</div></div>
  <div class="stat"><div class="lbl">Active</div><b>{{ $stats['active'] }}</b><div class="sub">{{ $stats['total'] - $stats['active'] }} archived</div></div>
  <div class="stat"><div class="lbl">Avg. rate</div><b>{{ money($stats['avg_rate']) }}</b><div class="sub">across active items</div></div>
</div>

<div class="card" style="margin-top:22px">
<div class="card-head"><h3>Products</h3><div class="btnrow" style="gap:8px"><button class="btn sm sec" data-modal-open="import-modal">Import</button><a class="btn sm" href="{{ route('products.create') }}">+ New</a></div></div>
<form method="GET" data-ajax-filter data-region="products-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search name, SKU, HSN…"><select class="in" style="margin:0;flex:1;min-width:130px" name="status"><option value="">All statuses</option><option value="active" {{ ($status ?? '')==='active'?'selected':'' }}>Active</option><option value="archived" {{ ($status ?? '')==='archived'?'selected':'' }}>Archived</option></select><button class="btn sec sm">Search</button></form>
<form method="POST" action="{{ route('products.store') }}" data-ajax-add data-region="products-list" class="noprint quickadd" style="display:flex;gap:8px;margin-bottom:6px;flex-wrap:wrap;align-items:center">@csrf
<div class="cap" style="flex-basis:100%">Quick add — name + rate is enough</div>
<input class="in" style="margin:0;flex:2;min-width:160px" name="name" required placeholder="Quick add: product or service ↵" autocomplete="off">
<input class="in" style="margin:0;flex:1;min-width:110px" name="rate" type="number" step="0.01" min="0" placeholder="Rate (₹)" autocomplete="off">
<input class="in" style="margin:0;flex:1;min-width:90px" name="hsn_code" placeholder="HSN/SAC" autocomplete="off">
<button class="btn sm">Add</button></form>
<div class="bulkbar noprint" data-bulk-bar data-bulk-url="{{ route('products.bulk') }}" data-region="products-list" hidden>
<span class="sel"><b data-bulk-count>0</b> selected</span>
<select data-bulk-value name="status"><option value="">Set status…</option><option value="active">Active</option><option value="archived">Archived</option></select>
<button class="btn sm sec" data-bulk-apply="status">Apply</button>
<span class="spacer"></span>
<button class="btn sm danger" data-bulk-apply="delete">Delete</button>
</div>
<div data-ajax-region="products-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th style="width:34px"><input type="checkbox" data-bulk-all title="Select all"></th><th>Product</th><th>Rate</th><th>GST</th><th style="text-align:right">Stock</th><th>Status</th><th></th></tr>
@forelse($products as $p)
@php $onHand = round((float)($p->opening_stock ?? 0) + (float)($p->stock_in ?? 0) - (float)($p->stock_out ?? 0) + (float)($p->stock_adj ?? 0), 2); $low = $p->low_stock_at !== null && $onHand <= (float)$p->low_stock_at; @endphp
<tr><td><input type="checkbox" class="bulk-check" value="{{ $p->id }}" title="Select"></td><td><b>{{ $p->name }}</b>@if($p->sku)<span class="hint"> · {{ $p->sku }}</span>@endif @if($p->description)<div class="hint">{{ \Illuminate\Support\Str::limit($p->description, 70) }}</div>@endif</td>
<td class="nowrap"><b>{{ money($p->rate) }}</b>@if($p->unit)<span class="hint"> / {{ $p->unit }}</span>@endif</td>
<td class="nowrap">@if($p->tax_rate !== null){{ rtrim(rtrim(number_format($p->tax_rate, 2), '0'), '.') }}%@if($p->hsn_code)<span class="hint"> · {{ $p->hsn_code }}</span>@endif @else<span class="hint">—</span>@endif</td>
<td class="nowrap" style="text-align:right">@if($low)<span class="badge b-amber">{{ $onHand }} {{ $p->unit }}</span>@else{{ $onHand }} {{ $p->unit }}@endif</td>
<td><span class="badge {{ $p->status === 'active' ? 'b-green' : 'b-grey' }}">{{ $p->status }}</span></td>
<td><div class="row-actions"><a class="icon-btn" href="{{ route('products.stock',$p) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>Stock</a><a class="icon-btn" href="{{ route('products.edit',$p) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Edit</a>
<form method="POST" action="{{ route('products.destroy',$p) }}" style="display:inline" data-confirm="Delete product {{ $p->name }}? Issued invoices keep their own copies.">@csrf @method('DELETE')<button class="icon-btn danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>Delete</button></form></div></td></tr>
@empty<tr><td colspan="7"><div class="empty">No products found.<br><br><a class="btn sm" href="{{ route('products.create') }}">Add your first product</a></div></td></tr>@endforelse</table></div>
{{ $products->links() }}</div></div>
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
