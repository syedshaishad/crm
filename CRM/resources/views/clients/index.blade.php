@extends('layouts.app')
@section('title','Clients')
@section('content')
<div class="grid stats">
  <div class="stat"><div class="lbl">Total clients</div><b>{{ $stats['total'] }}</b><div class="sub">{{ $stats['new_month'] }} new this month</div></div>
  <div class="stat"><div class="lbl">Active</div><b>{{ $stats['active'] }}</b><div class="sub">{{ $stats['total'] - $stats['active'] }} inactive</div><a class="stat-link" href="{{ route('clients.index',['status'=>'active']) }}">View active →</a></div>
  <div class="stat"><div class="lbl">Outstanding</div><b>{{ money($stats['outstanding']) }}</b><div class="sub">across open invoices</div>@if(module_visible('Reports'))<a class="stat-link" href="{{ route('reports.receivables') }}">View receivables →</a>@endif</div>
</div>

<div class="card" style="margin-top:22px">
<div class="card-head"><h3>Clients</h3><div class="btnrow" style="gap:8px"><button class="btn sm sec" data-modal-open="import-modal">Import</button><a class="btn sm" href="{{ route('clients.create') }}">+ New</a></div></div>
<form method="GET" data-ajax-filter data-region="clients-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search name, company, email, phone…"><select class="in" style="margin:0;flex:1;min-width:130px" name="status"><option value="">All statuses</option><option value="active" {{ ($status ?? '')==='active'?'selected':'' }}>Active</option><option value="inactive" {{ ($status ?? '')==='inactive'?'selected':'' }}>Inactive</option></select><button class="btn sec sm">Search</button>@if(($q ?? '') !== '' || ($status ?? '') !== '')<a class="btn sm sec" href="{{ route('clients.index') }}">Clear</a>@endif</form>
<form method="POST" action="{{ route('clients.store') }}" data-ajax-add data-region="clients-list" class="noprint quickadd" style="display:flex;gap:8px;margin-bottom:6px;flex-wrap:wrap;align-items:center">@csrf
<div class="cap" style="flex-basis:100%">Quick add — name only is enough</div>
<input class="in" style="margin:0;flex:2;min-width:160px" name="name" required placeholder="Quick add: client name ↵" autocomplete="off">
<input class="in" style="margin:0;flex:2;min-width:160px" name="company" placeholder="Company (optional)" autocomplete="off">
<input class="in" style="margin:0;flex:1;min-width:140px" name="phone" placeholder="Phone" autocomplete="off">
<button class="btn sm">Add</button></form>
<div class="bulkbar noprint" data-bulk-bar data-bulk-url="{{ route('clients.bulk') }}" data-region="clients-list" hidden>
<span class="sel"><b data-bulk-count>0</b> selected</span>
<select data-bulk-value name="status"><option value="">Set status…</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
<button class="btn sm sec" data-bulk-apply="status">Apply</button>
<span class="spacer"></span>
<button class="btn sm danger" data-bulk-apply="delete">Delete</button>
</div>
<div data-ajax-region="clients-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th style="width:34px"><input type="checkbox" data-bulk-all title="Select all"></th><th>Client</th><th>Contact</th><th style="text-align:right">Outstanding</th><th>Status</th><th></th></tr>
@forelse($clients as $c)
@php $bal = max(0, ($c->billed ?? 0) - ($c->collected ?? 0)); @endphp
<tr><td><input type="checkbox" class="bulk-check" value="{{ $c->id }}" title="Select"></td><td><span class="who"><span class="av sm">{{ strtoupper(substr($c->name,0,1)) }}</span><span><b><a href="{{ route('clients.show',$c) }}">{{ $c->company ?: $c->name }}</a></b>@if($c->company)<span>{{ $c->name }}</span>@endif</span></span></td>
<td>{{ $c->email ?: '' }}@if($c->email && $c->phone)<br>@endif{{ $c->phone ?: '' }}@if(!$c->email && !$c->phone)<span class="hint">—</span>@endif</td>
<td class="nowrap" style="text-align:right">@if($bal > 0)<b>{{ money($bal) }}</b>@else <span class="hint">settled</span>@endif</td>
<td><span class="badge {{ ($c->status ?? 'active') === 'active' ? 'b-green' : 'b-grey' }}">{{ $c->status ?? 'active' }}</span></td>
<td><div class="row-actions"><a class="icon-btn" href="{{ route('clients.edit',$c) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Edit</a>
<form method="POST" action="{{ route('clients.destroy',$c) }}" style="display:inline" data-confirm="Delete client {{ $c->name }}? Projects, tasks and invoices will be affected.">@csrf @method('DELETE')<button class="icon-btn danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>Delete</button></form></div></td></tr>
@empty<tr><td colspan="6"><div class="empty">No clients found.<br><br><a class="btn sm" href="{{ route('clients.create') }}">Add your first client</a></div></td></tr>@endforelse</table></div>
{{ $clients->links() }}</div></div>
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
