@extends('layouts.app')
@section('title',$contract->number)
@section('content')
<div class="page-head"><h1>{{ $contract->number }}</h1><div class="crumb">{{ $contract->title }} · {{ strtoupper($contract->type) }} · <span class="badge {{ $state==='active' ? 'b-green' : ($state==='expiring' ? 'b-amber' : ($state==='expired' ? 'b-red' : 'b-grey')) }}">{{ ucfirst($state) }}</span></div></div>
<div class="noprint" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
  <a class="btn sm sec" href="{{ route('amc.edit',$contract) }}">Edit</a>
  <form method="POST" action="{{ route('amc.renew',$contract) }}" style="display:inline" data-confirm="Renew for 12 months from {{ d($contract->end_date) ?: 'today' }}?">@csrf<button class="btn sm">Renew 12 months</button></form>
  <form method="POST" action="{{ route('amc.destroy',$contract) }}" style="display:inline" data-confirm="Delete contract {{ $contract->number }}?">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form>
  @if($contract->invoice_id)<a class="btn sm sec" href="{{ route('invoices.show',$contract->invoice_id) }}">View invoice</a>@endif
</div>

<div class="card">
<div class="cols-2">
<div>
  <div class="kv"><span class="k">Client</span><span><a href="{{ route('clients.show',$contract->client) }}">{{ $contract->client->company ?? $contract->client->name }}</a></span></div>
  <div class="kv"><span class="k">Covers</span><span>{{ $contract->product->name ?? '—' }}</span></div>
  <div class="kv"><span class="k">Period</span><span>{{ d($contract->start_date) ?: '—' }} → {{ d($contract->end_date) ?: 'open-ended' }}</span></div>
</div>
<div>
  <div class="kv"><span class="k">Value</span><span>{{ money($contract->value) }}</span></div>
  <div class="kv"><span class="k">Invoice</span><span>@if($contract->invoice_id)<a href="{{ route('invoices.show',$contract->invoice_id) }}">{{ $contract->invoice->number ?? '' }}</a>@else—@endif</span></div>
  <div class="kv"><span class="k">Notes</span><span>{{ $contract->notes ?: '—' }}</span></div>
</div>
</div>
</div>
@endsection
