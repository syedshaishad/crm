@extends('layouts.app')
@section('title','Staff loans')
@section('content')

<x-page-header title="Staff loans" :crumb="'EMI auto-deducts every payroll'" icon='<path d="M12 2v20M17 6.5c0-2-2.2-3-5-3s-5 1-5 3 2.2 2.6 5 3.2 5 1.3 5 3.3-2.2 3-5 3-5-1-5-3"/>'/>
<div class="card ix-card">
<div class="card-head"><h2>Staff loans</h2><span class="hint">EMI auto-deducts every payroll</span></div>
<form method="POST" action="{{ route('loans.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
<div><label class="f">Who</label><select class="in" name="user_id" style="margin:0">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
<div><label class="f">Principal (₹)</label><input class="in" type="number" step="0.01" min="1" name="principal" style="margin:0" required></div>
<div><label class="f">EMI (₹)</label><input class="in" type="number" step="0.01" min="1" name="emi" style="margin:0" required></div>
<div><label class="f">Disbursed on</label><input class="in" type="date" name="disbursed_on" value="{{ date('Y-m-d') }}" style="margin:0" required></div>
<div style="flex:1;min-width:140px"><label class="f">Note</label><input class="in" name="note" maxlength="255" style="margin:0" placeholder="Optional…"></div>
<div><button class="btn sm">Record loan</button></div></form>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Who</th><th style="text-align:right">Principal</th><th style="text-align:right">EMI</th><th style="text-align:right">Repaid</th><th style="text-align:right">Left</th><th>Status</th><th></th></tr>
@forelse($rows as $l)<tr>
<td><b>{{ $l->user->name ?? '—' }}</b>@if($l->note)<div class="hint">{{ \Str::limit($l->note,50) }}</div>@endif<div class="hint">since {{ d($l->disbursed_on, 'j M Y') }}</div></td>
<td class="nowrap" style="text-align:right">{{ money($l->principal) }}</td>
<td class="nowrap" style="text-align:right">{{ money($l->emi) }}</td>
<td class="nowrap" style="text-align:right">{{ money($l->repaid()) }}</td>
<td class="nowrap" style="text-align:right"><b>{{ money($l->outstanding()) }}</b></td>
<td><span class="badge {{ $l->status==='active' ? 'b-green' : 'b-grey' }}">{{ ucfirst($l->status) }}</span></td>
<td>@if($l->repaid() <= 0)<form method="POST" action="{{ route('loans.destroy',$l) }}" style="display:inline" data-confirm="Remove this loan?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form>@endif</td></tr>
@empty<tr><td colspan="7"><div class="empty">No loans on the books.</div></td></tr>@endforelse</table></div>
{{ $rows->links() }}</div>
@endsection
