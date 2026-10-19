@extends('portal.layout')
@section('title','Overview')
@section('content')
<div class="page-head"><h1>Hello, {{ explode(' ', auth('client')->user()->name)[0] }}</h1><div class="crumb">{{ $client->company ?: $client->name }} · {{ now()->format('l, j F Y') }}</div></div>
<div class="grid stats">
  <div class="stat acc" style="--acc:#ff453a"><div class="lbl">Outstanding</div><b>{{ money($outstanding) }}</b><div class="sub">{{ $openInvoices }} open invoices</div></div>
  <div class="stat acc" style="--acc:#0a84ff"><div class="lbl">Open invoices</div><b>{{ $openInvoices }}</b><div class="sub"><a class="hint" href="{{ route('portal.invoices') }}">View all →</a></div></div>
  <div class="stat acc" style="--acc:#bf5af2"><div class="lbl">Awaiting signature</div><b>{{ $pendingProposals }}</b><div class="sub"><a class="hint" href="{{ route('portal.proposals') }}">Review →</a></div></div>
</div>
<div class="cols-2">
<div class="card" style="margin-top:22px"><div class="card-head"><h3>Recent invoices</h3><a class="hint" href="{{ route('portal.invoices') }}">All →</a></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Number</th><th style="text-align:right">Balance</th><th>Status</th></tr>
@forelse($recentInvoices as $i)<tr><td>@if($i->public_token)<a href="{{ route('invoices.public',$i->public_token) }}">{{ $i->number }}</a>@else{{ $i->number }}@endif</td><td style="text-align:right">{{ money($i->balance()) }}</td><td><span class="badge {{ $i->status==='paid' ? 'b-green' : ($i->status==='overdue' ? 'b-red' : (in_array($i->status,['sent','partial']) ? 'b-blue' : 'b-grey')) }}">{{ $i->status }}</span></td></tr>@empty<tr><td colspan="3"><div class="empty">No invoices yet.</div></td></tr>@endforelse</table></div></div>
<div class="card" style="margin-top:22px"><div class="card-head"><h3>Recent proposals</h3><a class="hint" href="{{ route('portal.proposals') }}">All →</a></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Number</th><th style="text-align:right">Total</th><th></th></tr>
@forelse($recentProposals as $p)<tr><td>{{ $p->number }}</td><td style="text-align:right">{{ money($p->total) }}</td><td style="text-align:right">@if($p->status==='sent' && $p->sign_token)<a class="btn sm" href="{{ route('proposals.sign',$p->sign_token) }}">Review & sign</a>@else<span class="badge b-grey">{{ $p->status }}</span>@endif</td></tr>@empty<tr><td colspan="3"><div class="empty">No proposals yet.</div></td></tr>@endforelse</table></div></div>
</div>
@endsection
