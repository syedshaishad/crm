@extends('layouts.app')
@section('title',$client->name)
@section('content')
@php
  $billed = $client->invoices->sum('total');
  $paid = $client->invoices->sum('paid_amount');
  $pct = $billed > 0 ? min(100, round($paid / $billed * 100)) : 0;
@endphp
<div class="card">
  <div class="profile-cover"></div>
  <div class="profile-av"><span class="av lg">{{ strtoupper(substr($client->name,0,1)) }}</span></div>
  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">
    <div><h2 style="margin:0">{{ $client->company ?: $client->name }}</h2><div class="hint">@if($client->company){{ $client->name }} · @endif{{ $client->status ?? 'active' }}</div></div>
    <div class="btnrow"><a class="btn sec sm" href="{{ route('clients.edit',$client) }}">Edit</a><a class="btn sec sm" href="{{ route('clients.ledger',$client) }}">Ledger</a>@if(module_visible('Proposals'))<a class="btn sec sm" href="{{ route('proposals.create',['client_id'=>$client->id]) }}">+ Proposal</a>@endif<a class="btn sm" href="{{ route('invoices.create',['client_id'=>$client->id]) }}">+ Invoice</a></div>
  </div>
  <div class="cols-2" style="margin-top:16px">
    <div>
      <div class="kv"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><span class="k">Email</span><span>{{ $client->email ?: '—' }}</span></div>
      <div class="kv"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg><span class="k">Phone</span><span>{{ $client->phone ?: '—' }}</span></div>
      <div class="kv"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><span class="k">Address</span><span>{{ $client->address ?: '—' }}</span></div>
      <div class="kv"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v14H4z"/><path d="M4 10h16"/></svg><span class="k">GSTIN</span><span>{{ $client->gstin ?: 'Unregistered' }}@if($client->gstin && ($gerr = gstin_check($client->gstin))) <span class="badge b-red" title="{{ $gerr }}">invalid</span>@endif</span></div>
      <div class="kv"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18"/></svg><span class="k">State</span><span>{{ $client->state ?: '—' }}</span></div>
      @if($client->birthday)<div class="kv"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M12 10v10M12 10a2 2 0 1 0-4 0M12 10a2 2 0 1 1 4 0M8 6h8"/></svg><span class="k">Birthday</span><span>{{ d($client->birthday, 'j M') }}</span></div>@endif
      @if($client->notes)<div class="kv"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/></svg><span class="k">Notes</span><span>{{ $client->notes }}</span></div>@endif
      @if(!empty($client->custom_fields))
      @foreach($client->custom_fields as $l=>$v)<div class="kv"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v4H4zM4 13h16v4H4z"/></svg><span class="k">{{ $l }}</span><span>{{ $v }}</span></div>@endforeach
      @endif
    </div>
    <div>
      <div class="fsec" style="margin-top:0">Statistics</div>
      <div style="font-size:13px">Projects <b style="float:right">{{ $client->projects->count() }}</b></div>
      <div style="font-size:13px;margin-top:10px">Billed <b style="float:right">{{ money($billed) }}</b></div>
      <div style="font-size:13px;margin-top:10px">Collected ({{ $pct }}%) <b style="float:right">{{ money($paid) }}</b></div>
      <div class="statbar"><div style="width:{{ $pct }}%"></div></div>
      <div style="font-size:13px;margin-top:10px">Outstanding <b style="float:right">{{ money($outstanding) }}</b></div>
      <div class="statbar"><div style="width:{{ $billed > 0 ? round($outstanding / $billed * 100) : 0 }}%;background:var(--ink)"></div></div>
      @php $adv = client_advance($client->id); @endphp
      @if($adv > 0)<div style="font-size:13px;margin-top:10px">Advance available <b style="float:right">{{ money($adv) }}</b></div>
      <div class="hint">Unlinked receipts — adjust them from any open invoice.</div>@endif
      <div style="font-size:13px;margin-top:10px">Credit limit <b style="float:right">{{ (float)($client->credit_limit ?? 0) > 0 ? money($client->credit_limit) : 'Unlimited' }}</b></div>
      <div style="font-size:13px;margin-top:6px">Loyalty balance <b style="float:right">{{ money($client->loyalty_balance ?? 0) }}</b></div>
      @if(credit_warn($client))<div class="alert warn" style="margin:10px 0 0">{{ credit_warn($client) }}</div>@endif
    </div>
  </div>
</div>
<div class="cols-2">
<div class="card"><div class="card-head"><h3>Projects</h3><a class="btn sm sec" href="{{ route('projects.create') }}">+ New</a></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Name</th><th>Status</th></tr>
@forelse($client->projects as $p)<tr><td><a href="{{ route('projects.show',$p) }}">{{ $p->name }}</a></td><td><span class="badge b-grey">{{ $p->status }}</span></td></tr>@empty<tr><td colspan="2"><div class="empty">No projects.</div></td></tr>@endforelse</table></div></div>
<div class="card"><div class="card-head"><h3>Invoices</h3><a class="btn sm sec" href="{{ route('invoices.create',['client_id'=>$client->id]) }}">+ New</a></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Number</th><th style="text-align:right">Total</th><th>Status</th></tr>
@forelse($client->invoices as $i)<tr><td><a href="{{ route('invoices.show',$i) }}">{{ $i->number }}</a></td><td style="text-align:right;font-variant-numeric:tabular-nums">{{ money($i->total) }}</td><td><span class="badge b-blue">{{ $i->status }}</span></td></tr>@empty<tr><td colspan="3"><div class="empty">No invoices.</div></td></tr>@endforelse</table></div></div>
</div>
<div class="card"><div class="card-head"><h3>Documents</h3><a class="hint" href="{{ route('documents.index',['client_id'=>$client->id]) }}">Open folder →</a></div>@php $hasDocs = $client->documents->isNotEmpty() || $client->projects->contains(fn ($p) => $p->documents->isNotEmpty()); @endphp
@if($client->documents->isNotEmpty())
<div class="fsec" style="margin-top:0">Client files</div>
<div class="fm-grid" style="margin-bottom:14px">
@foreach($client->documents as $dd)
@php $ext = file_ext($dd->name); @endphp
<div class="file-tile"><a class="file-ic {{ file_icon_class($ext) }}" href="{{ route('documents.download',$dd) }}" title="Download {{ $dd->name }}">{{ file_icon_label($ext) }}</a><div class="file-name" title="{{ $dd->name }}">{{ $dd->name }}</div><div class="hint">{{ fmt_size($dd->size) }}</div></div>
@endforeach
</div>
@endif
@foreach($client->projects as $p)
@if($p->documents->isNotEmpty())
<div class="fsec" style="margin-top:0"><a href="{{ route('projects.show',$p) }}">{{ $p->name }}</a> <span class="hint">({{ $p->documents->count() }})</span></div>
<div class="fm-grid" style="margin-bottom:14px">
@foreach($p->documents as $dd)
@php $ext = file_ext($dd->name); @endphp
<div class="file-tile"><a class="file-ic {{ file_icon_class($ext) }}" href="{{ route('documents.download',$dd) }}" title="Download {{ $dd->name }}">{{ file_icon_label($ext) }}</a><div class="file-name" title="{{ $dd->name }}">{{ $dd->name }}</div><div class="hint">{{ fmt_size($dd->size) }}</div></div>
@endforeach
</div>
@endif
@endforeach
@if(!$hasDocs)<div class="empty">No files for this client yet.</div>@endif</div>
<div class="card"><div class="card-head"><h3>Price list</h3><span class="hint">special rates for {{ $client->company ?: $client->name }}</span></div>
@forelse($prices as $pr)
<div class="mi-row" style="border-color:var(--line)">
  <span style="flex:1"><b>{{ $pr->product->name ?? '—' }}</b><span>catalog {{ money($pr->product->rate ?? 0) }}</span></span>
  <span class="badge b-blue">{{ money($pr->rate) }}</span>
  <div class="row-actions">
    <form method="POST" action="{{ route('clients.prices.destroy',[$client,$pr]) }}" style="display:inline" data-confirm="Remove this special rate?">@csrf @method('DELETE')<button class="icon-btn danger">Remove</button></form>
  </div>
</div>
@empty
<div class="empty">Catalog rates apply — add a special rate below to override per product.</div>
@endforelse
<form method="POST" action="{{ route('clients.prices.store',$client) }}" class="noprint" style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;align-items:end">@csrf
<div style="flex:2;min-width:170px"><label class="f">Product</label><select class="in" style="margin:0" name="product_id" required>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }} · {{ money($p->rate) }}</option>@endforeach</select></div>
<div style="flex:1;min-width:130px"><label class="f">Special rate (₹)</label><input class="in" style="margin:0" type="number" step="0.01" min="0" name="rate" required></div>
<div><button class="btn sm">Save rate</button></div>
</form>
</div>
<div class="card"><div class="card-head"><h3>Portal access</h3><span class="hint">logins for {{ $client->company ?: $client->name }}</span></div>
@forelse($client->portalUsers as $u)
<div class="mi-row" style="border-color:var(--line)">
  <span class="mi-ic" style="background:var(--surface-2)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="color:var(--ink2)"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.9-3.8 3.7-5.5 7-5.5s6.1 1.7 7 5.5"/></svg></span>
  <span style="flex:1"><b>{{ $u->name }}</b><span>{{ $u->email }}</span></span>
  <span class="badge {{ $u->is_active ? 'b-green' : 'b-grey' }}">{{ $u->is_active ? 'active' : 'disabled' }}</span>
  <div class="row-actions">
    <form method="POST" action="{{ route('clients.users.toggle',[$client,$u]) }}" style="display:inline">@csrf<button class="icon-btn">{{ $u->is_active ? 'Disable' : 'Enable' }}</button></form>
    <form method="POST" action="{{ route('clients.users.destroy',[$client,$u]) }}" style="display:inline" data-confirm="Remove portal access for {{ $u->email }}?">@csrf @method('DELETE')<button class="icon-btn danger">Remove</button></form>
  </div>
</div>
@empty
<div class="empty">No portal logins yet — create one so this client can sign in.</div>
@endforelse
<form method="POST" action="{{ route('clients.users.store',$client) }}" class="noprint" style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;align-items:end">@csrf
<div style="flex:2;min-width:150px"><label class="f">Name</label><input class="in" style="margin:0" name="name" required maxlength="255" placeholder="Contact person"></div>
<div style="flex:2;min-width:170px"><label class="f">Email (login)</label><input class="in" style="margin:0" type="email" name="email" required maxlength="255"></div>
<div style="flex:1;min-width:140px"><label class="f">Password</label><input class="in" style="margin:0" type="text" name="password" required minlength="8" placeholder="min 8 chars"></div>
<div><button class="btn sm">Create login</button></div>
</form>
<p class="hint" style="margin:8px 0 0">Share <code>{{ route('portal.login') }}</code> with them. They can change the password after signing in.</p>
</div>
@endsection
