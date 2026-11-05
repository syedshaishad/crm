@extends('layouts.app')
@section('title','Projects')
@section('content')
<div class="grid stats cols-4">
  <div class="stat"><div class="lbl">Total projects</div><b>{{ $stats['total'] }}</b><div class="sub">{{ $stats['active'] }} active right now</div></div>
  <div class="stat"><div class="lbl">Completed</div><b>{{ $stats['completed'] }}</b><div class="sub">shipped</div></div>
  <div class="stat"><div class="lbl">Combined budget</div><b>{{ money($stats['budget']) }}</b><div class="sub">across all projects</div></div>
  <div class="stat"><div class="lbl">Start</div><b style="font-size:18px"><a href="{{ route('projects.create') }}">+ New project</a></b><div class="sub">takes 30 seconds</div></div>
</div>

<div class="card" style="margin-top:22px">
<div class="card-head"><h3>Projects</h3></div>
@php
  $fSum = (int) $funnel->sum();
  $fTotal = max(1, $fSum);
  $fColors = ['new'=>'var(--dc-faint)','in_progress'=>'var(--dc-blue)','on_hold'=>'var(--dc-amber)','completed'=>'var(--dc-green)','cancelled'=>'var(--dc-muted)'];
  $fLabels = ['new'=>'New','in_progress'=>'In progress','on_hold'=>'On hold','completed'=>'Completed','cancelled'=>'Cancelled'];
  $fMoney = fn ($v) => preg_replace('/\.00$/', '', money($v));
@endphp
<div class="status-summary" aria-label="Projects by status">
@foreach($fLabels as $fs=>$fl)
  @php $n = (int) ($funnel[$fs] ?? 0); $pct = $n > 0 ? (int) round($n / $fTotal * 100) : 0; @endphp
  <span class="status-summary-item" title="{{ $fl }}: {{ $n }} of {{ $fSum }} projects">
    <span class="status-summary-dot" style="background:{{ $fColors[$fs] }}"></span>
    <span class="status-summary-name">{{ $fl }}</span>
    <b>{{ $n }}</b>
    <span class="status-summary-pct">{{ $pct }}%</span>
  </span>
@endforeach
</div>
<form method="GET" data-ajax-filter data-region="projects-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search project or client…"><select class="in" style="margin:0;flex:1;min-width:140px" name="status"><option value="">All statuses</option>@foreach($fLabels as $s=>$sl)<option value="{{ $s }}" {{ ($status ?? '')===$s?'selected':'' }}>{{ $sl }}</option>@endforeach</select><button class="btn sec sm">Filter</button>@if(($q ?? '') !== '' || ($status ?? '') !== '')<a class="btn sm sec" href="{{ route('projects.index') }}">Clear</a>@endif</form>
<div class="bulkbar noprint" data-bulk-bar data-bulk-url="{{ route('projects.bulk') }}" data-region="projects-list" hidden>
<span class="sel"><b data-bulk-count>0</b> selected</span>
<select data-bulk-value name="status"><option value="">Set status…</option><option value="new">New</option><option value="in_progress">In progress</option><option value="on_hold">On hold</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select>
<button class="btn sm sec" data-bulk-apply="status">Apply</button>
<span class="spacer"></span>
<button class="btn sm danger" data-bulk-apply="delete">Delete</button>
</div>
<div data-ajax-region="projects-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th style="width:34px"><input type="checkbox" data-bulk-all title="Select all"></th><th>Project</th><th>Tasks</th><th>Budget vs cost</th><th style="text-align:right">Profit</th><th>Due</th><th></th></tr>
@forelse($projects as $p)
@php $tpct = $p->tasks_count > 0 ? round($p->done_tasks_count / $p->tasks_count * 100) : 0; $cost = (float) ($p->costs_sum ?? 0); $bpct = $p->budget > 0 ? min(100, round($cost / $p->budget * 100)) : 0; $profit = round((float)($p->billed_sum ?? 0) - $cost, 2); $over = $p->budget > 0 && $cost > $p->budget; $pOver = $p->due_date && substr((string) $p->due_date, 0, 10) < date('Y-m-d') && !in_array($p->status, ['completed','cancelled']); @endphp
<tr><td><input type="checkbox" class="bulk-check" value="{{ $p->id }}" title="Select"></td><td><a href="{{ route('projects.show',$p) }}"><b>{{ $p->name }}</b></a><div class="proj-meta"><span class="proj-client">{{ $p->client->company ?? $p->client->name ?? '' }}</span><span class="proj-state"><i style="background:{{ $fColors[$p->status] ?? '#8e8e93' }}"></i>{{ $fLabels[$p->status] ?? ucfirst(str_replace('_',' ',$p->status)) }}</span></div></td>
<td style="min-width:130px">@if($p->tasks_count > 0)<div class="hint proj-meter-head"><span>{{ $p->done_tasks_count }}/{{ $p->tasks_count }} done</span><b>{{ $tpct }}%</b></div><div class="statbar proj-bar"><div style="width:{{ $tpct }}%;--proj-bar:var(--dc-green)"></div></div>@else<div class="hint">No tasks yet</div>@endif</td>
<td style="min-width:150px">@if($p->budget > 0)@php $bTone = $over ? 'var(--dc-red)' : ($bpct >= 80 ? 'var(--dc-amber)' : 'var(--dc-blue)'); @endphp<div class="hint proj-meter-head"><span>{{ $fMoney($cost) }} of {{ $fMoney($p->budget) }}</span><b>{{ $bpct }}%</b></div><div class="statbar proj-bar"><div style="width:{{ $bpct }}%;--proj-bar:{{ $bTone }}"></div></div>@if($over)<div class="hint" style="color:var(--bad)">over budget</div>@endif @else<div class="hint">{{ money($cost) }} cost · no budget set</div>@endif</td>
<td class="nowrap" style="text-align:right">@if($profit < 0)<b style="color:var(--bad)">{{ money($profit) }}</b>@else{{ money($profit) }}@endif<div class="hint">of {{ money($p->billed_sum ?? 0) }} billed</div></td>
<td class="nowrap">@if($pOver)<span class="badge b-red" title="Past due date">overdue</span> @endif{{ d($p->due_date) }}</td>
<td><div class="row-actions"><a class="icon-btn" href="{{ route('projects.edit',$p) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Edit</a>
<form method="POST" action="{{ route('projects.destroy',$p) }}" style="display:inline" data-confirm="Delete project {{ $p->name }}?">@csrf @method('DELETE')<button class="icon-btn danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>Delete</button></form></div></td></tr>
@empty<tr><td colspan="7"><div class="empty">No projects found.<br><br><a class="btn sm" href="{{ route('projects.create') }}">Start your first project</a></div></td></tr>@endforelse</table></div>
{{ $projects->links() }}</div></div>
@endsection
