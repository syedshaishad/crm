@extends('layouts.app')
@section('title','Purchase orders')
@section('content')
<div class="card">
<div class="card-head"><h3>Purchase orders</h3><a class="btn sm" href="{{ route('purchase-orders.create') }}">+ New</a></div>
<form method="GET" data-ajax-filter data-region="pos-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search number, vendor…"><select class="in" style="margin:0;flex:1;min-width:130px" name="status"><option value="">All statuses</option>@foreach(['draft','sent','received','cancelled'] as $st)<option value="{{ $st }}" {{ ($status ?? '')===$st?'selected':'' }}>{{ ucfirst($st) }}</option>@endforeach</select><button class="btn sec sm">Search</button></form>
<div data-ajax-region="pos-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th>Number</th><th>Vendor</th><th>Issued</th><th style="text-align:right">Total</th><th>Status</th><th></th></tr>
@forelse($orders as $o)<tr><td class="nowrap"><a href="{{ route('purchase-orders.show',$o) }}"><b>{{ $o->number }}</b></a></td><td>{{ $o->vendor }}</td><td class="nowrap">{{ d($o->issue_date) }}</td><td class="nowrap" style="text-align:right"><b>{{ money($o->total) }}</b></td><td><span class="badge {{ $o->status==='received' ? 'b-green' : ($o->status==='sent' ? 'b-blue' : 'b-grey') }}">{{ ucfirst($o->status) }}</span></td><td style="text-align:right"><a class="btn sm sec" href="{{ route('purchase-orders.show',$o) }}">Open</a></td></tr>@empty<tr><td colspan="6"><div class="empty">No purchase orders yet.</div></td></tr>@endforelse</table></div>
{{ $orders->links() }}</div></div>
@endsection
