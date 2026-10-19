@extends('portal.layout')
@section('title','My invoices')
@section('content')
<div class="page-head"><h1>Invoices</h1><div class="crumb">Open a document to view, download or pay it.</div></div>
<div class="card">
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Number</th><th>Issued</th><th>Due</th><th style="text-align:right">Total</th><th style="text-align:right">Balance</th><th>Status</th><th></th></tr>
@forelse($invoices as $i)<tr><td><b>{{ $i->number }}</b></td><td class="nowrap">{{ d($i->issue_date) }}</td><td class="nowrap">{{ d($i->due_date) }}</td><td class="nowrap" style="text-align:right">{{ money($i->total) }}</td><td class="nowrap" style="text-align:right"><b>{{ money($i->balance()) }}</b></td><td><span class="badge {{ $i->status==='paid' ? 'b-green' : ($i->status==='overdue' ? 'b-red' : (in_array($i->status,['sent','partial']) ? 'b-blue' : 'b-grey')) }}">{{ $i->status }}</span></td><td style="text-align:right">@if($i->public_token)<a class="btn sm sec" href="{{ route('invoices.public',$i->public_token) }}">View / pay</a>@else<span class="hint">—</span>@endif</td></tr>@empty<tr><td colspan="7"><div class="empty">No invoices yet.</div></td></tr>@endforelse</table></div>
{{ $invoices->links() }}</div>
@endsection
