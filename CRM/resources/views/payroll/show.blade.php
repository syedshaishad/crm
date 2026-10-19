@extends('layouts.app')
@section('title','Payroll '.$payroll->label())
@section('content')
<div class="card">
<div class="card-head"><h3>{{ $payroll->label() }}</h3><span class="badge {{ $payroll->status==='finalized' ? 'b-green' : 'b-amber' }}">{{ ucfirst($payroll->status) }}</span></div>
<div class="grid stats cols-4" style="margin-bottom:14px">
  <div class="stat"><div class="lbl">Staff paid</div><b>{{ $payroll->slips->count() }}</b></div>
  <div class="stat"><div class="lbl">Net payout</div><b>{{ money($payroll->slips->sum('net')) }}</b></div>
  <div class="stat"><div class="lbl">TDS (192)</div><b>{{ money($payroll->slips->sum('tds')) }}</b></div>
  <div class="stat"><div class="lbl">Recovered</div><b>{{ money($payroll->slips->sum('advance_cut') + $payroll->slips->sum('loan_cut')) }}</b><div class="sub">advances + EMIs</div></div>
</div>
@if($payroll->status!=='finalized')
<div class="btnrow noprint" style="margin-bottom:12px">
<form method="POST" action="{{ route('payroll.finalize',$payroll) }}" data-confirm="Finalize {{ $payroll->label() }}? Salaries post to expenses, recoveries + TDS book automatically. This locks the run.">@csrf<button class="btn">Finalize & post to books</button></form>
<form method="POST" action="{{ route('payroll.destroy',$payroll) }}" data-confirm="Discard this draft run?">@csrf @method('DELETE')<button class="btn sec danger">Discard draft</button></form>
</div>
@else
<p class="hint">Finalized{{ $payroll->finalized_at ? ' '.d($payroll->finalized_at,'j M Y, h:i A') : '' }} — locked.</p>
@endif
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Who</th><th style="text-align:right">Paid days</th><th style="text-align:right">Earned</th><th style="text-align:right">+ OT/Bonus/Reimb</th><th style="text-align:right">− Adv/EMI/TDS</th><th style="text-align:right">Net</th><th class="noprint"></th></tr>
@forelse($payroll->slips as $s)<tr>
<td><b>{{ $s->user->name ?? '—' }}</b><div class="hint">{{ $s->paid_days }}/{{ $s->working_days }} days · gross {{ money($s->gross_monthly) }}</div></td>
<td class="nowrap" style="text-align:right">{{ $s->paid_days }}</td>
<td class="nowrap" style="text-align:right">{{ money($s->gross_earned) }}</td>
<td class="nowrap" style="text-align:right">{{ money($s->additions()) }}</td>
<td class="nowrap" style="text-align:right">{{ money($s->deductions()) }}</td>
<td class="nowrap" style="text-align:right"><b>{{ money($s->net) }}</b></td>
<td class="noprint"><a class="icon-btn" href="{{ route('payroll.slip',[$payroll,$s]) }}">Payslip</a></td></tr>
@empty<tr><td colspan="7"><div class="empty">No slips — nobody has a salary structure yet.</div></td></tr>@endforelse</table></div></div>
@endsection
