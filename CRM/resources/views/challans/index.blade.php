@extends('layouts.app')
@section('title','Delivery challans')
@section('content')
<div class="card">
<div class="card-head"><h3>Delivery challans</h3><a class="btn sm" href="{{ route('challans.create') }}">+ New</a></div>
<form method="GET" data-ajax-filter data-region="dc-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search number, client…"><select class="in" style="margin:0;flex:1;min-width:130px" name="status"><option value="">All statuses</option>@foreach(['draft','issued','cancelled'] as $st)<option value="{{ $st }}" {{ ($status ?? '')===$st?'selected':'' }}>{{ ucfirst($st) }}</option>@endforeach</select><button class="btn sec sm">Search</button></form>
<div data-ajax-region="dc-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th>Number</th><th>Client</th><th>Date</th><th style="text-align:right">Qty</th><th style="text-align:right">Value</th><th>Status</th><th></th></tr>
@forelse($challans as $c)<tr><td class="nowrap"><a href="{{ route('challans.show',$c) }}"><b>{{ $c->number }}</b></a></td><td>{{ $c->client->company ?? $c->client->name ?? '—' }}</td><td class="nowrap">{{ d($c->challan_date) }}</td><td class="nowrap" style="text-align:right">{{ $c->total_qty }}</td><td class="nowrap" style="text-align:right"><b>{{ money($c->subtotal) }}</b></td><td><span class="badge {{ $c->status==='issued' ? 'b-green' : ($c->status==='cancelled' ? 'b-grey' : 'b-blue') }}">{{ ucfirst($c->status) }}</span></td><td style="text-align:right"><a class="btn sm sec" href="{{ route('challans.show',$c) }}">Open</a></td></tr>@empty<tr><td colspan="7"><div class="empty">No challans yet.</div></td></tr>@endforelse</table></div>
{{ $challans->links() }}</div></div>
@endsection
