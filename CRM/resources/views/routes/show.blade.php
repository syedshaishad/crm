@extends('layouts.app')
@section('title',$route->number)
@section('content')
<x-page-header :title="$route->number" :crumb="d($route->route_date, 'j M Y').($route->vehicle_no ? ' · '.$route->vehicle_no : '')" icon='<path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>' back="route('routes.index')">
  
</x-page-header>
<div class="noprint" style="margin-bottom:14px">
  <form method="POST" action="{{ route('routes.destroy',$route) }}" style="display:inline" data-confirm="Delete {{ $route->number }}? Challans stay untouched.">@csrf @method('DELETE')<button class="btn sm danger">Delete route</button></form>
</div>
<div class="card"><div class="card-head"><h3>Stops · {{ $progress['done'] }}/{{ $progress['total'] }}</h3></div>
<div class="progress" style="margin:0 0 12px"><div style="width:{{ $progress['pct'] }}%"></div></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th style="width:36px">#</th><th>Challan</th><th>Deliver to</th><th></th></tr>
@forelse($route->stops as $i=>$s)<tr @if($s->done_at) style="opacity:.55" @endif><td><b>{{ $i + 1 }}</b></td><td class="nowrap"><a href="{{ route('challans.show',$s->challan) }}"><b>{{ $s->challan->number ?? '—' }}</b></a>@if($s->done_at)<div class="hint">done {{ d($s->done_at, 'h:i A') }}</div>@endif</td><td>{{ $s->challan->client->company ?? $s->challan->client->name ?? '—' }}<div class="hint">{{ $s->challan->client->address ?? '' }}</div></td>
<td class="noprint" style="text-align:right;white-space:nowrap"><form method="POST" action="{{ route('routes.toggle',[$route,$s]) }}" style="display:inline">@csrf<button class="btn sm {{ $s->done_at ? 'sec' : '' }}">{{ $s->done_at ? 'Reopen' : 'Delivered' }}</button></form>
<form method="POST" action="{{ route('routes.move',[$route,$s]) }}" style="display:inline">@csrf<input type="hidden" name="dir" value="up"><button class="btn sm sec" title="Earlier">↑</button></form>
<form method="POST" action="{{ route('routes.move',[$route,$s]) }}" style="display:inline">@csrf<input type="hidden" name="dir" value="down"><button class="btn sm sec" title="Later">↓</button></form></td></tr>
@empty<tr><td colspan="4"><div class="empty">No stops.</div></td></tr>@endforelse</table></div></div>
@endsection
