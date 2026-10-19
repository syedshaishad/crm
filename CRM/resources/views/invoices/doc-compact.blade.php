<div class="doc-compact">
  <div class="dc-top">
    <div>@if($invLogo)@include('brand.logo', ['light' => $invLogo, 'dark' => $invDark ?? '', 'cls' => 'doc-logo', 'hideName' => true, 'style' => 'max-height:44px'])@else<b style="font-size:17px">{{ $brand['company_name'] ?? '' }}</b>@endif
      <div class="sub">{{ $brand['address'] ?? '' }}@if(!empty($brand['gstin'])) · GSTIN: {{ $brand['gstin'] }}@endif @if(brand_contact($brand))· {{ brand_contact($brand) }}@endif</div></div>
    <div style="text-align:right"><b>TAX INVOICE</b><div class="inv-no">{{ $invoice->number }}</div><div class="hint">{{ d($invoice->issue_date, 'j F Y') }} · Due {{ d($invoice->due_date, 'j F Y') ?: 'on receipt' }}</div></div>
  </div>
  <div class="dc-meta">
    <span><b>Bill to:</b> {{ $invoice->client->company ?: ($invoice->client->name ?? '') }}@if($invoice->client->gstin) ({{ $invoice->client->gstin }})@else (Unregistered)@endif</span>
    <span>{{ $invoice->client->address ?? '' }} · {{ $buyerState ?: '—' }}</span>
    <span>Place of supply: {{ $invoice->place_of_supply ?: '—' }} · {{ $intra ? 'CGST+SGST' : 'IGST' }}@if($invoice->reverse_charge) · <b>Reverse charge</b>@endif @if($invoice->eway_bill_no)· E-way: <b>{{ $invoice->eway_bill_no }}</b>@endif</span>
  </div>
  <div class="tbl-scroll"><table class="tbl compact"><tr><th style="width:4%">#</th><th>Description</th><th style="width:10%">HSN</th><th style="width:6%">Qty</th><th style="width:14%;text-align:right">Rate</th><th style="width:8%;text-align:right">GST%</th><th style="width:15%;text-align:right">Taxable</th></tr>
  @foreach($invoice->items as $n => $it)<tr><td>{{ $n + 1 }}</td><td>{{ $it->description }}@if(($it->discount_percent ?? 0) > 0)<span class="hint"> −{{ rtrim(rtrim($it->discount_percent,'0'),'.') }}%</span>@endif</td><td>{{ $it->hsn_code ?: '-' }}</td><td>{{ $it->qty }}</td><td class="nowrap" style="text-align:right">{{ money($it->rate) }}</td><td style="text-align:right">{{ $it->tax_rate !== null ? $it->tax_rate : $invoice->gst_rate }}%</td><td class="nowrap" style="text-align:right">{{ money($it->netAmount()) }}</td></tr>@endforeach</table></div>
  <div class="dc-tots">
    <div class="sub">@foreach($invoice->taxSlabs() as $slab)GST {{ $slab['rate'] }}%: {{ money($slab['tax']) }} · @endforeach
    @if($intra)CGST {{ $halfRate }}%: {{ money($invoice->cgst_amount) }} · SGST {{ $halfRate }}%: {{ money($invoice->sgst_amount) }}@else IGST {{ $invoice->gst_rate }}%: {{ money($invoice->igst_amount) }}@endif
    @if($invoice->discount > 0) · Discount: {{ money($invoice->discount) }}@endif</div>
    @php $itemDisc = $invoice->items->sum(fn($l) => round($l->amount - $l->netAmount(), 2)); @endphp
    @if($itemDisc > 0)<div class="sub">Subtotal {{ money($invoice->subtotal + $itemDisc) }} · less line discounts {{ money($itemDisc) }}</div>@endif
    <div class="dc-grand"><span>Total <b>{{ money($invoice->total) }}</b>@if($invoice->isForeign()) ({{ money($invoice->foreignTotal(), $invoice->currency) }})@endif</span><span>Paid {{ money($invoice->paid_amount) }}</span>@if(($invoice->writeoff_amount ?? 0) > 0)<span>Written off {{ money($invoice->writeoff_amount) }}</span>@endif @isset($tdsEntries) @if($tdsEntries->isNotEmpty())<span>TDS {{ money($tdsEntries->sum('amount')) }}</span>@endif @endisset<span>Balance <b>{{ money($balance) }}</b></span></div>
  </div>
  <div class="words">Amount in words: {{ amount_words($invoice->total) }} only.</div>
  @if($invoice->notes)<div class="sub" style="margin-top:8px"><b>Notes:</b> {{ $invoice->notes }}</div>@endif
  @php $bank = bank_details(); @endphp
  @if($bank && (($bankAlways ?? false) || $balance > 0))<div class="dc-meta" style="margin-top:10px"><span><b>Pay:</b> @if($bank['account_name'] ?? ''){{ $bank['account_name'] }} · @endif @if($bank['account_number'] ?? '')A/c {{ $bank['account_number'] }} · @endif @if($bank['ifsc'] ?? '')IFSC {{ $bank['ifsc'] }}@endif @if($bank['upi_id'] ?? '')· UPI <b>{{ $bank['upi_id'] }}</b>@endif</span></div>
  @include('invoices.upi-qr')@endif
  <div class="dc-foot"><span>{{ setting('invoice.declaration','We declare that this invoice shows the actual particulars of the supply described and that all particulars are true and correct.') }} @if(setting('invoice.footer')){{ setting('invoice.footer') }}@endif</span><span style="text-align:right">For <b>{{ $brand['company_name'] ?? '' }}</b><br><span class="sub">Authorised Signatory</span></span></div>
</div>
