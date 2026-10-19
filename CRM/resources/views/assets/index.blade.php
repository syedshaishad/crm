@extends('layouts.app')
@section('title','Assets')
@section('content')
<div class="card">
<div class="card-head"><h3>Fixed assets</h3><span class="hint">straight-line depreciation</span></div>
<div class="grid stats cols-4" style="margin-bottom:14px">
  <div class="stat acc" style="--acc:#0a84ff"><div class="lbl">Original cost</div><b>{{ money($total['cost']) }}</b></div>
  <div class="stat acc" style="--acc:#30d158"><div class="lbl">Book value today</div><b>{{ money($total['book']) }}</b></div>
</div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Asset</th><th>Bought</th><th>Life</th><th style="text-align:right">Cost</th><th style="text-align:right">Book value</th><th class="noprint"></th></tr>
@forelse($assets as $a)<tr><td><b>{{ $a->name }}</b>@if($a->location)<div class="hint">{{ $a->location }}</div>@endif @if($a->notes)<div class="hint">{{ $a->notes }}</div>@endif @if($a->holder)<div class="hint">with {{ $a->holder->name }}</div>@endif</td><td class="nowrap">{{ d($a->bought_on) }}</td><td class="nowrap">{{ $a->life_years }} yr</td><td class="nowrap" style="text-align:right">{{ money($a->cost) }}</td><td class="nowrap" style="text-align:right"><b>{{ money($a->bookValue()) }}</b></td>
<td class="noprint" style="text-align:right;white-space:nowrap">
<form method="POST" action="{{ route('assets.assign',$a) }}" style="display:inline-flex;gap:4px;align-items:center;margin-right:6px">@csrf<select class="in" name="assigned_to" style="margin:0;width:auto" onchange="this.form.submit()" title="Custody"><option value="">Office</option>@foreach($users as $u)<option value="{{ $u->id }}" {{ (string)($a->assigned_to ?? '')===(string)$u->id?'selected':'' }}>→ {{ $u->name }}</option>@endforeach</select></form>
<form method="POST" action="{{ route('assets.destroy',$a) }}" style="display:inline" data-confirm="Move {{ $a->name }} to Trash?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form></td></tr>@empty<tr><td colspan="6"><div class="empty">No assets — register laptops, printers, furniture below.</div></td></tr>@endforelse</table></div></div>
<div class="card" style="margin-top:22px;max-width:680px"><div class="card-head"><h3>Register asset</h3></div>
<form method="POST" action="{{ route('assets.store') }}">@csrf
<label class="f">Name <span class="req">*</span></label><input class="in" name="name" required maxlength="160" placeholder="e.g. Office laptop">
<div class="form-grid">
<div><label class="f">Bought on</label><input class="in" type="date" name="bought_on" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required></div>
<div><label class="f">Cost (₹)</label><input class="in" type="number" step="0.01" min="0" name="cost" required></div>
<div><label class="f">Life (years)</label><input class="in" type="number" min="1" max="30" name="life_years" value="5" required></div>
</div>
<label class="f">Location</label><input class="in" name="location" maxlength="160" placeholder="e.g. Front office">
<label class="f">Notes</label><input class="in" name="notes" placeholder="Serial no, bill ref…">
<div class="form-actions"><button class="btn sm" type="submit">Register</button></div></form></div>
@endsection
