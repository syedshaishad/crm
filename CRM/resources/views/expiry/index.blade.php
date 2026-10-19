@extends('layouts.app')
@section('title','Compliance')
@section('content')
<div class="grid stats cols-4">
  <div class="stat acc" style="--acc:#30d158"><div class="lbl">Valid</div><b>{{ $stats['valid'] }}</b><div class="sub">in date</div></div>
  <div class="stat acc" style="--acc:#ff9f0a"><div class="lbl">Expiring soon</div><b>{{ $stats['expiring'] }}</b><div class="sub">within alert window</div></div>
  <div class="stat acc" style="--acc:#ff453a"><div class="lbl">Expired</div><b>{{ $stats['expired'] }}</b><div class="sub">needs renewal</div></div>
</div>

<div class="card" style="margin-top:22px">
<div class="card-head"><h3>Tracked documents</h3><a class="btn sm" href="{{ route('expiry.create') }}">+ New</a></div>
<form method="GET" data-ajax-filter data-region="expiry-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search title, ref no, authority…"><select class="in" style="margin:0;flex:1;min-width:130px" name="category"><option value="">All types</option>@foreach(\Modules\Expiry\ExpiryDocument::categories() as $ck=>$cl)<option value="{{ $ck }}" {{ ($category ?? '')===$ck?'selected':'' }}>{{ $cl }}</option>@endforeach</select><select class="in" style="margin:0;flex:1;min-width:130px" name="state"><option value="">All states</option>@foreach(['valid','expiring','expired'] as $st)<option value="{{ $st }}" {{ ($state ?? '')===$st?'selected':'' }}>{{ ucfirst($st) }}</option>@endforeach</select><button class="btn sec sm">Search</button></form>
<div data-ajax-region="expiry-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th>Number</th><th>Title</th><th>Type</th><th>Ref / authority</th><th>Valid to</th><th>Status</th><th></th></tr>
@forelse($docs as $x)<tr><td class="nowrap"><a href="{{ route('expiry.show',$x) }}"><b>{{ $x->number }}</b></a></td><td>{{ $x->title }}</td><td><span class="badge b-grey">{{ \Modules\Expiry\ExpiryDocument::categories()[$x->category] ?? $x->category }}</span></td><td>{{ $x->ref_no ?: '—' }}@if($x->authority)<div class="hint">{{ $x->authority }}</div>@endif</td><td class="nowrap">{{ d($x->valid_to) ?: '—' }}</td><td>@php $st = $x->state(); @endphp<span class="badge {{ $st==='valid' ? 'b-green' : ($st==='expiring' ? 'b-amber' : ($st==='expired' ? 'b-red' : 'b-grey')) }}">{{ ucfirst($st) }}</span></td><td style="text-align:right"><a class="btn sm sec" href="{{ route('expiry.show',$x) }}">Open</a></td></tr>@empty<tr><td colspan="7"><div class="empty">Nothing tracked yet — add GST filings, licenses, insurance.</div></td></tr>@endforelse</table></div>
{{ $docs->links() }}</div></div>
@endsection
