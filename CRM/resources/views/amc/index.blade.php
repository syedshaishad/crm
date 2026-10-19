@extends('layouts.app')
@section('title','AMC & warranty')
@section('content')
<div class="grid stats cols-4">
  <div class="stat acc" style="--acc:#30d158"><div class="lbl">Active</div><b>{{ $stats['active'] }}</b><div class="sub">covered now</div></div>
  <div class="stat acc" style="--acc:#ff9f0a"><div class="lbl">Expiring ≤ 30 days</div><b>{{ $stats['expiring'] }}</b><div class="sub">renew soon</div></div>
  <div class="stat acc" style="--acc:#ff453a"><div class="lbl">Expired</div><b>{{ $stats['expired'] }}</b><div class="sub">needs renewal</div></div>
</div>

<div class="card" style="margin-top:22px">
<div class="card-head"><h3>Contracts</h3><a class="btn sm" href="{{ route('amc.create') }}">+ New</a></div>
<form method="GET" data-ajax-filter data-region="amc-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search number, title, client…"><select class="in" style="margin:0;flex:1;min-width:130px" name="state"><option value="">All states</option>@foreach(['active','expiring','expired'] as $st)<option value="{{ $st }}" {{ ($state ?? '')===$st?'selected':'' }}>{{ ucfirst($st) }}</option>@endforeach</select><button class="btn sec sm">Search</button></form>
<div data-ajax-region="amc-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th>Number</th><th>Title</th><th>Client</th><th>Type</th><th>Ends</th><th style="text-align:right">Value</th><th>Status</th><th></th></tr>
@forelse($contracts as $c)<tr><td class="nowrap"><a href="{{ route('amc.show',$c) }}"><b>{{ $c->number }}</b></a></td><td>{{ $c->title }}</td><td>{{ $c->client->company ?? $c->client->name ?? '—' }}</td><td><span class="badge b-grey">{{ strtoupper($c->type) }}</span></td><td class="nowrap">{{ d($c->end_date) ?: '—' }}</td><td class="nowrap" style="text-align:right">{{ money($c->value) }}</td><td>@php $st = $c->state(); @endphp<span class="badge {{ $st==='active' ? 'b-green' : ($st==='expiring' ? 'b-amber' : ($st==='expired' ? 'b-red' : 'b-grey')) }}">{{ ucfirst($st) }}</span></td><td style="text-align:right"><a class="btn sm sec" href="{{ route('amc.show',$c) }}">Open</a></td></tr>@empty<tr><td colspan="8"><div class="empty">No contracts yet.</div></td></tr>@endforelse</table></div>
{{ $contracts->links() }}</div></div>
@endsection
