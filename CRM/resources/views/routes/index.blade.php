@extends('layouts.app')
@section('title','Delivery routes')
@section('content')
<div class="card">
<div class="card-head"><h3>Delivery routes</h3><a class="btn sm" href="{{ route('routes.create') }}">+ Plan route</a></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Route</th><th>Date</th><th>Vehicle</th><th>Stops</th><th>Progress</th><th>Status</th><th></th></tr>
@forelse($routes as $r)@php $p = $r->progress(); @endphp<tr><td class="nowrap"><a href="{{ route('routes.show',$r) }}"><b>{{ $r->number }}</b></a></td><td class="nowrap">{{ d($r->route_date) }}</td><td>{{ $r->vehicle_no ?: '—' }}@if($r->driver)<div class="hint">{{ $r->driver }}</div>@endif</td><td class="nowrap">{{ $r->stops_count }}</td><td style="min-width:110px"><div class="progress" style="margin:0"><div style="width:{{ $p['pct'] }}%"></div></div><div class="hint">{{ $p['done'] }}/{{ $p['total'] }}</div></td><td><span class="badge {{ $r->status==='done' ? 'b-green' : ($r->status==='on_route' ? 'b-blue' : 'b-grey') }}">{{ \Modules\Challans\DeliveryRoute::statuses()[$r->status] ?? $r->status }}</span></td><td style="text-align:right"><a class="btn sm sec" href="{{ route('routes.show',$r) }}">Open</a></td></tr>@empty<tr><td colspan="7"><div class="empty">No routes yet — plan one from issued challans.</div></td></tr>@endforelse</table></div>
{{ $routes->links() }}</div>
@endsection
