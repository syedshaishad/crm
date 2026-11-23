@extends('layouts.app')
@section('title','Compliance')
@section('content')
<x-page-header title="Statutory compliance" :crumb="$docs->total().' tracked documents · '.$stats['expired'].' expired'" icon='<rect x="4" y="4" width="16" height="16" rx="2"/><path d="m8.5 12.5 2.5 2.5 5-5.5"/><path d="M8 2v4M16 2v4"/>'>
  <a class="btn sm" href="{{ route('expiry.create') }}">+ New</a>
</x-page-header>

<div class="grid stats">
  <div class="stat"><div class="lbl">Valid</div><b>{{ $stats['valid'] }}</b><div class="sub">in date</div></div>
  <div class="stat"><div class="lbl">Expiring soon</div><b>{{ $stats['expiring'] }}</b><div class="sub">within alert window</div></div>
  <div class="stat"><div class="lbl">Expired</div><b>{{ $stats['expired'] }}</b><div class="sub">needs renewal</div></div>
</div>

<div class="card ix-card">
<div class="card-head"><h2>Tracked documents</h2><span class="hint">{{ $docs->total() }} on record</span></div>
<form method="GET" data-ajax-filter data-region="expiry-list" class="noprint rp-filter" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search title, ref no, authority…" aria-label="Search expiry documents"><select class="in" style="margin:0;flex:1;min-width:130px" name="category" aria-label="Filter by type"><option value="">All types</option>@foreach(\Modules\Expiry\ExpiryDocument::categories() as $ck=>$cl)<option value="{{ $ck }}" {{ ($category ?? '')===$ck?'selected':'' }}>{{ $cl }}</option>@endforeach</select><select class="in" style="margin:0;flex:1;min-width:130px" name="state" aria-label="Filter by state"><option value="">All states</option>@foreach(['valid','expiring','expired'] as $st)<option value="{{ $st }}" {{ ($state ?? '')===$st?'selected':'' }}>{{ ucfirst($st) }}</option>@endforeach</select><button class="btn sec sm">Search</button></form>
<div data-ajax-region="expiry-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th>Number</th><th>Title</th><th>Type</th><th>Ref / authority</th><th>Valid to</th><th>Status</th><th></th></tr>
@forelse($docs as $x)<tr><td class="nowrap"><a href="{{ route('expiry.show',$x) }}"><b>{{ $x->number }}</b></a></td><td>{{ $x->title }}</td><td><span class="ix-tag">{{ \Modules\Expiry\ExpiryDocument::categories()[$x->category] ?? $x->category }}</span></td><td>{{ $x->ref_no ?: '—' }}@if($x->authority)<div class="hint">{{ $x->authority }}</div>@endif</td><td class="nowrap">{{ d($x->valid_to, 'j M Y') ?: '—' }}</td><td>@php $st = $x->state(); @endphp{!! status_tag(ucfirst($st), ['valid' => 'green', 'expiring' => 'amber', 'expired' => 'red'][$st] ?? 'grey') !!}</td><td style="text-align:right"><a class="btn sm sec" href="{{ route('expiry.show',$x) }}">Open</a></td></tr>@empty<tr><td colspan="7"><div class="empty">Nothing tracked yet — add GST filings, licenses, insurance.</div></td></tr>@endforelse</table></div>
{{ $docs->links() }}</div></div>
@endsection
