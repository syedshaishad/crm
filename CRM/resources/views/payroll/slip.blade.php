@extends('layouts.app')
@section('title','Payslip '.$payroll->label())
@section('content')
<div class="card" style="max-width:680px">
<div class="card-head"><h3>Payslip — {{ $payroll->label() }}</h3><button class="btn sm noprint" onclick="window.print()">Print / PDF</button></div>
<p style="margin-top:0"><b>{{ $payslip->user->name ?? '' }}</b>@if($payslip->user->designation)<span class="hint"> · {{ $payslip->user->designation }}</span>@endif</p>
<div class="tbl-scroll"><table class="tbl">
<tr><th>Earnings</th><th style="text-align:right">Amount</th></tr>
<tr><td>Gross earned ({{ $payslip->paid_days }}/{{ $payslip->working_days }} days of {{ money($payslip->gross_monthly) }})</td><td class="nowrap" style="text-align:right">{{ money($payslip->gross_earned) }}</td></tr>
@if($payslip->overtime_pay > 0)<tr><td>Overtime</td><td class="nowrap" style="text-align:right">{{ money($payslip->overtime_pay) }}</td></tr>@endif
@if($payslip->bonus > 0)<tr><td>Bonus</td><td class="nowrap" style="text-align:right">{{ money($payslip->bonus) }}</td></tr>@endif
@if($payslip->reimbursements > 0)<tr><td>Reimbursements</td><td class="nowrap" style="text-align:right">{{ money($payslip->reimbursements) }}</td></tr>@endif
<tr><th>Deductions</th><th></th></tr>
@if($payslip->advance_cut > 0)<tr><td>Advance recovery</td><td class="nowrap" style="text-align:right">{{ money($payslip->advance_cut) }}</td></tr>@endif
@if($payslip->loan_cut > 0)<tr><td>Loan EMI</td><td class="nowrap" style="text-align:right">{{ money($payslip->loan_cut) }}</td></tr>@endif
@if($payslip->tds > 0)<tr><td>TDS (192)</td><td class="nowrap" style="text-align:right">{{ money($payslip->tds) }}</td></tr>@endif
<tr><td><b>Net pay</b></td><td class="nowrap" style="text-align:right"><b>{{ money($payslip->net) }}</b></td></tr>
</table></div>
<p class="hint">Generated {{ d(now()) }} · {{ $brand['company_name'] ?? config('app.name') }}</p>
</div>
@endsection
