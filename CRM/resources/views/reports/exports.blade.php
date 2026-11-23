@extends('layouts.app')
@section('title','Report exports')
@section('content')
<x-page-header title="Data exports" :crumb="d($from, 'j M Y').' → '.d($to, 'j M Y').' · CSV and Tally XML'" icon='<path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v3h16v-3"/>'/>

<div class="card ix-card">
  <div class="card-head"><h2>Period</h2></div>
  <form method="GET" class="noprint rp-filter" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div><label class="f">From</label><input class="in" style="margin:0" type="date" name="from" value="{{ $from }}"></div>
    <div><label class="f">To</label><input class="in" style="margin:0" type="date" name="to" value="{{ $to }}"></div>
    <div><button class="btn sec">Apply</button></div>
  </form>
</div>

<div class="cols-2">
<div class="card ix-card"><div class="card-head"><h2>All exports</h2><span class="hint">{{ d($from, 'j M Y') }} → {{ d($to, 'j M Y') }}</span></div>
  <div style="display:grid;gap:8px">
    @foreach([['Payments CSV','Every collection with client, invoice & method.','payments'],['Invoices CSV','Numbers, clients, totals & balances.','invoices'],['Expenses CSV','Spend with category, vendor & project.','expenses']] as [$t,$s,$k])
    <a class="export-opt" href="{{ route('reports.export',['type'=>$k,'from'=>$from,'to'=>$to]) }}"><span><b>{{ $t }}</b><span>{{ $s }}</span></span></a>
    @endforeach
    <a class="export-opt" href="{{ route('reports.tally',['from'=>$from,'to'=>$to]) }}"><span><b>Tally XML</b><span>Party ledgers + balanced Sales & Purchase vouchers for Tally Prime import.</span></span></a>
    @if(auth()->user()->isAdmin())
    <a class="export-opt" href="{{ route('reports.export',['type'=>'clients','from'=>$from,'to'=>$to]) }}"><span><b>Clients CSV</b><span>Directory with GSTIN, state & status.</span></span></a>
    @endif
    @foreach([['GSTR-1 · B2B','Registered buyers, invoice by rate.','b2b'],['GSTR-1 · B2C','Grouped unregistered supplies.','b2c'],['GSTR-1 · HSN','HSN-wise qty and tax.','hsn'],['GSTR-1 · CDNR','Credit notes, split estimated.','cdnr']] as [$t,$s,$k])
    <a class="export-opt" href="{{ route('reports.gstr1.export',['type'=>$k,'from'=>$from,'to'=>$to]) }}"><span><b>{{ $t }}</b><span>{{ $s }}</span></span></a>
    @endforeach
  </div>
</div>
<div class="card ix-card"><div class="card-head"><h2>Client ledger</h2><span class="hint">running balance</span></div>
  <p class="hint" style="margin-top:0">Pick a client to open their full account ledger — invoices, payments and credit notes with Export CSV and Print.</p>
  <label class="f" for="ledger-client">Client</label>
  <select class="in" id="ledger-client" onchange="if(this.value)location=this.value"><option value="">Choose a client…</option>
  @foreach($ledgerClients as $c)<option value="{{ route('clients.ledger',$c->id) }}">{{ $c->company ?: $c->name }}{{ $c->company ? ' · '.$c->name : '' }}</option>@endforeach
  </select>
</div>
</div>
@endsection
