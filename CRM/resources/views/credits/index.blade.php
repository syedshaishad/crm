@extends('layouts.app')
@section('title','Credit notes')
@section('content')
<div class="card">
<div class="card-head"><h3>Credit notes</h3><div class="btnrow" style="gap:8px"><a class="btn sm sec" href="{{ route('invoices.index') }}">Invoices</a><a class="btn sm" href="{{ route('credits.create') }}">+ New</a></div></div>
<p class="fhint">Goodwill refunds, rate corrections, over-billing fixes. Apply against open invoices — books a credit payment.</p>
<form method="GET" data-ajax-filter data-region="credits-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search number or client…"><select class="in" style="margin:0;flex:1;min-width:130px" name="status"><option value="">All statuses</option>@foreach(['open','applied'] as $s)<option {{ ($status ?? '')===$s?'selected':'' }}>{{ $s }}</option>@endforeach</select><button class="btn sec sm">Filter</button></form>
<div data-ajax-region="credits-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th>Number</th><th>Client</th><th style="text-align:right">Amount</th><th style="text-align:right">Applied</th><th>Status</th><th></th></tr>
@forelse($notes as $n)<tr><td class="nowrap"><a href="{{ route('credits.show',$n) }}">{{ $n->number }}</a></td><td>{{ $n->client->company ?: ($n->client->name ?? '') }}</td><td class="nowrap" style="text-align:right">{{ money($n->amount) }}</td><td class="nowrap" style="text-align:right">{{ money($n->applied_amount) }}</td><td><span class="badge {{ $n->status==='applied' ? 'b-green' : 'b-amber' }}">{{ $n->status }}</span></td>
<td><div class="row-actions"><a class="icon-btn" href="{{ route('credits.show',$n) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>View</a>
<form method="POST" action="{{ route('credits.destroy',$n) }}" style="display:inline" data-confirm="Delete credit note {{ $n->number }}?">@csrf @method('DELETE')<button class="icon-btn danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>Delete</button></form></div></td></tr>
@empty<tr><td colspan="6"><div class="empty">No credit notes yet.</div></td></tr>@endforelse</table></div>
{{ $notes->links() }}</div></div>
@endsection
