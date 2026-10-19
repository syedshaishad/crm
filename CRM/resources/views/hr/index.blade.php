@extends('layouts.app')
@section('title','HR overview')
@section('content')
<div class="grid stats cols-4" style="margin-bottom:14px">
  <div class="stat"><div class="lbl">Headcount</div><b>{{ $headcount }}</b><div class="sub">active staff</div></div>
  <div class="stat"><div class="lbl">Present today</div><b>{{ $present }}</b><div class="sub">{{ $onLeave }} on leave · {{ $openVisits }} on site</div></div>
  <div class="stat"><div class="lbl">Awaiting decisions</div><b>{{ $pendingLeave + $pendingOt + $pendingClaims + $pendingBonus }}</b><div class="sub">leave {{ $pendingLeave }} · OT {{ $pendingOt }} · claims {{ $pendingClaims }} · bonus {{ $pendingBonus }}</div></div>
  <div class="stat"><div class="lbl">Payroll · {{ date('F Y') }}</div><b>{{ $payroll ? ucfirst($payroll->status) : 'Not run' }}</b><div class="sub">@if($payroll){{ money($payroll->slips->sum('net')) }} net @else<a href="{{ route('payroll.index') }}">run it →</a>@endif</div></div>
</div>
<div class="cols-2">
<div class="card" style="margin-top:0"><div class="card-head"><h3>People</h3></div>
<div>
<a class="mi-row" href="{{ route('attendance.index') }}"><span class="mi-ic">◷</span><span style="flex:1"><b>Attendance</b><span class="hint"> · daily marks</span></span>→</a>
<a class="mi-row" href="{{ route('leaves.index') }}"><span class="mi-ic">❋</span><span style="flex:1"><b>Leave</b>@if($pendingLeave)<span class="badge b-amber">{{ $pendingLeave }} pending</span>@endif</span>→</a>
<a class="mi-row" href="{{ route('shifts.index') }}"><span class="mi-ic">▦</span><span style="flex:1"><b>Shifts & roster</b></span>→</a>
<a class="mi-row" href="{{ route('holidays.index') }}"><span class="mi-ic">☆</span><span style="flex:1"><b>Holidays</b></span>→</a>
<a class="mi-row" href="{{ route('field.index') }}"><span class="mi-ic">◎</span><span style="flex:1"><b>Site visits</b>@if($openVisits)<span class="badge b-green">{{ $openVisits }} out</span>@endif</span>→</a>
<a class="mi-row" href="{{ route('team.load') }}"><span class="mi-ic">▅</span><span style="flex:1"><b>Workload</b></span>→</a>
<a class="mi-row" href="{{ route('skills.index') }}"><span class="mi-ic">★</span><span style="flex:1"><b>Skills matrix</b></span>→</a>
<a class="mi-row" href="{{ route('voice.index') }}"><span class="mi-ic">✉</span><span style="flex:1"><b>Employee voice</b></span>→</a>
<a class="mi-row" href="{{ route('appraisals.index') }}"><span class="mi-ic">◆</span><span style="flex:1"><b>Appraisals</b><span class="hint"> · {{ $reviews }} on record</span></span>→</a>
<a class="mi-row" href="{{ route('exits.index') }}"><span class="mi-ic">➔</span><span style="flex:1"><b>Exits</b>@if($openExits)<span class="badge b-amber">{{ $openExits }} open</span>@endif</span>→</a>
</div></div>
<div class="card" style="margin-top:0"><div class="card-head"><h3>Pay</h3></div>
@if(auth()->user()->canManageInvoices())
<div>
<a class="mi-row" href="{{ route('payroll.index') }}"><span class="mi-ic">₹</span><span style="flex:1"><b>Payroll runs</b></span>→</a>
<a class="mi-row" href="{{ route('structures.index') }}"><span class="mi-ic">▤</span><span style="flex:1"><b>Pay structures</b></span>→</a>
<a class="mi-row" href="{{ route('salary.index') }}"><span class="mi-ic">↧</span><span style="flex:1"><b>Advances</b></span>→</a>
<a class="mi-row" href="{{ route('loans.index') }}"><span class="mi-ic">$</span><span style="flex:1"><b>Loans</b>@if($activeLoans)<span class="hint"> · {{ $activeLoans }} active</span>@endif</span>→</a>
<a class="mi-row" href="{{ route('bonuses.index') }}"><span class="mi-ic">✦</span><span style="flex:1"><b>Bonuses</b></span>→</a>
<a class="mi-row" href="{{ route('overtime.index') }}"><span class="mi-ic">◔</span><span style="flex:1"><b>Overtime</b>@if($pendingOt)<span class="badge b-amber">{{ $pendingOt }} pending</span>@endif</span>→</a>
<a class="mi-row" href="{{ route('reimbursements.index') }}"><span class="mi-ic">⧉</span><span style="flex:1"><b>Claims</b>@if($pendingClaims)<span class="badge b-amber">{{ $pendingClaims }} pending</span>@endif</span>→</a>
</div>
@else
<p class="hint">Payroll details are manager-only.</p>
@endif
@if($certs->isNotEmpty())
<hr><h3>Certs lapsing soon</h3>
@foreach($certs as $c)<div class="hint" style="margin-bottom:6px"><b>{{ $c->user->name ?? '' }}</b> — {{ $c->skill->name ?? '' }} · {{ d($c->cert_expires_on) }}</div>@endforeach
@endif
</div>
</div>
@endsection
