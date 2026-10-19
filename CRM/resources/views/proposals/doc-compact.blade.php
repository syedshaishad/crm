<div class="doc-compact">
  <div class="dc-top">
    <div>@if(!empty($brand['logo_path']) || !empty($brand['logo_dark_path']))@include('brand.logo', ['cls' => 'doc-logo', 'style' => 'max-height:44px'])@else<b style="font-size:17px">{{ $brand['company_name'] ?? '' }}</b>@endif
      <div class="sub">{{ $brand['tagline'] ?? '' }} {{ brand_contact($brand) }}</div></div>
    <div style="text-align:right"><b>PROPOSAL</b><div class="inv-no">{{ $proposal->number }}</div><div class="hint">{{ d($proposal->issue_date, 'j F Y') }}@if($proposal->valid_until) · Valid {{ d($proposal->valid_until, 'j F Y') }}@endif</div></div>
  </div>
  <div class="dc-meta">
    <span><b>To:</b> {{ $proposal->client->company ?: $proposal->client->name }}@if($proposal->client->email) · {{ $proposal->client->email }}@endif @if($proposal->client->phone)· {{ $proposal->client->phone }}@endif</span>
    <span>By {{ $proposal->creator->name ?? '—' }} · {{ $intra ? 'CGST+SGST' : 'IGST' }} · <b>{{ $st }}</b></span>
  </div>
  @if($proposal->notes)<div class="sub" style="margin-bottom:8px">{{ $proposal->notes }}</div>@endif
  <div class="tbl-scroll"><table class="tbl compact"><tr><th style="width:4%">#</th><th>Service</th><th style="width:6%">Qty</th><th style="width:14%;text-align:right">Rate</th><th style="width:8%;text-align:right">GST%</th><th style="width:15%;text-align:right">Taxable</th></tr>
  @foreach($proposal->items as $n => $it)<tr><td>{{ $n + 1 }}</td><td>{{ $it->description }}@if(($it->billing ?? 'once') !== 'once') <span class="hint">· {{ $it->billing === 'monthly' ? '/mo' : '/yr' }}</span>@endif @if(($it->discount_percent ?? 0) > 0)<span class="hint"> −{{ rtrim(rtrim($it->discount_percent,'0'),'.') }}%</span>@endif</td><td>{{ $it->qty }}</td><td class="nowrap" style="text-align:right">{{ money($it->rate) }}</td><td style="text-align:right">{{ $it->tax_rate !== null ? $it->tax_rate : $proposal->gst_rate }}%</td><td class="nowrap" style="text-align:right">{{ money($it->netAmount()) }}</td></tr>@endforeach</table></div>
  <div class="dc-tots">
    <div class="sub">Taxable {{ money($proposal->taxable_value) }} @if($proposal->discount > 0)· after {{ money($proposal->discount) }} discount @endif · GST {{ money($proposal->gst_amount) }}</div>
    <div class="dc-grand"><span>Total <b>{{ money($proposal->total) }}</b></span></div>
  </div>
  <div class="words">Amount in words: {{ amount_words($proposal->total) }} only.</div>
  @if($proposal->scope)<div class="sub" style="margin-top:8px"><b>Scope:</b> {{ $proposal->scope }}</div>@endif
  @if($proposal->results)<div class="sub" style="margin-top:4px"><b>Results:</b> {{ $proposal->results }}</div>@endif
  @if($proposal->terms)<div class="sub" style="margin-top:4px"><b>Terms:</b> {{ $proposal->terms }}</div>@endif
  <div class="dc-foot"><span>@if($proposal->signature_path)Signed by <b>{{ $proposal->signed_by }}</b> · {{ $proposal->signed_at ? d($proposal->signed_at, 'j M Y') : '' }}@elseif(!empty($proposal->accept_mode))Accepted offline via <b>{{ ucfirst(str_replace('_',' ',$proposal->accept_mode)) }}</b>@else Awaiting client signature @endif</span><span style="text-align:right">For <b>{{ $brand['company_name'] ?? '' }}</b></span></div>
</div>
