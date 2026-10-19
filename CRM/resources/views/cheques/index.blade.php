@extends('layouts.app')
@section('title','Cheques')
@section('content')
<div class="grid stats cols-4">
  <div class="stat acc" style="--acc:#0a84ff"><div class="lbl">Held value</div><b>{{ money($stats['held']) }}</b><div class="sub">in the drawer</div></div>
  <div class="stat acc" style="--acc:#ff9f0a"><div class="lbl">Ready to deposit</div><b>{{ $stats['due'] }}</b><div class="sub">date reached</div></div>
  <div class="stat acc" style="--acc:#ff453a"><div class="lbl">Bounced</div><b>{{ $stats['bounced'] }}</b><div class="sub">needs follow-up</div></div>
</div>

<div class="card" style="margin-top:22px">
<div class="card-head"><h3>Post-dated cheques</h3><a class="btn sm" href="{{ route('cheques.create') }}">+ New</a></div>
<form method="GET" data-ajax-filter data-region="cheque-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search number, cheque no, bank, client…"><select class="in" style="margin:0;flex:1;min-width:130px" name="status"><option value="">All states</option>@foreach(\Modules\Cheques\PdcCheque::statuses() as $sk=>$sl)<option value="{{ $sk }}" {{ ($status ?? '')===$sk?'selected':'' }}>{{ $sl }}</option>@endforeach</select><button class="btn sec sm">Search</button></form>
<div data-ajax-region="cheque-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th>Number</th><th>Client</th><th>Cheque</th><th>Date</th><th style="text-align:right">Amount</th><th>Status</th><th></th></tr>
@forelse($cheques as $c)<tr><td class="nowrap"><a href="{{ route('cheques.show',$c) }}"><b>{{ $c->number }}</b></a></td><td>{{ $c->client->company ?? $c->client->name ?? '—' }}@if($c->invoice_id)<div class="hint">{{ $c->invoice->number ?? '' }}</div>@endif</td><td class="nowrap">{{ $c->cheque_no }}@if($c->bank)<div class="hint">{{ $c->bank }}</div>@endif</td><td class="nowrap">{{ d($c->cheque_date) }}@if($c->isDue())<div class="hint" style="color:var(--warn,orange)">deposit now</div>@endif</td><td class="nowrap" style="text-align:right"><b>{{ money($c->amount) }}</b></td><td><span class="badge {{ $c->status==='cleared' ? 'b-green' : ($c->status==='bounced' ? 'b-red' : ($c->status==='cancelled' ? 'b-grey' : 'b-amber')) }}">{{ \Modules\Cheques\PdcCheque::statuses()[$c->status] ?? $c->status }}</span></td><td style="text-align:right"><a class="btn sm sec" href="{{ route('cheques.show',$c) }}">Open</a></td></tr>@empty<tr><td colspan="7"><div class="empty">No cheques on record.</div></td></tr>@endforelse</table></div>
{{ $cheques->links() }}</div></div>
@endsection
