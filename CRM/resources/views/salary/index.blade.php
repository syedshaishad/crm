@extends('layouts.app')
@section('title','Salary advances')
@section('content')
@if(count($balances))
<div class="grid stats cols-4" style="margin-bottom:22px">
@foreach($balances as $b)<div class="stat acc" style="--acc:#ff9f0a"><div class="lbl">{{ $b['user']->name }} owes</div><b>{{ money($b['balance']) }}</b></div>@endforeach
</div>
@endif
<div class="card">
<div class="card-head"><h3>Ledger</h3></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Date</th><th>Staff</th><th>Type</th><th style="text-align:right">Amount</th><th>Note</th><th class="noprint"></th></tr>
@forelse($rows as $e)<tr><td class="nowrap">{{ d($e->dated) }}</td><td>{{ $e->user->name ?? '—' }}</td><td><span class="badge {{ $e->kind==='advance' ? 'b-amber' : 'b-green' }}">{{ ucfirst($e->kind) }}</span></td><td class="nowrap" style="text-align:right"><b>{{ money($e->amount) }}</b></td><td class="hint">{{ $e->note ?: '—' }}</td><td class="noprint">@if(auth()->user()->isAdmin() || auth()->user()->isManager())<form method="POST" action="{{ route('salary.destroy',$e) }}" style="display:inline" data-confirm="Remove this entry? Balances recalculate.">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form>@endif</td></tr>@empty<tr><td colspan="6"><div class="empty">No advances on record.</div></td></tr>@endforelse</table></div>
{{ $rows->links() }}</div>
@if(auth()->user()->isAdmin() || auth()->user()->isManager())
<div class="card" style="margin-top:22px;max-width:680px"><div class="card-head"><h3>Record</h3></div>
<form method="POST" action="{{ route('salary.store') }}">@csrf
<div class="form-grid">
<div><label class="f">Staff <span class="req">*</span></label><select class="in" name="user_id" required>@foreach($who as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
<div><label class="f">Type</label><select class="in" name="kind"><option value="advance">Advance given</option><option value="recovery">Recovery (salary cut)</option></select></div>
</div>
<div class="form-grid">
<div><label class="f">Amount (₹) <span class="req">*</span></label><input class="in" type="number" step="0.01" min="0.01" name="amount" required></div>
<div><label class="f">Date</label><input class="in" type="date" name="dated" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required></div>
</div>
<label class="f">Note</label><input class="in" name="note" maxlength="255" placeholder="e.g. Festival advance, recovered March salary">
<div class="form-actions"><button class="btn sm" type="submit">Record</button></div></form></div>
@endif
@endsection
