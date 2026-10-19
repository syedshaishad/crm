@extends('layouts.app')
@section('title','Bonuses')
@section('content')
<div class="card">
<div class="card-head"><h3>Bonuses</h3><span class="hint">approved ones join the next payroll</span></div>
@if($canAll)
<form method="POST" action="{{ route('bonuses.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
<div><label class="f">Who</label><select class="in" name="user_id" style="margin:0">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
<div><label class="f">Month</label><input class="in" type="month" name="month" value="{{ date('Y-m') }}" style="margin:0" required></div>
<div><label class="f">Amount (₹)</label><input class="in" type="number" step="0.01" min="1" name="amount" style="margin:0" required></div>
<div style="flex:1;min-width:160px"><label class="f">Reason</label><input class="in" name="reason" maxlength="255" style="margin:0" placeholder="Diwali, target smashed…"></div>
<div><button class="btn sm">Propose</button></div></form>
@endif
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Who</th><th>Month</th><th style="text-align:right">Amount</th><th>Status</th><th></th></tr>
@forelse($rows as $b)<tr>
<td><b>{{ $b->user->name ?? '—' }}</b>@if($b->reason)<div class="hint">{{ \Str::limit($b->reason,60) }}</div>@endif</td>
<td class="nowrap">{{ d($b->month,'F Y') }}</td>
<td class="nowrap" style="text-align:right">{{ money($b->amount) }}</td>
<td><span class="badge {{ $b->status==='approved' ? 'b-green' : ($b->status==='rejected' ? 'b-red' : 'b-amber') }}">{{ ucfirst($b->status) }}</span>@if($b->payroll_id)<div class="hint">paid in run</div>@endif</td>
<td><div class="row-actions">
@if($b->status==='pending' && $canAll)<form method="POST" action="{{ route('bonuses.decide',$b) }}" style="display:inline">@csrf<button class="icon-btn" name="status" value="approved">Approve</button></form><form method="POST" action="{{ route('bonuses.decide',$b) }}" style="display:inline" data-confirm="Reject this bonus?">@csrf<button class="icon-btn danger" name="status" value="rejected">Reject</button></form>@endif
@if(!$b->payroll_id)<form method="POST" action="{{ route('bonuses.destroy',$b) }}" style="display:inline" data-confirm="Remove this bonus?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form>@endif</div></td></tr>
@empty<tr><td colspan="5"><div class="empty">No bonuses yet.</div></td></tr>@endforelse</table></div>
{{ $rows->links() }}</div>
@endsection
