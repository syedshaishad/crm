@extends('layouts.app')
@section('title','Overtime')
@section('content')
<div class="card">
<div class="card-head"><h3>Overtime</h3>@if($canAll)<span class="hint">approve to pay it in payroll</span>@endif</div>
<form method="POST" action="{{ route('overtime.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
@if($canAll)<div><label class="f">Who</label><select class="in" name="user_id" style="margin:0">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>@endif
<div><label class="f">Day</label><input class="in" type="date" name="day" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" style="margin:0" required></div>
<div><label class="f">Minutes</label><input class="in" type="number" min="15" max="1440" name="minutes" style="margin:0" required placeholder="e.g. 120"></div>
<div style="flex:1;min-width:160px"><label class="f">Note</label><input class="in" name="note" maxlength="255" style="margin:0" placeholder="Site, reason…"></div>
<div><button class="btn sm">Log</button></div></form>
<form method="GET" data-ajax-filter data-region="ot-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px">@if($canAll)<select class="in" style="margin:0" name="user_id"><option value="">Everyone</option>@foreach($users as $u)<option value="{{ $u->id }}" {{ (string)($user_id ?? '')===(string)$u->id?'selected':'' }}>{{ $u->name }}</option>@endforeach</select>@endif<select class="in" style="margin:0" name="status"><option value="">All statuses</option>@foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$l)<option value="{{ $v }}" {{ ($status ?? '')===$v?'selected':'' }}>{{ $l }}</option>@endforeach</select><button class="btn sec sm">Filter</button></form>
<div data-ajax-region="ot-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th>Who</th><th>Day</th><th style="text-align:right">Extra</th><th>Status</th><th></th></tr>
@forelse($rows as $o)<tr>
<td><b>{{ $o->user->name ?? '—' }}</b>@if($o->note)<div class="hint">{{ \Str::limit($o->note,60) }}</div>@endif</td>
<td class="nowrap">{{ d($o->day) }}</td>
<td class="nowrap" style="text-align:right">{{ \Modules\Tasks\TaskTime::fmt($o->minutes) }}</td>
<td><span class="badge {{ $o->status==='approved' ? 'b-green' : ($o->status==='rejected' ? 'b-red' : 'b-amber') }}">{{ ucfirst($o->status) }}</span></td>
<td><div class="row-actions">
@if($o->status==='pending' && $canAll)<form method="POST" action="{{ route('overtime.decide',$o) }}" style="display:inline">@csrf<button class="icon-btn" name="status" value="approved">Approve</button></form><form method="POST" action="{{ route('overtime.decide',$o) }}" style="display:inline" data-confirm="Reject this overtime?">@csrf<button class="icon-btn danger" name="status" value="rejected">Reject</button></form>@endif
@if($o->status!=='approved')<form method="POST" action="{{ route('overtime.destroy',$o) }}" style="display:inline" data-confirm="Withdraw this entry?">@csrf @method('DELETE')<button class="icon-btn danger">Withdraw</button></form>@endif</div></td></tr>
@empty<tr><td colspan="5"><div class="empty">No overtime logged.</div></td></tr>@endforelse</table></div>
{{ $rows->links() }}</div></div>
@endsection
