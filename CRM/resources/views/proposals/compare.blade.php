@extends('layouts.app')
@section('title','Compare '.$proposal->number)
@section('content')
<div class="page-head"><h1>Compare</h1><div class="crumb">{{ $proposal->number }} vs {{ $other ? $other->number : '…' }}</div></div>
<div class="card noprint"><form method="GET" action="{{ route('proposals.compare',$proposal) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
<div><label class="f">Against</label><select class="in" name="with" style="margin:0;min-width:260px" onchange="this.form.submit()"><option value="">Choose a proposal…</option>@foreach($options as $o)<option value="{{ $o->id }}" {{ $other && (int)$other->id===(int)$o->id?'selected':'' }}>{{ $o->number }} · {{ $o->client->company ?? $o->client->name ?? '' }} · {{ ucfirst($o->status) }} · {{ money($o->total) }}</option>@endforeach</select></div>
<noscript><div><button class="btn sec sm">Compare</button></div></noscript></form></div>
@if($other)
@php
  $cols = ['a' => $proposal, 'b' => $other];
  $row = function ($label, $fa, $fb, $fmt = null) use ($cols) {
    $va = $fa($cols['a']); $vb = $fb($cols['b']);
    $diff = (string) $va !== (string) $vb;
    $f = $fmt ?: fn ($v) => $v;
    echo '<tr'.($diff ? ' style="background:var(--warn-bg,rgba(255,159,10,.08))"' : '').'><td>'.e($label).'</td><td>'.$f($va).'</td><td>'.$f($vb).'</td></tr>';
  };
@endphp
<div class="card"><div class="card-head"><h3>{{ $proposal->number }} vs {{ $other->number }}</h3><span class="hint">highlighted rows differ</span></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th style="width:26%"></th><th>{{ $proposal->number }}</th><th>{{ $other->number }}</th></tr>
@php
  $row('Client', fn ($p) => $p->client->company ?: $p->client->name, fn ($p) => $p->client->company ?: $p->client->name, fn ($v) => e($v));
  $row('Status', fn ($p) => $p->displayStatus(), fn ($p) => $p->displayStatus(), fn ($v) => ucfirst(e($v)));
  $row('Total', fn ($p) => $p->total, fn ($p) => $p->total, fn ($v) => '<b>'.money($v).'</b>');
  $row('Subtotal', fn ($p) => $p->subtotal, fn ($p) => $p->subtotal, fn ($v) => money($v));
  $row('Discount', fn ($p) => $p->discount, fn ($p) => $p->discount, fn ($v) => money($v));
  $row('GST', fn ($p) => $p->gst_amount, fn ($p) => $p->gst_amount, fn ($v) => money($v));
  $row('Services', fn ($p) => $p->items->count(), fn ($p) => $p->items->count());
  $row('Valid till', fn ($p) => $p->valid_until ? d($p->valid_until) : '—', fn ($p) => $p->valid_until ? d($p->valid_until) : '—', fn ($v) => e($v));
  $row('Scope', fn ($p) => $p->scope ?: '—', fn ($p) => $p->scope ?: '—', fn ($v) => nl2br(e(mb_substr($v, 0, 300))));
  $row('Results', fn ($p) => $p->results ?: '—', fn ($p) => $p->results ?: '—', fn ($v) => nl2br(e(mb_substr($v, 0, 300))));
  $row('Terms', fn ($p) => $p->terms ?: '—', fn ($p) => $p->terms ?: '—', fn ($v) => nl2br(e(mb_substr($v, 0, 300))));
@endphp
</table></div></div>
<div class="card"><div class="card-head"><h3>Services</h3></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>#</th><th>{{ $proposal->number }}</th><th>{{ $other->number }}</th></tr>
@php $max = max($proposal->items->count(), $other->items->count()); @endphp
@for($i = 0; $i < $max; $i++)@php $a = $proposal->items[$i] ?? null; $b = $other->items[$i] ?? null; $d = ($a?->description) !== ($b?->description) || ($a?->amount) !== ($b?->amount); @endphp<tr @if($d) style="background:var(--warn-bg,rgba(255,159,10,.08))" @endif><td>{{ $i + 1 }}</td><td>@if($a){{ $a->description }} <span class="hint">{{ $a->qty }} × {{ money($a->rate) }} = {{ money($a->amount) }}</span>@else<span class="hint">—</span>@endif</td><td>@if($b){{ $b->description }} <span class="hint">{{ $b->qty }} × {{ money($b->rate) }} = {{ money($b->amount) }}</span>@else<span class="hint">—</span>@endif</td></tr>@endfor</table></div></div>
@endif
@endsection
