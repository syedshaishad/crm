@extends('layouts.app')
@section('title',$challan->number)
@section('content')
<div class="page-head"><h1>{{ $challan->number }}</h1><div class="crumb">{{ $challan->client->company ?? $challan->client->name ?? '' }} · {{ ucfirst($challan->status) }}</div></div>
<div class="noprint" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
  @if($challan->status==='draft')<a class="btn sm sec" href="{{ route('challans.edit',$challan) }}">Edit</a>
  <form method="POST" action="{{ route('challans.issue',$challan) }}" data-ajax-post data-refresh="page" style="display:inline">@csrf<button class="btn sm">Mark issued</button></form>@endif
  @if($challan->status!=='issued')<form method="POST" action="{{ route('challans.destroy',$challan) }}" style="display:inline" data-confirm="Delete challan {{ $challan->number }}?">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form>@endif
  @if($challan->invoice_id)<a class="btn sm sec" href="{{ route('invoices.show',$challan->invoice_id) }}">View invoice</a>@endif
  <button class="btn sm sec" onclick="window.print()">Print / PDF</button>
</div>

<div class="card">
<div class="doc-head" style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:12px">
<div><div class="cap">Delivery challan</div><h2 style="margin:0">{{ $challan->number }}</h2><div class="sub">{{ d($challan->challan_date) }}@if($challan->place_of_supply) · {{ $challan->place_of_supply }}@endif</div></div>
<div style="text-align:right"><div class="cap">Deliver to</div><b>{{ $challan->client->company ?? $challan->client->name }}</b>@if($challan->client->address)<div class="sub">{{ $challan->client->address }}</div>@endif</div>
</div>
@if($challan->vehicle_no || $challan->transporter || $challan->eway_bill)
<div class="bill-grid" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px">
@if($challan->vehicle_no)<div class="bill-box"><div class="cap">Vehicle</div><div class="sub">{{ $challan->vehicle_no }}</div></div>@endif
@if($challan->transporter)<div class="bill-box"><div class="cap">Transporter</div><div class="sub">{{ $challan->transporter }}</div></div>@endif
@if($challan->eway_bill)<div class="bill-box"><div class="cap">E-way bill</div><div class="sub">{{ $challan->eway_bill }}</div></div>@endif
</div>
@endif
<div class="tbl-scroll roomy"><table class="tbl"><tr><th style="width:4%">#</th><th>Description</th><th>HSN/SAC</th><th>Qty</th><th>Unit</th><th style="text-align:right">Rate</th><th style="text-align:right">Amount</th></tr>
@foreach($challan->items as $i => $it)<tr><td>{{ $i + 1 }}</td><td>{{ $it->description }}</td><td class="nowrap">{{ $it->hsn_code ?: '—' }}</td><td class="nowrap">{{ $it->qty }}</td><td class="nowrap">{{ $it->unit }}</td><td class="nowrap" style="text-align:right">{{ money($it->rate) }}</td><td class="nowrap" style="text-align:right">{{ money($it->amount) }}</td></tr>@endforeach</table></div>
<div class="tot-band"><div class="tot-row"><span>Total qty</span><span>{{ $challan->total_qty }}</span></div><div class="tot-row grand"><span>Value</span><span>{{ money($challan->subtotal) }}</span></div></div>
@if($challan->notes)<div class="bill-box" style="margin-top:14px"><div class="cap">Notes</div><div class="sub" style="white-space:pre-line">{{ $challan->notes }}</div></div>@endif
</div>
@endsection
