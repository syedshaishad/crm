@extends('layouts.app')
@section('title','Reimbursements')
@section('content')
<div class="card">
<div class="card-head"><h3>Expense claims</h3><span class="hint">approved ones join the next payroll</span></div>
<form method="POST" action="{{ route('reimbursements.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
@if($canAll)<div><label class="f">Who</label><select class="in" name="user_id" style="margin:0">@foreach(\App\Models\User::where('is_active',true)->orderBy('name')->get(['id','name']) as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>@endif
<div><label class="f">Spent on</label><input class="in" type="date" name="spent_on" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" style="margin:0" required></div>
<div><label class="f">Amount (₹)</label><input class="in" type="number" step="0.01" min="1" name="amount" style="margin:0" required></div>
<div style="flex:1;min-width:180px"><label class="f">What for? <span class="req">*</span></label><input class="in" name="reason" required maxlength="255" style="margin:0" placeholder="Fuel, courier, site food…"></div>
<div><button class="btn sm">File claim</button></div></form>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Who</th><th>Spent</th><th style="text-align:right">Amount</th><th>Status</th><th></th></tr>
@forelse($rows as $c)<tr>
<td><b>{{ $c->user->name ?? '—' }}</b><div class="hint">{{ \Str::limit($c->reason ?? '',60) }}</div></td>
<td class="nowrap">{{ d($c->spent_on) }}</td>
<td class="nowrap" style="text-align:right">{{ money($c->amount) }}</td>
<td><span class="badge {{ $c->status==='approved' ? 'b-green' : ($c->status==='rejected' ? 'b-red' : 'b-amber') }}">{{ ucfirst($c->status) }}</span>@if($c->payroll_id)<div class="hint">paid in run</div>@endif</td>
<td><div class="row-actions">
@if($c->status==='pending' && $canAll)<form method="POST" action="{{ route('reimbursements.decide',$c) }}" style="display:inline">@csrf<button class="icon-btn" name="status" value="approved">Approve</button></form><form method="POST" action="{{ route('reimbursements.decide',$c) }}" style="display:inline" data-confirm="Reject this claim?">@csrf<button class="icon-btn danger" name="status" value="rejected">Reject</button></form>@endif
@if(!$c->payroll_id)<form method="POST" action="{{ route('reimbursements.destroy',$c) }}" style="display:inline" data-confirm="Withdraw this claim?">@csrf @method('DELETE')<button class="icon-btn danger">Withdraw</button></form>@endif</div></td></tr>
@empty<tr><td colspan="5"><div class="empty">No claims filed.</div></td></tr>@endforelse</table></div>
{{ $rows->links() }}</div>
@endsection
