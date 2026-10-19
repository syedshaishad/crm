@extends('layouts.app')
@section('title','Proposals')
@section('content')
@php
  $statuses = \Modules\Proposals\Proposal::statuses();
  $bColor = ['draft'=>'b-grey','sent'=>'b-blue','accepted'=>'b-green','declined'=>'b-red','expired'=>'b-amber','invoiced'=>'b-green'];
@endphp
<div class="card">
<div class="card-head"><h3>Proposals</h3><span class="btnrow" style="gap:8px"><a class="btn sec sm" href="{{ route('proposals.templates') }}">Templates</a><a class="btn sm" href="{{ route('proposals.create') }}">+ New</a></span></div>
<p class="fhint">Pitch services, get the client's signature on the link, then convert straight to an invoice.</p>
<form method="GET" data-ajax-filter data-region="proposals-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search number or client…"><select class="in" style="margin:0;flex:1;min-width:130px" name="status"><option value="">All statuses</option>@foreach($statuses as $sv=>$sl)<option value="{{ $sv }}" {{ ($status ?? '')===$sv?'selected':'' }}>{{ $sl }}</option>@endforeach</select><button class="btn sec sm">Filter</button>@if(($q ?? '') !== '' || ($status ?? '') !== '')<a class="btn sm sec" href="{{ route('proposals.index') }}">Clear</a>@endif</form>
<div data-ajax-region="proposals-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th>Number</th><th>Client</th><th style="text-align:right">Total</th><th>Valid till</th><th>Status</th><th></th></tr>
@forelse($proposals as $p)<tr><td class="nowrap"><a href="{{ route('proposals.show',$p) }}">{{ $p->number }}</a></td><td>{{ $p->client->company ?: ($p->client->name ?? '') }}@if($p->client->company)<div class="hint">{{ $p->client->name }}</div>@endif</td><td class="nowrap" style="text-align:right">{{ money($p->total) }}</td><td class="nowrap">{{ d($p->valid_until) }}</td><td><span class="badge {{ $bColor[$p->displayStatus()] ?? 'b-grey' }}">{{ $statuses[$p->displayStatus()] ?? $p->status }}</span>@if($p->converted_invoice_id)<div class="hint">→ <a href="{{ route('invoices.show',$p->converted_invoice_id) }}">invoice</a></div>@endif</td>
<td><div class="row-actions"><a class="icon-btn" href="{{ route('proposals.show',$p) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>View</a>@if($p->status==='draft')<a class="icon-btn" href="{{ route('proposals.edit',$p) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Edit</a>@endif
<form method="POST" action="{{ route('proposals.destroy',$p) }}" style="display:inline" data-confirm="Delete proposal {{ $p->number }}?">@csrf @method('DELETE')<button class="icon-btn danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>Delete</button></form></div></td></tr>
@empty<tr><td colspan="6"><div class="empty">No proposals found.<br><br><a class="btn sm" href="{{ route('proposals.create') }}">Create your first proposal</a></div></td></tr>@endforelse</table></div>
{{ $proposals->links() }}</div></div>
@endsection
