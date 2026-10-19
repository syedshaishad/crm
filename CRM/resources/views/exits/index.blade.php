@extends('layouts.app')
@section('title','Exits')
@section('content')
<div class="card">
<div class="card-head"><h3>Employee exits</h3><span class="hint">dues + assets must clear first</span></div>
<form method="POST" action="{{ route('exits.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
<div><label class="f">Who</label><select class="in" name="user_id" style="margin:0">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
<div><label class="f">Resigned on</label><input class="in" type="date" name="resigned_on" style="margin:0"></div>
<div><label class="f">Last day</label><input class="in" type="date" name="last_day" style="margin:0"></div>
<div style="flex:1;min-width:160px"><label class="f">Reason</label><input class="in" name="reason" maxlength="500" style="margin:0" placeholder="Optional…"></div>
<div><button class="btn sm">Open exit</button></div></form>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Who</th><th>Resigned</th><th>Last day</th><th>Status</th><th></th></tr>
@forelse($rows as $e)<tr>
<td><b>{{ $e->user->name ?? '—' }}</b>@if($e->reason)<div class="hint">{{ \Str::limit($e->reason,60) }}</div>@endif @if($e->clearance)<div class="hint">Clearance: {{ \Str::limit($e->clearance,80) }}</div>@endif</td>
<td class="nowrap">{{ $e->resigned_on ? d($e->resigned_on) : '—' }}</td>
<td class="nowrap">{{ $e->last_day ? d($e->last_day) : '—' }}</td>
<td><span class="badge {{ $e->status==='completed' ? 'b-green' : 'b-amber' }}">{{ ucfirst($e->status) }}</span></td>
<td><div class="row-actions">
@if($e->status!=='completed')<form method="POST" action="{{ route('exits.complete',$e) }}" style="display:inline-flex;gap:4px;align-items:center" data-confirm="Complete this exit? The login deactivates. Blocked until advances, loans and assets clear.">@csrf<input class="in" name="clearance" maxlength="1000" style="margin:0;width:170px" placeholder="Clearance note…"><button class="icon-btn">Complete</button></form>
<form method="POST" action="{{ route('exits.destroy',$e) }}" style="display:inline" data-confirm="Withdraw this exit?">@csrf @method('DELETE')<button class="icon-btn danger">Withdraw</button></form>@endif</div></td></tr>
@empty<tr><td colspan="5"><div class="empty">No exits open.</div></td></tr>@endforelse</table></div>
{{ $rows->links() }}</div>
@endsection
