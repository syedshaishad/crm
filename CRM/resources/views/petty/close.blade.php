@extends('layouts.app')
@section('title','Day close')
@section('content')
<div class="card">
<div class="card-head"><h3>Close · {{ d($today,'j F Y') }}</h3>@if($done)<span class="badge b-green">closed</span>@endif</div>
<div class="grid stats cols-4" style="margin-bottom:14px">
  <div class="stat acc" style="--acc:#0a84ff"><div class="lbl">Petty in hand</div><b>{{ money($expected) }}</b><div class="sub">expected count</div></div>
  <div class="stat acc" style="--acc:#30d158"><div class="lbl">Cash receipts today</div><b>{{ money($cashToday) }}</b><div class="sub">cash payments</div></div>
  <div class="stat acc" style="--acc:#ff9f0a"><div class="lbl">Open invoices</div><b>{{ $openInvoices }}</b><div class="sub">with balance</div></div>
  <div class="stat acc" style="--acc:#ff453a"><div class="lbl">Cheques to deposit</div><b>{{ $dueCheques }}</b><div class="sub">date reached</div></div>
</div>
<form method="POST" action="{{ route('dayclose.store') }}">@csrf
<label class="f">Cash counted (₹) <span class="req">*</span></label><input class="in" type="number" step="0.01" min="0" name="cash_counted" required style="max-width:240px" value="{{ old('cash_counted', $done->cash_counted ?? '') }}" placeholder="Physical cash in drawer">
<div class="fsec">Checklist</div>
@foreach(\Modules\Petty\DayClose::checklist() as $k=>$l)<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:8px"><input type="checkbox" name="checks[{{ $k }}]" value="1" {{ ($done->checks[$k] ?? false) ? 'checked' : '' }} style="width:auto;margin:0"> {{ $l }}</label>@endforeach
<label class="f">Notes</label><textarea class="in" name="notes" rows="2" placeholder="Anything for tomorrow…">{{ old('notes', $done->notes ?? '') }}</textarea>
<div class="form-actions"><button class="btn" type="submit">{{ $done ? 'Update close' : 'Close the day' }}</button></div></form>
</div>
<div class="card" style="margin-top:22px"><div class="card-head"><h3>Past closes</h3></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Date</th><th style="text-align:right">Counted</th><th style="text-align:right">Expected</th><th style="text-align:right">Variance</th><th>Checks</th><th>By</th></tr>
@forelse($closes as $c)@php $v = $c->variance(); @endphp<tr><td class="nowrap">{{ d($c->day) }}</td><td class="nowrap" style="text-align:right">{{ money($c->cash_counted) }}</td><td class="nowrap" style="text-align:right">{{ money($c->expected_petty) }}</td><td class="nowrap" style="text-align:right"><b style="{{ $v != 0 ? 'color:var(--bad)' : '' }}">{{ ($v > 0 ? '+' : '').money($v) }}</b></td><td>{{ collect($c->checks)->filter()->count() }}/{{ count(\Modules\Petty\DayClose::checklist()) }}</td><td>{{ $c->closer->name ?? '—' }}</td></tr>@empty<tr><td colspan="6"><div class="empty">No closes yet.</div></td></tr>@endforelse</table></div></div>
@endsection
