@extends('layouts.app')
@section('title','Leave')
@section('content')
<div class="grid stats cols-4" style="margin-bottom:14px">
  @foreach(['sick'=>'Sick','casual'=>'Casual','earned'=>'Earned'] as $t=>$l)<div class="stat"><div class="lbl">{{ $l }} left ({{ date('Y') }})</div><b>{{ $balances[$t]['left'] }}</b><div class="sub">of {{ $balances[$t]['quota'] }} · used {{ $balances[$t]['used'] }}</div></div>@endforeach
  <div class="stat"><div class="lbl">Awaiting decision</div><b>{{ $pending ?? 0 }}</b><div class="sub">pending requests</div></div>
</div>
<div class="card">
<div class="card-head"><h3>Leave requests</h3></div>
<form method="POST" action="{{ route('leaves.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
@if($canAll)<div><label class="f">Who</label><select class="in" name="user_id" style="margin:0">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>@endif
<div><label class="f">From</label><input class="in" type="date" name="from_date" value="{{ date('Y-m-d') }}" style="margin:0" required></div>
<div><label class="f">To</label><input class="in" type="date" name="to_date" value="{{ date('Y-m-d') }}" style="margin:0" required></div>
<div><label class="f">Type</label><select class="in" name="type" style="margin:0">@foreach(\Modules\Work\Leave::types() as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
<div style="flex:1;min-width:160px"><label class="f">Reason</label><input class="in" name="reason" maxlength="500" style="margin:0" placeholder="Optional…"></div>
<div><button class="btn sm">Apply</button></div></form>
<form method="GET" data-ajax-filter data-region="leaves-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">@if($canAll)<select class="in" style="margin:0" name="user_id"><option value="">Everyone</option>@foreach($users as $u)<option value="{{ $u->id }}" {{ (string)($user_id ?? '')===(string)$u->id?'selected':'' }}>{{ $u->name }}</option>@endforeach</select>@endif<select class="in" style="margin:0" name="status"><option value="">All statuses</option>@foreach(\Modules\Work\Leave::statuses() as $v=>$l)<option value="{{ $v }}" {{ ($status ?? '')===$v?'selected':'' }}>{{ $l }}</option>@endforeach</select><button class="btn sec sm">Filter</button></form>
<div data-ajax-region="leaves-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th>Who</th><th>Dates</th><th>Type</th><th>Days</th><th>Status</th><th></th></tr>
@forelse($leaves as $l)<tr>
<td><b>{{ $l->user->name ?? '—' }}</b>@if($l->reason)<div class="hint">{{ \Str::limit($l->reason,60) }}</div>@endif</td>
<td class="nowrap">{{ d($l->from_date) }} → {{ d($l->to_date) }}</td>
<td><span class="badge b-grey">{{ \Modules\Work\Leave::types()[$l->type] ?? $l->type }}</span></td>
<td class="nowrap">{{ $l->days() }}</td>
<td><span class="badge {{ $l->status==='approved' ? 'b-green' : ($l->status==='rejected' ? 'b-red' : 'b-amber') }}">{{ ucfirst($l->status) }}</span></td>
<td><div class="row-actions">
@if($l->status==='pending' && $canAll)<form method="POST" action="{{ route('leaves.decide',$l) }}" style="display:inline">@csrf<button class="icon-btn" name="status" value="approved">Approve</button></form><form method="POST" action="{{ route('leaves.decide',$l) }}" style="display:inline" data-confirm="Reject this leave?">@csrf<button class="icon-btn danger" name="status" value="rejected">Reject</button></form>@endif
@if($l->status!=='approved')<form method="POST" action="{{ route('leaves.destroy',$l) }}" style="display:inline" data-confirm="Withdraw this leave request?">@csrf @method('DELETE')<button class="icon-btn danger">Withdraw</button></form>@endif</div></td></tr>
@empty<tr><td colspan="6"><div class="empty">No leave requests.</div></td></tr>@endforelse</table></div>
{{ $leaves->links() }}</div></div>
@endsection
