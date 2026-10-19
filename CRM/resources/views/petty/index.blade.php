@extends('layouts.app')
@section('title','Petty cash')
@section('content')
<div class="grid stats cols-4">
  <div class="stat acc" style="--acc:#0a84ff"><div class="lbl">In hand</div><b>{{ money($balance) }}</b><div class="sub">right now</div></div>
  <div class="stat acc" style="--acc:#30d158"><div class="lbl">Added · {{ d($from,'M Y') }}</div><b>{{ money($in) }}</b><div class="sub">cash in</div></div>
  <div class="stat acc" style="--acc:#ff9f0a"><div class="lbl">Spent · {{ d($from,'M Y') }}</div><b>{{ money($out) }}</b><div class="sub">cash out</div></div>
</div>

<div class="card" style="margin-top:22px">
<div class="card-head"><h3>Book · {{ d($from,'M Y') }}</h3><span class="btnrow noprint" style="gap:8px"><a class="btn sec sm" href="{{ route('petty.index',['month'=>$prev]) }}">← {{ d($prev.'-01','M Y') }}</a>@if($next)<a class="btn sec sm" href="{{ route('petty.index',['month'=>$next]) }}">{{ d($next.'-01','M Y') }} →</a>@endif</span></div>
<div class="noprint" style="margin-bottom:12px"><form method="POST" action="{{ route('petty.store') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">@csrf
<div><label class="f">Date</label><input class="in" type="date" name="entry_date" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" style="margin:0" required></div>
<div><label class="f">Type</label><select class="in" name="kind" style="margin:0"><option value="out">Spend</option><option value="in">Add cash</option></select></div>
<div><label class="f">Amount (₹)</label><input class="in" type="number" step="0.01" min="0.01" name="amount" style="margin:0;max-width:160px" required placeholder="0.00"></div>
<div style="flex:2;min-width:160px"><label class="f">Note</label><input class="in" name="note" style="margin:0" maxlength="255" placeholder="e.g. Auto fare, office keys"></div>
<div><button class="btn sm">Add</button></div></form></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Date</th><th>Type</th><th>Note</th><th>By</th><th style="text-align:right">In</th><th style="text-align:right">Out</th><th style="text-align:right">Balance</th><th class="noprint"></th></tr>
<tr><td colspan="6"><span class="hint">Opening {{ d($from,'M Y') }}</span></td><td class="nowrap" style="text-align:right">{{ money($opening) }}</td><td class="noprint"></td></tr>
@forelse($rows as $e)<tr><td class="nowrap">{{ d($e->entry_date) }}</td><td><span class="badge {{ $e->kind==='in' ? 'b-green' : 'b-amber' }}">{{ $e->kind==='in' ? 'In' : 'Out' }}</span></td><td>{{ $e->note ?: '—' }}</td><td>{{ $e->creator->name ?? '—' }}</td><td class="nowrap" style="text-align:right">{{ $e->kind==='in' ? money($e->amount) : '—' }}</td><td class="nowrap" style="text-align:right">{{ $e->kind==='out' ? money($e->amount) : '—' }}</td><td class="nowrap" style="text-align:right"><b>{{ money($e->running) }}</b></td><td class="noprint"><form method="POST" action="{{ route('petty.destroy',$e) }}" style="display:inline" data-confirm="Delete this entry?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form></td></tr>@empty<tr><td colspan="8"><div class="empty">No entries this month — add cash to open the book.</div></td></tr>@endforelse</table></div>
</div>
@endsection
