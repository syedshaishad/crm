@extends('portal.layout')
@section('title','My receipts')
@section('content')
<div class="page-head"><h1>Receipts</h1><div class="crumb">Every payment received on your account.</div></div>
<div class="card">
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Receipt</th><th>Paid on</th><th>Invoice</th><th style="text-align:right">Amount</th><th></th></tr>
@forelse($receipts as $p)<tr><td><b>{{ $p->number ?? '-' }}</b><div class="hint">{{ ucfirst($p->method) }}</div></td><td class="nowrap">{{ d($p->paid_on) }}</td><td class="nowrap">{{ $p->invoice->number ?? 'Advance' }}</td><td class="nowrap" style="text-align:right"><b>{{ money($p->amount) }}</b></td><td style="text-align:right">@if($p->public_token)<a class="btn sm sec" href="{{ route('payments.public',$p->public_token) }}">Download</a>@else<span class="hint">—</span>@endif</td></tr>@empty<tr><td colspan="5"><div class="empty">No payments yet.</div></td></tr>@endforelse</table></div>
{{ $receipts->links() }}</div>
@endsection
