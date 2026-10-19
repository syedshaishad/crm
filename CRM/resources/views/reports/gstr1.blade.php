@extends('layouts.app')
@section('title','GSTR-1 outward supplies')
@section('content')
<div class="card">
  <div class="card-head"><h3>Filters</h3><div class="btnrow" style="gap:8px"><a class="btn sm sec" href="{{ route('reports.index',['from'=>$from,'to'=>$to]) }}">← Reports</a></div></div>
  <form method="GET" class="noprint" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div><label class="f">From</label><input class="in" style="margin:0" type="date" name="from" value="{{ $from }}"></div>
    <div><label class="f">To</label><input class="in" style="margin:0" type="date" name="to" value="{{ $to }}"></div>
    <div><button class="btn sec">Apply</button></div>
  </form>
</div>

<div class="grid stats">
  <div class="stat acc" style="--acc:#0a84ff"><div class="lbl">Taxable value</div><b>{{ money($tot['taxable']) }}</b><div class="sub">{{ $tot['docs'] }} filed docs · drafts & cancelled excluded</div></div>
  <div class="stat acc" style="--acc:#30d158"><div class="lbl">CGST + SGST</div><b>{{ money($tot['cgst'] + $tot['sgst']) }}</b><div class="sub">intra-state supplies</div></div>
  <div class="stat acc" style="--acc:#bf5af2"><div class="lbl">IGST</div><b>{{ money($tot['igst']) }}</b><div class="sub">inter-state supplies</div></div>
  <div class="stat acc" style="--acc:#ff9f0a"><div class="lbl">Credit notes (est.)</div><b>{{ money($noteTotals['taxable']) }}</b><div class="sub">{{ $noteTotals['count'] }} notes · tax split pro-rata</div></div>
</div>

<div class="card" style="margin-top:22px"><div class="card-head"><h3>B2B — registered buyers</h3><a class="btn sm sec" href="{{ route('reports.gstr1.export',['type'=>'b2b','from'=>$from,'to'=>$to]) }}">CSV</a></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Invoice</th><th>Date</th><th>GSTIN</th><th>Receiver</th><th>Place of supply</th><th>RCM</th><th style="text-align:right">Rate</th><th style="text-align:right">Taxable</th><th style="text-align:right">CGST</th><th style="text-align:right">SGST</th><th style="text-align:right">IGST</th><th style="text-align:right">Invoice value</th></tr>
@forelse($b2b as $b)<tr><td class="nowrap"><b>{{ $b['number'] }}</b></td><td class="nowrap">{{ d($b['date']) }}</td><td class="nowrap">{{ $b['gstin'] }}</td><td>{{ $b['name'] }}</td><td class="nowrap">{{ $b['pos'] }}</td><td>{{ $b['rcm'] ? 'Yes' : 'No' }}</td><td class="nowrap" style="text-align:right">{{ $b['rate'] }}%</td><td class="nowrap" style="text-align:right">{{ money($b['taxable']) }}</td><td class="nowrap" style="text-align:right">{{ money($b['cgst']) }}</td><td class="nowrap" style="text-align:right">{{ money($b['sgst']) }}</td><td class="nowrap" style="text-align:right">{{ money($b['igst']) }}</td><td class="nowrap" style="text-align:right"><b>{{ money($b['value']) }}</b></td></tr>@empty<tr><td colspan="12"><div class="empty">No B2B supplies in range.</div></td></tr>@endforelse</table></div></div>

<div class="card" style="margin-top:22px"><div class="card-head"><h3>B2C — unregistered buyers</h3><a class="btn sm sec" href="{{ route('reports.gstr1.export',['type'=>'b2c','from'=>$from,'to'=>$to]) }}">CSV</a></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Type</th><th>Place of supply</th><th style="text-align:right">Rate</th><th style="text-align:right">Taxable</th><th style="text-align:right">CGST</th><th style="text-align:right">SGST</th><th style="text-align:right">IGST</th><th style="text-align:right">Docs</th></tr>
@forelse($b2c as $g)<tr><td><span class="badge {{ $g['type']==='B2CL' ? 'b-blue' : 'b-grey' }}">{{ $g['type'] }}</span></td><td class="nowrap">{{ $g['pos'] }}</td><td class="nowrap" style="text-align:right">{{ $g['rate'] }}%</td><td class="nowrap" style="text-align:right">{{ money($g['taxable']) }}</td><td class="nowrap" style="text-align:right">{{ money($g['cgst']) }}</td><td class="nowrap" style="text-align:right">{{ money($g['sgst']) }}</td><td class="nowrap" style="text-align:right">{{ money($g['igst']) }}</td><td class="nowrap" style="text-align:right">{{ $g['count'] }}</td></tr>@empty<tr><td colspan="8"><div class="empty">No B2C supplies in range.</div></td></tr>@endforelse</table></div>
<p class="hint" style="margin:10px 0 0">B2CL = inter-state invoices over ₹1,00,000 · everything else B2CS.</p></div>

<div class="card" style="margin-top:22px"><div class="card-head"><h3>HSN summary</h3><a class="btn sm sec" href="{{ route('reports.gstr1.export',['type'=>'hsn','from'=>$from,'to'=>$to]) }}">CSV</a></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>HSN</th><th>UQC</th><th style="text-align:right">Qty</th><th style="text-align:right">Taxable</th><th style="text-align:right">CGST</th><th style="text-align:right">SGST</th><th style="text-align:right">IGST</th></tr>
@forelse($hsn as $h)<tr><td class="nowrap"><b>{{ $h['hsn'] }}</b></td><td class="nowrap">{{ $h['uqc'] }}</td><td class="nowrap" style="text-align:right">{{ $h['qty'] }}</td><td class="nowrap" style="text-align:right">{{ money($h['taxable']) }}</td><td class="nowrap" style="text-align:right">{{ money($h['cgst']) }}</td><td class="nowrap" style="text-align:right">{{ money($h['sgst']) }}</td><td class="nowrap" style="text-align:right">{{ money($h['igst']) }}</td></tr>@empty<tr><td colspan="7"><div class="empty">No items in range.</div></td></tr>@endforelse</table></div></div>

<div class="card" style="margin-top:22px"><div class="card-head"><h3>CDNR — credit notes</h3><a class="btn sm sec" href="{{ route('reports.gstr1.export',['type'=>'cdnr','from'=>$from,'to'=>$to]) }}">CSV</a></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Note</th><th>Date</th><th>Linked invoice</th><th>GSTIN</th><th style="text-align:right">Rate</th><th style="text-align:right">Taxable</th><th style="text-align:right">CGST</th><th style="text-align:right">SGST</th><th style="text-align:right">IGST</th><th>Split</th></tr>
@forelse($cdnr as $c)<tr><td class="nowrap"><b>{{ $c['number'] }}</b></td><td class="nowrap">{{ d($c['date']) }}</td><td class="nowrap">{{ $c['invoice'] }}</td><td class="nowrap">{{ $c['gstin'] ?: '—' }}</td><td class="nowrap" style="text-align:right">{{ $c['rate'] === null ? '—' : $c['rate'].'%' }}</td><td class="nowrap" style="text-align:right">{{ money($c['taxable']) }}</td><td class="nowrap" style="text-align:right">{{ money($c['cgst']) }}</td><td class="nowrap" style="text-align:right">{{ money($c['sgst']) }}</td><td class="nowrap" style="text-align:right">{{ money($c['igst']) }}</td><td><span class="badge b-grey">estimated</span></td></tr>@empty<tr><td colspan="10"><div class="empty">No credit notes in range.</div></td></tr>@endforelse</table></div>
<p class="hint" style="margin:10px 0 0">Notes carry no tax breakup — amounts are split pro-rata across the linked invoice's slabs. Verify before filing.</p></div>
@endsection
