@extends('layouts.app')
@section('title','Staff performance')
@section('content')
<div class="card">
<div class="card-head"><h3>Leaderboard · {{ d($from,'M Y') }}</h3><span class="btnrow noprint" style="gap:8px"><a class="btn sec sm" href="{{ route('reports.staff',['month'=>$prev]) }}">← {{ d($prev.'-01','M Y') }}</a>@if($next)<a class="btn sec sm" href="{{ route('reports.staff',['month'=>$next]) }}">{{ d($next.'-01','M Y') }} →</a>@endif</span></div>
<p class="hint" style="margin:0 0 12px">Collections are credited to whoever the invoice is assigned to; billing to whoever prepared it. Targets and commission rates are set per user in Settings → Users.</p>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>#</th><th>Staff</th><th style="text-align:right">Collected</th><th style="text-align:right">Target</th><th style="min-width:140px">Progress</th><th style="text-align:right">Billed</th></tr>
@php $payout = 0; @endphp
@forelse($rows as $i=>$r)@php $t = (float)($r['user']->monthly_target ?? 0); $pct = $t > 0 ? min(100, round($r['collected']/$t*100)) : null; $rate = (float)($r['user']->commission_rate ?? 0); $comm = round($r['collected']*$rate/100, 2); $payout += $comm; $commLine = $rate > 0 ? ' · comm '.rtrim(rtrim(number_format($rate, 2), '0'), '.').'% = '.money($comm) : ''; @endphp<tr><td>{{ $i+1 }}</td><td><b>{{ $r['user']->name }}</b><div class="hint">{{ ucfirst($r['user']->role) }}</div></td><td class="nowrap" style="text-align:right"><b>{{ money($r['collected']) }}</b><div class="hint">{{ $r['receipts'] }} receipts{{ $commLine }}</div></td><td class="nowrap" style="text-align:right">{{ $t > 0 ? money($t) : '—' }}</td><td>@if($pct === null)<span class="hint">no target</span>@else<div class="progress" style="margin:0"><div style="width:{{ $pct }}%"></div></div><div class="hint">{{ $pct }}%</div>@endif</td><td class="nowrap" style="text-align:right">{{ money($r['billed']) }}<div class="hint">{{ $r['invoices'] }} invoices</div></td></tr>@empty<tr><td colspan="6"><div class="empty">No active users.</div></td></tr>@endforelse</table></div>
@if($payout > 0)<p class="hint" style="margin:12px 0 0">Total commission payable for {{ d($from,'M Y') }}: <b>{{ money($payout) }}</b>.</p>@endif
@if($unassigned['receipts'] > 0 || ($unbilled['billed'] ?? 0) > 0)<p class="hint" style="margin:12px 0 0">Unattributed this month: {{ money($unassigned['collected']) }} collected on {{ $unassigned['receipts'] }} unassigned receipts · {{ money($unbilled['billed'] ?? 0) }} billed without a recorded preparer.</p>@endif
</div>
@endsection
