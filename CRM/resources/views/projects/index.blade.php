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
@php $fTotal = max(1, $funnel->sum()); $fColors = ['new'=>'#8e8e93','in_progress'=>'#0a84ff','on_hold'=>'#ff9f0a','completed'=>'#30d158','cancelled'=>'#48484a']; @endphp
<div class="funnel" style="margin-bottom:6px">
@foreach(['new','in_progress','on_hold','completed','cancelled'] as $fs)<div style="width:{{ max(($funnel[$fs] ?? 0) > 0 ? 9 : 0, round(($funnel[$fs] ?? 0) / $fTotal * 100, 1)) }}%;background:{{ $fColors[$fs] }}" title="{{ $fs }}: {{ $funnel[$fs] ?? 0 }}">{{ $funnel[$fs] ?? 0 }}</div>@endforeach
</div>
<div class="funnel-legend" style="margin-bottom:12px">@foreach(['new'=>'New','in_progress'=>'In progress','on_hold'=>'On hold','completed'=>'Completed','cancelled'=>'Cancelled'] as $fs=>$fl)<span><span class="dot" style="background:{{ $fColors[$fs] }}"></span>{{ $fl }} ({{ $funnel[$fs] ?? 0 }})</span>@endforeach</div>
<form method="GET" data-ajax-filter data-region="projects-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search project or client…"><select class="in" style="margin:0;flex:1;min-width:140px" name="status"><option value="">All statuses</option>@foreach(['new','in_progress','on_hold','completed','cancelled'] as $s)<option {{ ($status ?? '')===$s?'selected':'' }}>{{ $s }}</option>@endforeach</select><button class="btn sec sm">Filter</button>@if(($q ?? '') !== '' || ($status ?? '') !== '')<a class="btn sm sec" href="{{ route('projects.index') }}">Clear</a>@endif</form>
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
<tr><td><input type="checkbox" class="bulk-check" value="{{ $p->id }}" title="Select"></td><td><a href="{{ route('projects.show',$p) }}"><b>{{ $p->name }}</b></a><div class="hint">{{ $p->client->company ?? $p->client->name ?? '' }} · <span class="badge {{ $p->status==='completed' ? 'b-green' : ($p->status==='in_progress' ? 'b-blue' : ($p->status==='on_hold' ? 'b-amber' : 'b-grey')) }}">{{ $p->status }}</span></div></td>
<td style="min-width:130px"><div class="hint">{{ $p->done_tasks_count }}/{{ $p->tasks_count }} done</div><div class="statbar"><div style="width:{{ $tpct }}%"></div></div></td>
<td style="min-width:150px"><div class="hint">{{ money($cost) }} of {{ money($p->budget) }}</div><div class="statbar"><div style="width:{{ $bpct }}%;{{ $bpct >= 100 ? 'background:#ff453a' : '' }}"></div></div>@if($over)<div class="hint" style="color:var(--bad)">over budget</div>@endif</td>
<td class="nowrap" style="text-align:right">@if($profit < 0)<b style="color:var(--bad)">{{ money($profit) }}</b>@else{{ money($profit) }}@endif<div class="hint">of {{ money($p->billed_sum ?? 0) }} billed</div></td>
<td class="nowrap">@if($pOver)<span class="badge b-red" title="Past due date">overdue</span> @endif{{ d($p->due_date) }}</td>
<td><div class="row-actions"><a class="icon-btn" href="{{ route('projects.edit',$p) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Edit</a>
<form method="POST" action="{{ route('projects.destroy',$p) }}" style="display:inline" data-confirm="Delete project {{ $p->name }}?">@csrf @method('DELETE')<button class="icon-btn danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>Delete</button></form></div></td></tr>
@empty<tr><td colspan="7"><div class="empty">No projects found.<br><br><a class="btn sm" href="{{ route('projects.create') }}">Start your first project</a></div></td></tr>@endforelse</table></div>
{{ $projects->links() }}</div></div>
@endsection
