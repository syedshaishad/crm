  <div class="doc-head">
    <div class="doc-brand">
      @if($invLogo)@include('brand.logo', ['light' => $invLogo, 'dark' => $invDark ?? '', 'cls' => 'doc-logo', 'hideName' => true])@else<div><h2>{{ $brand['company_name'] ?? '' }}</h2><div class="doc-sub">{{ $brand['address'] ?? '' }}</div>@if(brand_contact($brand))<div class="doc-sub">{{ brand_contact($brand) }}</div>@endif</div>@endif
    </div>
    <div class="doc-meta"><h3>TAX INVOICE</h3><div class="inv-no">{{ $invoice->number }}</div><div class="hint">Original for Recipient</div></div>
  </div>
  <div class="bill-grid">
    <div class="bill-box"><div class="cap">Supplier</div><b>{{ $brand['company_name'] ?? '' }}</b>
      <div class="sub">{{ $brand['address'] ?? '' }}</div>
      @if(!empty($brand['gstin']))<div class="sub">GSTIN: {{ $brand['gstin'] }}</div>@endif
      <div class="sub">State: {{ $companyState ?: '—' }}</div>
      @if(brand_contact($brand))<div class="sub">{{ brand_contact($brand) }}</div>@endif</div>
    <div class="bill-box"><div class="cap">Recipient (Bill to)</div><b>{{ $invoice->client->company ?: ($invoice->client->name ?? '') }}</b>
      @if($invoice->client->company && $invoice->client->name !== $invoice->client->company)<div class="sub">Attn: {{ $invoice->client->name }}</div>@endif
      <div class="sub">{{ $invoice->client->address ?? '' }}</div>
      <div class="sub">GSTIN: {{ $invoice->client->gstin ?: 'Unregistered' }}</div>
      <div class="sub">State: {{ $buyerState ?: '—' }}</div></div>
  </div>
  <div class="bill-grid">
    <div class="bill-box"><div class="cap">Invoice details</div>
      <div class="sub">No: <b>{{ $invoice->number }}</b></div>
      <div class="sub">Date: {{ d($invoice->issue_date, 'j F Y') }}</div>
      <div class="sub">Due: {{ d($invoice->due_date, 'j F Y') ?: 'On receipt' }}</div></div>
    <div class="bill-box"><div class="cap">Tax details</div>
      <div class="sub">Place of supply: {{ $invoice->place_of_supply ?: '—' }}</div>
      <div class="sub">Supply type: {{ $intra ? 'Intra-state (CGST + SGST)' : 'Inter-state (IGST)' }}</div>
      <div class="sub">Reverse charge: {{ $invoice->reverse_charge ? 'Yes — tax payable by recipient' : 'No' }}</div>
      @if($invoice->eway_bill_no)<div class="sub">E-way bill: <b>{{ $invoice->eway_bill_no }}</b></div>@endif</div>
  </div>
  <div class="tbl-scroll roomy"><table class="tbl"><tr><th style="width:4%">#</th><th style="width:26%">Description</th><th style="width:10%">HSN/SAC</th><th style="width:6%">Qty</th><th style="width:7%">Unit</th><th style="width:14%;text-align:right">Rate</th><th style="width:8%;text-align:right">GST%</th><th style="width:15%;text-align:right">Taxable</th></tr>
  @foreach($invoice->items as $n => $it)<tr><td>{{ $n + 1 }}</td><td>{{ $it->description }}@if(($it->discount_percent ?? 0) > 0)<span class="hint"> −{{ rtrim(rtrim($it->discount_percent,'0'),'.') }}%</span>@endif</td><td>{{ $it->hsn_code ?: '-' }}</td><td>{{ $it->qty }}</td><td>{{ $it->unit ?: 'NOS' }}</td><td class="nowrap" style="text-align:right">{{ money($it->rate) }}</td><td style="text-align:right">{{ $it->tax_rate !== null ? $it->tax_rate : $invoice->gst_rate }}%</td><td class="nowrap" style="text-align:right">{{ money($it->netAmount()) }}</td></tr>@endforeach</table></div>
  <div class="tot-cols">
    <div class="tot-band"><div class="cap">Tax breakup</div>
    @foreach($invoice->taxSlabs() as $slab)<div class="tot-row"><span>GST {{ $slab['rate'] }}%</span><span>{{ money($slab['tax']) }}</span></div>@endforeach
    @if($intra)
    <div class="tot-row"><span>CGST {{ $halfRate }}%</span><span>{{ money($invoice->cgst_amount) }}</span></div>
    <div class="tot-row"><span>SGST {{ $halfRate }}%</span><span>{{ money($invoice->sgst_amount) }}</span></div>
    @else
    <div class="tot-row"><span>IGST {{ $invoice->gst_rate }}%</span><span>{{ money($invoice->igst_amount) }}</span></div>
    @endif
    </div>
    <div class="tot-band"><div class="cap">Totals</div>
    @php $itemDisc = $invoice->items->sum(fn($l) => round($l->amount - $l->netAmount(), 2)); @endphp
    <div class="tot-row"><span>Subtotal</span><span>{{ money($invoice->subtotal + $itemDisc) }}</span></div>
    @if($itemDisc > 0)<div class="tot-row"><span>Line discounts</span><span>- {{ money($itemDisc) }}</span></div>@endif
    @if($invoice->discount > 0)<div class="tot-row"><span>Discount</span><span>- {{ money($invoice->discount) }}</span></div>@endif
    <div class="tot-row"><span>Taxable value</span><span>{{ money($invoice->taxable_value) }}</span></div>
    <div class="tot-row grand"><span>Total</span><span>{{ money($invoice->total) }}</span></div>
    @if($invoice->isForeign())<div class="tot-row"><span>Total ({{ $invoice->currency }})</span><span>{{ money($invoice->foreignTotal(), $invoice->currency) }} @ {{ rtrim(rtrim($invoice->exchange_rate,'0'),'.') }}</span></div>@endif
    <div class="tot-row"><span>Paid</span><span class="paid">{{ money($invoice->paid_amount) }}</span></div>
    @if(($invoice->writeoff_amount ?? 0) > 0)<div class="tot-row"><span>Written off</span><span>{{ money($invoice->writeoff_amount) }}</span></div>@endif
    @isset($tdsEntries)
    @if($tdsEntries->isNotEmpty())<div class="tot-row"><span>· of which TDS</span><span>{{ money($tdsEntries->sum('amount')) }}</span></div>@endif
    @endisset
    <div class="tot-row"><span>Balance due</span><span><b>{{ money($balance) }}</b></span></div>
    </div>
  </div>
  <div class="words">Amount in words: {{ amount_words($invoice->total) }} only.</div>
  @if($invoice->notes)<div class="bill-box" style="margin-top:14px"><div class="cap">Notes</div><div class="sub" style="white-space:pre-line">{{ $invoice->notes }}</div></div>@endif
  @php $bank = bank_details(); @endphp
  @if($bank && (($bankAlways ?? false) || $balance > 0))<div class="bill-box" style="margin-top:14px"><div class="cap">Pay by bank transfer</div>
    <div class="sub">@if($bank['name'] ?? ''){{ $bank['name'] }}@endif @if($bank['branch'] ?? '')({{ $bank['branch'] }})@endif</div>
    @if($bank['account_name'] ?? '')<div class="sub">A/c name: <b>{{ $bank['account_name'] }}</b></div>@endif
    @if($bank['account_number'] ?? '')<div class="sub">A/c no: <b>{{ $bank['account_number'] }}</b></div>@endif
    @if($bank['ifsc'] ?? '')<div class="sub">IFSC: <b>{{ $bank['ifsc'] }}</b></div>@endif
    @if($bank['upi_id'] ?? '')<div class="sub">UPI: <b>{{ $bank['upi_id'] }}</b></div>@endif</div>
  @include('invoices.upi-qr')@endif
  <div class="bill-grid">
    <div class="bill-box"><div class="cap">Declaration</div><div class="sub">{{ setting('invoice.declaration','We declare that this invoice shows the actual particulars of the supply described and that all particulars are true and correct.') }}</div>
    @if(setting('invoice.footer'))<div class="sub" style="margin-top:8px">{{ setting('invoice.footer') }}</div>@endif</div>
    <div class="bill-box" style="text-align:right"><div class="cap">For {{ $brand['company_name'] ?? '' }}</div><div style="height:52px;display:flex;align-items:flex-end;justify-content:flex-end">@if(setting('invoice.signature_path'))<img src="{{ asset('storage/'.setting('invoice.signature_path')) }}" alt="signature" style="max-height:52px;max-width:180px;object-fit:contain">@endif</div><div class="sub">Authorised Signatory</div></div>
  </div>
