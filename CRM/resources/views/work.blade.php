@extends('layouts.app')
@section('title','Work')
@section('content')
@if(module_visible('Tasks'))
<div class="cols-2">
<div class="panel-dark">
  <div class="card-head"><div><h3>Today's focus</h3><span class="hint">Most urgent open tasks</span></div><div class="btnrow" style="gap:8px"><a class="hint" href="{{ route('tasks.index') }}">All tasks →</a><div class="has-drop"><button type="button" class="icon-btn" data-drop-toggle aria-label="Card menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg>Menu</button><div class="drop"><a href="{{ route('tasks.index') }}">All tasks</a><a href="{{ route('tasks.create') }}">New task</a></div></div></div></div>
  @forelse($focusTasks as $t)
  <div class="mi-row">
    <form method="POST" action="{{ route('tasks.toggle',$t->id) }}">@csrf<button type="submit" class="check-btn" title="Mark done / reopen" aria-label="Mark done or reopen {{ $t->title }}"></button></form>
    <div style="flex:1;min-width:0"><b>{{ $t->title }}</b><span>{{ $t->project ?? 'No project' }} · {{ d($t->due_date) ?: 'no due date' }} · {{ $t->priority }}</span></div>
    <a class="hint" href="{{ route('tasks.edit',$t->id) }}">Edit</a>
  </div>
  @empty<div class="empty" style="color:#a8a191">All clear — nothing open.</div>@endforelse
</div>
<div class="card">
  <div class="card-head"><div><h3>{{ date('F Y', mktime(0,0,0,$calM,1,$calY)) }}</h3><span class="hint">Due dates this month</span></div><div class="btnrow" style="gap:8px"><a class="hint" href="{{ route('tasks.index') }}">Tasks →</a><div class="has-drop"><button type="button" class="icon-btn" data-drop-toggle aria-label="Card menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg>Menu</button><div class="drop"><a href="{{ route('tasks.index') }}">All tasks</a><a href="{{ route('tasks.create') }}">New task</a></div></div></div></div>
  <div class="cal">
    @foreach(['M','T','W','T','F','S','S'] as $dow)<div class="cal-dow">{{ $dow }}</div>@endforeach
    @for($b = 1; $b < $calStart; $b++)<div class="cal-d blank"></div>@endfor
    @for($day = 1; $day <= $calDays; $day++)
      @php $key = sprintf('%04d-%02d-%02d', $calY, $calM, $day); $dots = $dueMap[$key] ?? []; @endphp
      <div class="cal-d {{ $key === date('Y-m-d') ? 'today' : '' }}">{{ $day }}@if($dots)<div class="cal-dots">@if(!empty($dots['tasks']))<i></i>@endif @if(!empty($dots['invoices']))<i class="warn"></i>@endif</div>@endif</div>
    @endfor
  </div>
  <h4>Collections this week</h4>
  <div class="weekbars">@foreach($week as $w)<div class="wb {{ $w['value'] > 0 ? 'hot' : '' }}" style="height:{{ max(6, round($w['value'] / $weekMax * 100)) }}%" title="{{ $w['label'] }}: {{ money($w['value']) }}"></div>@endforeach</div>
  <div class="wb-labels">@foreach($week as $w)<span>{{ substr($w['label'], 0, 1) }}</span>@endforeach</div>
</div>
</div>
@endif
<div class="cols-2">
@if(module_visible('Tasks'))
<div class="card"><div class="card-head"><div><h3>Recent tasks</h3><span class="hint">Latest activity</span></div><div class="btnrow" style="gap:8px"><a class="btn sm sec" href="{{ route('tasks.create') }}">+ New</a><div class="has-drop"><button type="button" class="icon-btn" data-drop-toggle aria-label="Card menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg>Menu</button><div class="drop"><a href="{{ route('tasks.index') }}">View all tasks</a><a href="{{ route('tasks.create') }}">New task</a></div></div></div></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Title</th><th>Status</th><th>Due</th></tr>
@forelse($recentTasks as $t)<tr><td>{{ $t->title }}</td><td><span class="badge {{ $t->status==='done' ? 'b-green' : ($t->status==='in_progress' ? 'b-blue' : 'b-grey') }}">{{ $t->status }}</span></td><td>{{ d($t->due_date) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">No tasks yet.<br><br><a class="btn sm" href="{{ route('tasks.create') }}">Add your first task</a></div></td></tr>@endforelse
</table></div></div>
@endif
@if(module_visible('Tasks'))
<div class="card"><div class="card-head"><div><h3>Due within 7 days</h3><span class="hint">Upcoming deadlines</span></div><div class="btnrow" style="gap:8px"><a class="hint" href="{{ route('tasks.index') }}">View all →</a><div class="has-drop"><button type="button" class="icon-btn" data-drop-toggle aria-label="Card menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg>Menu</button><div class="drop"><a href="{{ route('tasks.index') }}">View all tasks</a><a href="{{ route('tasks.create') }}">New task</a></div></div></div></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Task</th><th>Due</th><th>Status</th></tr>
@forelse($dueSoon as $t)<tr><td>{{ $t->title }}</td><td>{{ d($t->due_date) }}</td><td><span class="badge b-amber">{{ $t->status }}</span></td></tr>@empty<tr><td colspan="3"><div class="empty">Nothing due soon.</div></td></tr>@endforelse
</table></div></div>
@endif
</div>
@endsection
