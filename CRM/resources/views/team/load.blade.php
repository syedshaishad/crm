@extends('layouts.app')
@section('title','Team load')
@section('content')
<div class="card">
<div class="card-head"><h3>Who is drowning, who has room</h3><span class="hint">{{ d($weekStart) }} – {{ d($weekEnd) }} · {{ $defaultCap }}h week</span></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Who</th><th style="text-align:right">Open</th><th style="text-align:right">Due wk</th><th style="text-align:right">Overdue</th><th style="text-align:right">Logged wk</th><th style="width:26%">Utilization</th><th></th></tr>
@forelse($rows as $r)<tr>
<td><b>{{ $r['user']->name }}</b>@if($r['user']->designation)<div class="hint">{{ $r['user']->designation }}</div>@endif</td>
<td class="nowrap" style="text-align:right">{{ $r['open'] }}</td>
<td class="nowrap" style="text-align:right">{{ $r['dueWeek'] }}</td>
<td class="nowrap" style="text-align:right">@if($r['overdue'] > 0)<span class="badge b-red">{{ $r['overdue'] }}</span>@else<span class="hint">0</span>@endif</td>
<td class="nowrap" style="text-align:right">{{ \Modules\Tasks\TaskTime::fmt($r['mins']) }}</td>
<td><div class="statbar"><div style="width:{{ min(100,$r['pct']) }}%;{{ $r['pct'] >= 100 ? 'background:#ff453a' : ($r['pct'] >= 80 ? 'background:#ff9f0a' : '') }}"></div></div><div class="hint">{{ $r['pct'] }}% of {{ $r['cap'] }}h</div></td>
<td class="noprint">@if($r['open'] > 0)<form method="POST" action="{{ route('team.rebalance') }}" data-confirm="Move the ticked tasks off {{ $r['user']->name }}?">@csrf<input type="hidden" name="from_id" value="{{ $r['user']->id }}"><div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap"><select class="in" name="to_id" style="margin:0;width:auto" required><option value="">Move to…</option>@foreach($rows as $o)@if($o['user']->id !== $r['user']->id)<option value="{{ $o['user']->id }}">{{ $o['user']->name }}</option>@endif @endforeach</select><button class="btn sec sm">Move ticked</button></div><div style="margin-top:6px;max-height:120px;overflow:auto">@foreach($r['tasks'] as $t)<label class="hint" style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="task_ids[]" value="{{ $t->id }}" style="width:auto;margin:0"> {{ \Str::limit($t->title,40) }}@if($t->due_date) · {{ d($t->due_date) }}@endif</label>@endforeach</div></form>@else<span class="hint">—</span>@endif</td></tr>
@empty<tr><td colspan="7"><div class="empty">No active staff.</div></td></tr>@endforelse</table></div>
<p class="fhint">Utilization = hours logged this week ÷ weekly capacity. Tick tasks and move them to whoever has room.</p></div>
@endsection
