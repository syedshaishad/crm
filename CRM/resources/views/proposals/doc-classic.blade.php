  <div class="doc-head">
    <div class="doc-brand">@if(!empty($brand['logo_path']) || !empty($brand['logo_dark_path']))@include('brand.logo', ['cls' => 'doc-logo'])@endif<div><h2>{{ $brand['company_name'] ?? '' }}</h2>@if($brand['tagline'] ?? '')<div class="doc-sub">{{ $brand['tagline'] }}</div>@endif<div class="doc-sub">{{ brand_contact($brand) }}</div></div></div>
    <div class="doc-meta"><h3>PROPOSAL</h3><div class="inv-no">{{ $proposal->number }}</div><div>Date: {{ d($proposal->issue_date, 'j F Y') }}</div>@if($proposal->valid_until)<div>Valid till: {{ d($proposal->valid_until, 'j F Y') }}</div>@endif</div>
  </div>
  <div class="bill-grid">
    <div class="bill-box"><div class="cap">Proposed to</div><b>{{ $proposal->client->company ?: $proposal->client->name }}</b>@if($proposal->client->company)<div class="sub">{{ $proposal->client->name }}</div>@endif @if($proposal->client->email)<div class="sub">{{ $proposal->client->email }}</div>@endif @if($proposal->client->phone)<div class="sub">{{ $proposal->client->phone }}</div>@endif</div>
    <div class="bill-box"><div class="cap">Prepared by</div><b>{{ $proposal->creator->name ?? '—' }}</b>@if($proposal->assignee)<div class="sub">Attention: {{ $proposal->assignee->name }}</div>@endif @if($proposal->place_of_supply)<div class="sub">Place of supply: {{ $proposal->place_of_supply }}</div>@endif<div class="sub">Supply type: {{ $intra ? 'Intra-state (CGST + SGST)' : 'Inter-state (IGST)' }}</div></div>
  </div>
  @if($proposal->notes)<p>{{ $proposal->notes }}</p>@endif
  <div class="tbl-scroll roomy"><table class="tbl"><tr><th style="width:4%">#</th><th style="width:26%">Service</th><th style="width:10%">HSN/SAC</th><th style="width:6%">Qty</th><th style="width:7%">Unit</th><th style="width:14%;text-align:right">Rate</th><th style="width:8%;text-align:right">GST%</th><th style="width:15%;text-align:right">Taxable</th></tr>
  @foreach($proposal->items as $n => $it)<tr><td>{{ $n + 1 }}</td><td>{{ $it->description }}@if(($it->billing ?? 'once') !== 'once') <span class="hint">· {{ $it->billing === 'monthly' ? 'monthly' : 'yearly' }}</span>@endif @if(($it->discount_percent ?? 0) > 0)<span class="hint"> −{{ rtrim(rtrim($it->discount_percent,'0'),'.') }}%</span>@endif</td><td>{{ $it->hsn_code ?: '-' }}</td><td>{{ $it->qty }}</td><td>{{ $it->unit ?: 'NOS' }}</td><td class="nowrap" style="text-align:right">{{ money($it->rate) }}</td><td style="text-align:right">{{ $it->tax_rate !== null ? $it->tax_rate : $proposal->gst_rate }}%</td><td class="nowrap" style="text-align:right">{{ money($it->netAmount()) }}</td></tr>@endforeach</table></div>
  <div class="tot-band">
    @php $pItemDisc = $proposal->items->sum(fn($l) => round($l->amount - $l->netAmount(), 2)); @endphp
    <div class="tot-row"><span>Subtotal</span><span>{{ money($proposal->subtotal + $pItemDisc) }}</span></div>
    @if($pItemDisc > 0)<div class="tot-row"><span>Line discounts</span><span>- {{ money($pItemDisc) }}</span></div>@endif
    @if($proposal->discount > 0)<div class="tot-row"><span>Discount</span><span>- {{ money($proposal->discount) }}</span></div>@endif
    <div class="tot-row"><span>Taxable value</span><span>{{ money($proposal->taxable_value) }}</span></div>
    @foreach($proposal->taxSlabs() as $slab)<div class="tot-row"><span>GST @ {{ $slab['rate'] }}% on {{ money($slab['taxable']) }}</span><span>{{ money($slab['tax']) }}</span></div>@endforeach
    @if($intra)
    <div class="tot-row"><span>CGST @ {{ $halfRate }}%</span><span>{{ money($proposal->cgst_amount) }}</span></div>
    <div class="tot-row"><span>SGST @ {{ $halfRate }}%</span><span>{{ money($proposal->sgst_amount) }}</span></div>
    @else
    <div class="tot-row"><span>IGST @ {{ $proposal->gst_rate }}%</span><span>{{ money($proposal->igst_amount) }}</span></div>
    @endif
    <div class="tot-row grand"><span>Total</span><span>{{ money($proposal->total) }}</span></div>
  </div>
  <div class="words">Amount in words: {{ amount_words($proposal->total) }} only.</div>
  @if($proposal->scope)<div class="bill-box" style="margin-top:14px"><div class="cap">Scope of work</div><div class="sub" style="white-space:pre-line">{{ $proposal->scope }}</div></div>@endif
  @if($proposal->results)<div class="bill-box" style="margin-top:14px"><div class="cap">Expected results</div><div class="sub" style="white-space:pre-line">{{ $proposal->results }}</div></div>@endif
  @if($proposal->terms)<div class="bill-box" style="margin-top:14px"><div class="cap">Terms</div><div class="sub" style="white-space:pre-line">{{ $proposal->terms }}</div></div>@endif
  <div class="bill-grid">
    <div class="bill-box"><div class="cap">Accepted by (client signature)</div>
      @if($proposal->signature_path)<img src="{{ asset('storage/'.$proposal->signature_path) }}" alt="client signature" style="max-height:90px;max-width:230px;object-fit:contain;background:#fff;border:1px solid var(--line);border-radius:10px;padding:4px"><div class="sub" style="margin-top:6px"><b>{{ $proposal->signed_by }}</b> · {{ $proposal->signed_at ? d($proposal->signed_at, 'j M Y, h:i A') : '' }}</div>
      @elseif(!empty($proposal->accept_mode))<div class="sub">Accepted offline via <b>{{ ucfirst(str_replace('_',' ',$proposal->accept_mode)) }}</b>@if($proposal->accept_note) — {{ $proposal->accept_note }}@endif</div>
      @else <div class="sub">{{ $st==='expired' ? 'This proposal expired unsigned.' : ($st==='declined' ? 'Declined by the client.' : 'Awaiting client signature…') }}@if($st==='declined' && !empty($proposal->close_reason)) Reason: {{ $proposal->close_reason }}@endif</div>@endif</div>
    <div class="bill-box" style="text-align:right"><div class="cap">For {{ $brand['company_name'] ?? '' }}</div><div style="height:52px;display:flex;align-items:flex-end;justify-content:flex-end">@if(setting('invoice.signature_path'))<img src="{{ asset('storage/'.setting('invoice.signature_path')) }}" alt="signature" style="max-height:52px;max-width:180px;object-fit:contain">@endif</div><div class="sub">Authorised Signatory</div></div>
  </div>
