@extends('layouts.app')
@section('title','Tasks')
@section('content')
<div class="card">
<div class="card-head"><h3>Tasks</h3>
<div class="btnrow">
  <div class="seg" role="tablist" aria-label="Views"><a class="{{ ($view ?? 'list')==='list' ? 'on' : '' }}" href="{{ route('tasks.index', array_merge(request()->except(['view', 'page']), ['view' => 'list'])) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>Table</a><a class="{{ ($view ?? 'list')==='calendar' ? 'on' : '' }}" href="{{ route('tasks.index', array_merge(request()->except(['view', 'page']), ['view' => 'calendar'])) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>Calendar</a></div>
  <a class="btn sm" href="{{ route('tasks.create') }}">+ Create</a>
  <a class="btn sm sec" href="{{ route('tasks.templates') }}">Templates</a>
</div></div>
@php
  $sSum = (int) $funnel->sum();
  $sTotal = max(1, $sSum);
  $sColors = ['todo'=>'var(--dc-faint)','in_progress'=>'var(--dc-blue)','review'=>'var(--dc-amber)','done'=>'var(--dc-green)'];
  $sLabels = ['todo'=>'To do','in_progress'=>'Doing','review'=>'Review','done'=>'Done'];
  $pColors = ['low'=>'var(--dc-faint)','medium'=>'var(--dc-blue)','high'=>'var(--dc-amber)','urgent'=>'var(--dc-red)'];
  $pLabels = ['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'];
@endphp
<div class="status-summary" aria-label="Tasks by status">
@foreach($sLabels as $fs=>$fl)
  @php $n = (int) ($funnel[$fs] ?? 0); $pct = $n > 0 ? (int) round($n / $sTotal * 100) : 0; @endphp
  <span class="status-summary-item" title="{{ $fl }}: {{ $n }} of {{ $sSum }} tasks">
    <span class="status-summary-dot" style="background:{{ $sColors[$fs] }}"></span>
    <span class="status-summary-name">{{ $fl }}</span>
    <b>{{ $n }}</b>
    <span class="status-summary-pct">{{ $pct }}%</span>
  </span>
@endforeach
</div>

<form method="GET" data-ajax-filter data-region="tasks-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search tasks…"><input type="hidden" name="view" value="{{ $view ?? 'list' }}">@if(($view ?? 'list') === 'calendar')<input type="hidden" name="month" value="{{ $calMonth ?? '' }}">@endif<select class="in" style="margin:0;flex:1;min-width:130px" name="status"><option value="">All statuses</option>@foreach($sLabels as $s=>$sl)<option value="{{ $s }}" {{ ($status ?? '')===$s?'selected':'' }}>{{ $sl }}</option>@endforeach</select><select class="in" style="margin:0;flex:1;min-width:120px" name="priority"><option value="">Any priority</option>@foreach($pLabels as $pr=>$pl)<option value="{{ $pr }}" {{ ($priority ?? '')===$pr?'selected':'' }}>{{ $pl }}</option>@endforeach</select><select class="in" style="margin:0;flex:1;min-width:140px" name="client_id"><option value="">All clients</option>@foreach($clients as $c)<option value="{{ $c->id }}" {{ (string)($client_id ?? '')===(string)$c->id?'selected':'' }}>{{ $c->company ?: $c->name }}</option>@endforeach</select><button class="btn sec sm">Filter</button>
@if(!empty($day) || ($q ?? '') !== '' || ($status ?? '') !== '' || ($priority ?? '') !== '' || ($client_id ?? '') !== '')<a class="btn sec sm" href="{{ route('tasks.index', ['view' => $view ?? 'list']) }}">Clear{{ !empty($day) ? ' '.$day : '' }} ✕</a>@endif</form>

<form method="POST" action="{{ route('tasks.store') }}" data-ajax-add data-region="tasks-list" class="noprint quickadd" style="display:flex;gap:8px;margin-bottom:6px;flex-wrap:wrap;align-items:center">@csrf
<div class="cap" style="flex-basis:100%">Quick add — drops straight into the list above</div>
<input class="in" style="margin:0;flex:3;min-width:200px" name="title" required placeholder="Quick add: what needs doing? ↵" autocomplete="off">
<select class="in" style="margin:0;flex:1;min-width:140px" name="project_id"><option value="">No project</option>@foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
<input class="in" style="margin:0;flex:1;min-width:140px" type="date" name="due_date" value="{{ date('Y-m-d') }}">
<button class="btn sm">Add</button></form>
</div>

@if(($view ?? 'list') === 'calendar')
<div data-ajax-region="tasks-list">
<div class="card">
<div class="card-head"><h3>{{ date('F Y', mktime(0,0,0,$calM,1,$calY)) }}</h3>
<div class="btnrow"><a class="btn sec sm" data-cal-nav href="{{ route('tasks.index', array_merge(request()->except(['month', 'page']), ['view' => 'calendar', 'month' => $prevMonth])) }}">←</a><a class="btn sec sm" data-cal-nav href="{{ route('tasks.index', array_merge(request()->except(['month', 'page']), ['view' => 'calendar'])) }}">Today</a><a class="btn sec sm" data-cal-nav href="{{ route('tasks.index', array_merge(request()->except(['month', 'page']), ['view' => 'calendar', 'month' => $nextMonth])) }}">→</a></div></div>
<div class="cal cal-big">
@foreach(['M','T','W','T','F','S','S'] as $dow)<div class="cal-dow">{{ $dow }}</div>@endforeach
@for($b = 1; $b < $calStart; $b++)<div class="cal-d blank"></div>@endfor
@for($dayN = 1; $dayN <= $calDays; $dayN++)
@php $key = sprintf('%04d-%02d-%02d', $calY, $calM, $dayN); $items = $calMap[$key] ?? []; @endphp
<div class="cal-d day {{ $key === date('Y-m-d') ? 'today' : '' }} {{ ($day ?? '') === $key ? 'picked' : '' }}" data-date="{{ $key }}">
<a class="dnum" href="{{ route('tasks.index', ['view' => 'list', 'day' => $key]) }}">{{ $dayN }}</a>
  @include('tasks._pills', ['items' => $items, 'date' => $key])
</div>
@endfor
</div>
</div>
</div>
@else
<div class="card" style="padding-top:0">
<div class="bulkbar noprint" data-bulk-bar data-bulk-url="{{ route('tasks.bulk') }}" data-region="tasks-list" hidden>
<span class="sel"><b data-bulk-count>0</b> selected</span>
<select data-bulk-value name="status"><option value="">Set status…</option><option value="todo">To do</option><option value="in_progress">Doing</option><option value="review">Review</option><option value="done">Done</option></select>
<button class="btn sm sec" data-bulk-apply="status">Apply</button>
<span class="spacer"></span>
<button class="btn sm danger" data-bulk-apply="delete">Delete</button>
</div>
<div data-ajax-region="tasks-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th style="width:34px"><input type="checkbox" data-bulk-all title="Select all"></th><th>Done</th><th>Title</th><th>Project</th><th>Who</th><th>Status</th><th>Priority</th><th>Due</th><th></th></tr>
@forelse($tasks as $t)
@php
  $pri = $t->priority ?? 'medium';
  $tOver = $t->due_date && substr((string) $t->due_date, 0, 10) < date('Y-m-d') && !in_array($t->status, ['done','completed']);
@endphp
<tr class="{{ $t->status === 'done' ? 'is-done' : '' }}">
<td><input type="checkbox" class="bulk-check" value="{{ $t->id }}" title="Select"></td>
<td><form method="POST" action="{{ route('tasks.toggle',$t) }}" data-ajax-toggle>@csrf<button type="submit" class="rowcheck {{ $t->status === 'done' ? 'done' : '' }}" title="Done / reopen">{{ $t->status === 'done' ? '✓' : '' }}</button></form></td>
<td><a href="{{ route('tasks.edit',$t) }}">{{ $t->title }}</a></td>
<td>@if($t->project)<a href="{{ route('projects.show',$t->project) }}">{{ $t->project->name }}</a>@if($t->project->client)<div class="hint">{{ $t->project->client->company ?: $t->project->client->name }}</div>@endif @else <span class="hint">—</span>@endif</td>
<td>@if($t->assignee)<span class="who"><span class="av sm">{{ strtoupper(substr($t->assignee->name,0,1)) }}</span>{{ $t->assignee->name }}</span>@else <span class="hint">—</span>@endif</td>
<td><span class="task-tag"><i style="background:{{ $sColors[$t->status] ?? 'var(--dc-faint)' }}"></i>{{ $sLabels[$t->status] ?? ucfirst(str_replace('_',' ',$t->status)) }}</span></td>
<td><span class="task-tag {{ $pri === 'urgent' ? 'is-urgent' : '' }}"><i style="background:{{ $pColors[$pri] ?? 'var(--dc-faint)' }}"></i>{{ $pLabels[$pri] ?? ucfirst($pri) }}</span></td>
<td class="nowrap"><div>{{ d($t->due_date) }}</div>@if($tOver)<div class="task-overdue"><i></i>Overdue</div>@endif</td>
<td><div class="row-actions"><a class="icon-btn" href="{{ route('tasks.edit',$t) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Edit</a>
<form method="POST" action="{{ route('tasks.destroy',$t) }}" style="display:inline" data-confirm="Delete task?">@csrf @method('DELETE')<button class="icon-btn danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>Delete</button></form></div></td></tr>
@empty<tr><td colspan="9"><div class="empty">No tasks found.@if(!empty($day)) <a href="{{ route('tasks.index') }}">Clear date filter</a>@endif</div></td></tr>@endforelse</table></div>
{{ $tasks->links() }}</div></div>
@endif
@endsection
