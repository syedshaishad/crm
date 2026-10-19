@extends('layouts.app')
@section('title','Payroll')
@section('content')
<div class="card">
<div class="card-head"><h3>Payroll runs</h3></div>
<p class="fhint">Draft from live attendance, leave, overtime, bonuses, advances and loans — review, then finalize to post salaries + TDS to the books.</p>
<form method="POST" action="{{ route('payroll.run') }}" class="noprint" style="display:flex;gap:8px;align-items:end;margin-bottom:12px">@csrf
<div><label class="f">Month</label><input class="in" type="month" name="month" value="{{ date('Y-m') }}" style="margin:0" required></div>
<div><button class="btn sm">Run payroll</button></div></form>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Month</th><th>Status</th><th style="text-align:right">Net paid</th><th style="text-align:right">TDS</th><th></th></tr>
@forelse($rows as $r)<tr>
<td><b>{{ $r->label() }}</b></td>
<td><span class="badge {{ $r->status==='finalized' ? 'b-green' : 'b-amber' }}">{{ ucfirst($r->status) }}</span></td>
<td class="nowrap" style="text-align:right">{{ money($r->slips->sum('net')) }}</td>
<td class="nowrap" style="text-align:right">{{ money($r->slips->sum('tds')) }}</td>
<td style="text-align:right"><a class="btn sm sec" href="{{ route('payroll.show',$r) }}">Open</a></td></tr>
@empty<tr><td colspan="5"><div class="empty">No runs yet — pick a month above.</div></td></tr>@endforelse</table></div>
{{ $rows->links() }}</div>
@endsection
