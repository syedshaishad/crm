@extends('layouts.app')
@section('title',$note->number)
@section('content')
<div class="inv-hero noprint">
  <div><div class="inv-num">{{ $note->number }}</div>
    <div class="inv-chips">
      <span class="badge {{ $note->status==='applied' ? 'b-green' : 'b-amber' }}">{{ $note->status }}</span>
      <span class="chip">Client: <a href="{{ route('clients.show',$note->client) }}">{{ $note->client->company ?? $note->client->name }}</a></span>
      @if($note->invoice)<span class="chip">Linked: <a href="{{ route('invoices.show',$note->invoice) }}">{{ $note->invoice->number }}</a></span>@endif
    </div></div>
  <div class="btnrow">
    @if($note->applied_amount <= 0)
    <form method="POST" action="{{ route('credits.destroy',$note) }}" style="display:inline" data-confirm="Delete credit note {{ $note->number }}?">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form>
    @endif
  </div>
</div>
<div class="grid stats cols-4">
  <div class="stat"><div class="lbl">Credit amount</div><b>{{ money($note->amount) }}</b><div class="sub">{{ d($note->date) }}</div></div>
  <div class="stat"><div class="lbl">Applied</div><b>{{ money($note->applied_amount) }}</b><div class="sub">as credit payments</div></div>
  <div class="stat"><div class="lbl">Remaining</div><b>{{ money($note->remaining()) }}</b><div class="sub">left to apply</div></div>
  <div class="stat"><div class="lbl">Reason</div><b style="font-size:15px">{{ $note->reason ?: '—' }}</b><div class="sub">{{ $note->notes ?: '' }}</div></div>
</div>
@if($note->remaining() > 0)
<div class="card noprint" style="margin-top:22px"><div class="card-head"><h3>Apply credit</h3></div>
<form method="POST" action="{{ route('credits.apply',$note) }}" data-ajax-post data-refresh="page" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">@csrf
<div style="flex:2;min-width:180px"><label class="f">Open invoice</label><select class="in" style="margin:0" name="invoice_id" required>@forelse($openInvoices as $i)<option value="{{ $i->id }}" {{ (string)$note->invoice_id===(string)$i->id?'selected':'' }}>{{ $i->number }} — due {{ money(max(0,$i->total-$i->paid_amount)) }}</option>@empty<option value="" disabled selected>No open invoices for this client</option>@endforelse</select></div>
<div style="flex:1;min-width:130px"><label class="f">Amount (₹)</label><input class="in" style="margin:0" type="number" step="0.01" min="0.01" name="amount" value="{{ $note->remaining() }}" required></div>
<div><button class="btn sm">Apply</button></div></form></div>
@endif
@endsection
