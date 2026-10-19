@extends('portal.layout')
@section('title','My proposals')
@section('content')
<div class="page-head"><h1>Proposals</h1><div class="crumb">Review and sign pending proposals.</div></div>
<div class="card">
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Number</th><th>Valid until</th><th style="text-align:right">Total</th><th>Status</th><th></th></tr>
@forelse($proposals as $p)<tr><td><b>{{ $p->number }}</b></td><td class="nowrap">{{ d($p->valid_until) }}</td><td class="nowrap" style="text-align:right">{{ money($p->total) }}</td><td><span class="badge {{ $p->status==='accepted' ? 'b-green' : ($p->status==='sent' ? 'b-blue' : ($p->status==='declined' ? 'b-red' : 'b-grey')) }}">{{ $p->displayStatus() }}</span></td><td style="text-align:right">@if($p->status==='sent' && $p->sign_token)<a class="btn sm" href="{{ route('proposals.sign',$p->sign_token) }}">Review & sign</a>@else<span class="hint">—</span>@endif</td></tr>@empty<tr><td colspan="5"><div class="empty">No proposals yet.</div></td></tr>@endforelse</table></div>
{{ $proposals->links() }}</div>
@endsection
