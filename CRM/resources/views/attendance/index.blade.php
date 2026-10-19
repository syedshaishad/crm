@extends('layouts.app')
@section('title','Attendance')
@section('content')
<div class="card">
<div class="card-head"><h3>Attendance · {{ d($start,'M Y') }}</h3><span class="btnrow noprint" style="gap:8px"><a class="btn sec sm" href="{{ route('attendance.index',['month'=>$prev,'user_id'=>$uid]) }}">← {{ d($prev.'-01','M Y') }}</a>@if($next)<a class="btn sec sm" href="{{ route('attendance.index',['month'=>$next,'user_id'=>$uid]) }}">{{ d($next.'-01','M Y') }} →</a>@endif<a class="btn sec sm" href="{{ route('leaves.index') }}">Leave</a><a class="btn sec sm" href="{{ route('shifts.index') }}">Roster</a><a class="btn sec sm" href="{{ route('holidays.index') }}">Holidays</a></span></div>
<form method="GET" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">
@if($canAll)<div><label class="f">Staff</label><select class="in" name="user_id" style="margin:0" onchange="this.form.submit()">@foreach($users as $u)<option value="{{ $u->id }}" {{ (int)$uid===(int)$u->id?'selected':'' }}>{{ $u->name }}</option>@endforeach</select></div>@endif
<div><label class="f">Month</label><input class="in" type="month" name="month" value="{{ $month }}" style="margin:0" onchange="this.form.submit()"></div></form>
<div class="grid stats cols-4" style="margin-bottom:14px">
  <div class="stat acc" style="--acc:#30d158"><div class="lbl">Present</div><b>{{ $summary['present'] }}</b></div>
  <div class="stat acc" style="--acc:#0a84ff"><div class="lbl">Half days</div><b>{{ $summary['half'] }}</b></div>
  <div class="stat acc" style="--acc:#ff9f0a"><div class="lbl">Leave</div><b>{{ $summary['leave'] }}</b></div>
  <div class="stat acc" style="--acc:#ff453a"><div class="lbl">Absent</div><b>{{ $summary['absent'] }}</b></div>
</div>
<div class="noprint" style="margin-bottom:12px"><form method="POST" action="{{ route('attendance.store') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">@csrf
@if($canAll)<input type="hidden" name="user_id" value="{{ $uid }}">@endif
<div><label class="f">Date</label><input class="in" type="date" name="day" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" style="margin:0" required></div>
<div><label class="f">Status</label><select class="in" name="status" style="margin:0">@foreach(\Modules\Work\Attendance::statuses() as $k=>$l)<option value="{{ $k }}" {{ ($todayMark ?? '')===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div>
<div style="flex:1;min-width:140px"><label class="f">Note</label><input class="in" name="note" maxlength="255" style="margin:0" placeholder="Optional…"></div>
<div><button class="btn sm">Mark</button></div></form></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Date</th><th>Day</th><th>Status</th><th>Note</th></tr>
@php $days = $start->daysInMonth; @endphp
@for($d = 1; $d <= $days; $d++)@php $dt = $start->copy()->day($d)->toDateString(); $s = $marks->get($dt); $future = $dt > date('Y-m-d'); $hol = ($holidays ?? collect())->get($dt); @endphp<tr @if($dt === date('Y-m-d')) style="background:var(--surface-2)" @endif><td class="nowrap">{{ $d }} {{ d($start,'M') }}</td><td>{{ $start->copy()->day($d)->format('D') }}@if($hol)<div class="hint">{{ $hol }}</div>@endif</td><td>@if($s)<span class="badge {{ $s==='present' ? 'b-green' : ($s==='absent' ? 'b-red' : ($s==='leave' ? 'b-amber' : 'b-blue')) }}">{{ \Modules\Work\Attendance::statuses()[$s] ?? $s }}</span>@elseif($hol)<span class="badge b-grey">holiday</span>@elseif($future)<span class="hint">—</span>@else<span class="hint">unmarked</span>@endif</td><td class="hint">{{ $notes->get($dt) ?: '—' }}</td></tr>@endfor</table></div>
@if(!empty($balances))<p class="hint" style="margin-top:10px">Leave left this year: @foreach(['sick'=>'Sick','casual'=>'Casual','earned'=>'Earned'] as $t=>$l)<b>{{ $l }} {{ $balances[$t]['left'] }}</b>@if(!$loop->last) · @endif @endforeach — <a href="{{ route('leaves.index') }}">apply</a></p>@endif
</div>
@endsection
