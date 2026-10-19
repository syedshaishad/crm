@extends('layouts.app')
@section('title',$order->number)
@section('content')
<div class="page-head"><h1>{{ $order->number }}</h1><div class="crumb">{{ $order->vendor }} · {{ ucfirst($order->status) }}</div></div>
<div class="noprint" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
  @if($order->status==='draft')<a class="btn sm sec" href="{{ route('purchase-orders.edit',$order) }}">Edit</a>
  <form method="POST" action="{{ route('purchase-orders.send',$order) }}" data-ajax-post data-refresh="page" style="display:inline">@csrf<button class="btn sm">Mark sent</button></form>@endif
  @if($order->status==='sent')<form method="POST" action="{{ route('purchase-orders.receive',$order) }}" style="display:inline" data-confirm="Book this as an expense and mark received?">@csrf<button class="btn sm brand">Receive + book expense</button></form>@endif
  @if(!in_array($order->status,['received']))<form method="POST" action="{{ route('purchase-orders.destroy',$order) }}" style="display:inline" data-confirm="Delete order {{ $order->number }}?">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form>@endif
  @if($order->converted_expense_id)<a class="btn sm sec" href="{{ route('expenses.show',$order->converted_expense_id) }}">View expense</a>@endif
</div>

<div class="card">
<div class="tbl-scroll roomy"><table class="tbl"><tr><th style="width:4%">#</th><th>Description</th><th>HSN/SAC</th><th>Qty</th><th>Unit</th><th style="text-align:right">Rate</th><th style="text-align:right">GST%</th><th style="text-align:right">Amount</th></tr>
@foreach($order->items as $i => $it)<tr><td>{{ $i + 1 }}</td><td>{{ $it->description }}</td><td class="nowrap">{{ $it->hsn_code ?: '—' }}</td><td class="nowrap">{{ $it->qty }}</td><td class="nowrap">{{ $it->unit }}</td><td class="nowrap" style="text-align:right">{{ money($it->rate) }}</td><td class="nowrap" style="text-align:right">{{ $it->tax_rate }}%</td><td class="nowrap" style="text-align:right">{{ money($it->amount) }}</td></tr>@endforeach</table></div>
<div class="tot-band"><div class="tot-row"><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div><div class="tot-row"><span>GST</span><span>{{ money($order->gst_amount) }}</span></div><div class="tot-row grand"><span>Total</span><span>{{ money($order->total) }}</span></div></div>
<div class="words">Vendor: {{ $order->vendor }}@if($order->vendor_email) · {{ $order->vendor_email }} @endif @if($order->expected_on) · expected {{ d($order->expected_on) }} @endif</div>
@if($order->notes)<div class="bill-box" style="margin-top:14px"><div class="cap">Notes</div><div class="sub" style="white-space:pre-line">{{ $order->notes }}</div></div>@endif
</div>
@endsection
