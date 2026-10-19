@extends('layouts.app')
@section('title',$cheque->number)
@section('content')
<div class="page-head"><h1>{{ $cheque->number }}</h1><div class="crumb">{{ $cheque->cheque_no }} · {{ money($cheque->amount) }} · <span class="badge {{ $cheque->status==='cleared' ? 'b-green' : ($cheque->status==='bounced' ? 'b-red' : ($cheque->status==='cancelled' ? 'b-grey' : 'b-amber')) }}">{{ \Modules\Cheques\PdcCheque::statuses()[$cheque->status] ?? $cheque->status }}</span>@if($cheque->isDue()) · <b>ready to deposit</b>@endif</div></div>
@if(!in_array($cheque->status,['cleared','cancelled'],true))
<div class="noprint" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center">
  <a class="btn sm sec" href="{{ route('cheques.edit',$cheque) }}">Edit</a>
  @if($cheque->status==='held')<form method="POST" action="{{ route('cheques.deposit',$cheque) }}" style="display:inline-flex;gap:6px;align-items:center" data-confirm="Deposit cheque {{ $cheque->cheque_no }}?">@csrf<input class="in" type="date" name="deposited_on" value="{{ date('Y-m-d') }}" style="margin:0;width:auto" required><button class="btn sm">Deposit</button></form>@endif
  @if(in_array($cheque->status,['held','deposited'],true))<form method="POST" action="{{ route('cheques.clear',$cheque) }}" style="display:inline-flex;gap:6px;align-items:center" data-confirm="Mark {{ $cheque->cheque_no }} cleared? This books the payment.">@csrf<input class="in" type="date" name="cleared_on" value="{{ date('Y-m-d') }}" style="margin:0;width:auto" required><button class="btn sm">Clear</button></form>@endif
  @if(in_array($cheque->status,['held','deposited'],true))<form method="POST" action="{{ route('cheques.bounce',$cheque) }}" style="display:inline" data-confirm="Mark {{ $cheque->cheque_no }} bounced?">@csrf<button class="btn sm danger">Bounce</button></form>
  <form method="POST" action="{{ route('cheques.cancel',$cheque) }}" style="display:inline" data-confirm="Cancel {{ $cheque->number }}?">@csrf<button class="btn sm sec">Cancel cheque</button></form>@endif
  <form method="POST" action="{{ route('cheques.destroy',$cheque) }}" style="display:inline" data-confirm="Delete {{ $cheque->number }}?">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form>
</div>
@endif

<div class="card">
<div class="cols-2">
<div>
  <div class="kv"><span class="k">From</span><span><a href="{{ route('clients.show',$cheque->client) }}">{{ $cheque->client->company ?? $cheque->client->name }}</a></span></div>
  <div class="kv"><span class="k">Invoice</span><span>@if($cheque->invoice_id)<a href="{{ route('invoices.show',$cheque->invoice_id) }}">{{ $cheque->invoice->number ?? '' }}</a>@else—@endif</span></div>
  <div class="kv"><span class="k">Bank</span><span>{{ $cheque->bank ?: '—' }}</span></div>
  <div class="kv"><span class="k">Cheque date</span><span>{{ d($cheque->cheque_date,'j M Y') }}</span></div>
</div>
<div>
  <div class="kv"><span class="k">Amount</span><span><b>{{ money($cheque->amount) }}</b></span></div>
  <div class="kv"><span class="k">Deposited</span><span>{{ d($cheque->deposited_on,'j M Y') ?: '—' }}</span></div>
  <div class="kv"><span class="k">Cleared</span><span>@if($cheque->payment_id)<a href="{{ route('payments.show',$cheque->payment_id) }}">{{ d($cheque->cleared_on,'j M Y') }}</a>@else{{ d($cheque->cleared_on,'j M Y') ?: '—' }}@endif</span></div>
  <div class="kv"><span class="k">Notes</span><span style="white-space:pre-line">{{ $cheque->notes ?: '—' }}</span></div>
</div>
</div>
</div>
@endsection
