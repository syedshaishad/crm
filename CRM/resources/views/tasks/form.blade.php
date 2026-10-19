@extends('layouts.app')
@section('title',($task->exists ? 'Edit ' : 'New ').'task')
@section('content')
<div class="card" style="max-width:680px">
<h3>{{ $task->exists ? 'Edit task' : 'New task' }}</h3>
<form method="POST" action="{{ $task->exists ? route('tasks.update',$task) : route('tasks.store') }}">@csrf @if($task->exists)@method('PUT')@endif
<label class="f">What needs doing? <span class="req">*</span></label>
<input class="in" name="title" value="{{ old('title',$task->title) }}" required autofocus placeholder="e.g. Share homepage draft">
<div class="form-grid">
<div><label class="f">Project</label><select class="in" name="project_id"><option value="">No project</option>@foreach($projects as $p)<option value="{{ $p->id }}" {{ (string)old('project_id',$task->project_id)===(string)$p->id?'selected':'' }}>{{ $p->name }}</option>@endforeach</select></div>
<div><label class="f">Assign to</label><select class="in" name="assigned_to"><option value="">Nobody yet</option>@foreach($users as $u)<option value="{{ $u->id }}" {{ (string)old('assigned_to',$task->assigned_to)===(string)$u->id?'selected':'' }}>{{ $u->name }}</option>@endforeach</select></div>
</div>
<div class="fsec">Status</div>
<div class="pills">
@foreach(['todo'=>'To do','in_progress'=>'Doing','review'=>'Review','done'=>'Done'] as $v=>$l)
<label><input type="radio" name="status" value="{{ $v }}" {{ old('status',$task->status ?? 'todo')===$v?'checked':'' }}><span>{{ $l }}</span></label>
@endforeach
</div>
<div class="form-grid">
<div><div class="fsec" style="margin-top:0">Priority</div>
<div class="pills">
@foreach(['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'] as $v=>$l)
<label><input type="radio" name="priority" value="{{ $v }}" {{ old('priority',$task->priority ?? 'medium')===$v?'checked':'' }}><span>{{ $l }}</span></label>
@endforeach
</div></div>
<div><label class="f">Due date</label><input class="in" type="date" name="due_date" value="{{ old('due_date', $task->due_date ? $task->due_date->format('Y-m-d') : '') }}"></div>
<div><label class="f">Start date</label><input class="in" type="date" name="start_date" value="{{ old('start_date', $task->start_date ? $task->start_date->format('Y-m-d') : '') }}"></div>
<div><label class="f">Estimate</label><div style="display:flex;gap:8px"><input class="in" type="number" step="1" min="0" name="estimate_hours" value="{{ old('estimate_hours', intdiv((int)($task->estimate_minutes ?? 0),60)) }}" placeholder="Hrs" style="margin:0"><input class="in" type="number" step="1" min="0" max="59" name="estimate_minutes" value="{{ old('estimate_minutes', ((int)($task->estimate_minutes ?? 0))%60) }}" placeholder="Min" style="margin:0"></div></div>
</div>
<div class="form-grid">
<div><label class="f">Hourly rate (₹) <span class="hint">— blank = default</span></label><input class="in" type="number" step="0.01" min="0" name="hourly_rate" value="{{ old('hourly_rate', $task->hourly_rate) }}" placeholder="{{ setting('projects.hourly_rate',0) }}"></div>
<div><label class="f">Repeats</label><select class="in" name="recurs"><option value="none" {{ old('recurs',$task->recurs ?? 'none')==='none'?'selected':'' }}>One-off</option><option value="weekly" {{ old('recurs',$task->recurs ?? '')==='weekly'?'selected':'' }}>Weekly on done</option><option value="monthly" {{ old('recurs',$task->recurs ?? '')==='monthly'?'selected':'' }}>Monthly on done</option></select></div>
@if($task->baseline_due)<div><label class="f">Baseline due</label><input class="in" value="{{ d($task->baseline_due) }}{{ $task->due_date && $task->due_date->gt($task->baseline_due) ? ' · slipped '.$task->due_date->diffInDays($task->baseline_due).'d' : '' }}" disabled></div>@endif
</div>
<details class="adv"><summary>Add details (optional)</summary>
<div style="margin-top:10px"><label class="f">Description</label><textarea class="in" name="description" rows="3" placeholder="Context, links, acceptance criteria…">{{ old('description',$task->description) }}</textarea></div></details>
<div class="form-actions"><button class="btn" type="submit">{{ $task->exists ? 'Save changes' : 'Add task' }}</button><a class="btn sec" href="{{ route('tasks.index') }}">Cancel</a></div>
</form></div>
@if($task->exists)
<div class="card" style="max-width:680px"><div class="card-head"><h3>Waits on ({{ $task->blockers->count() }})</h3>@if($task->isBlocked())<span class="badge b-red">blocked</span>@endif</div>
@forelse($task->blockers as $dep)<div class="mi-row" style="border-color:var(--line)"><span style="flex:1"><b>{{ $dep->blocker->title ?? '—' }}</b> <span class="hint">{{ $dep->blocker->status ?? '' }}</span></span><form method="POST" action="{{ route('tasks.deps.destroy',$dep) }}" style="display:inline" data-confirm="Remove this dependency?">@csrf @method('DELETE')<button class="row-del">Delete</button></form></div>@empty<p class="hint">No dependencies — runs standalone.</p>@endforelse
<form method="POST" action="{{ route('tasks.deps.store',$task) }}" style="display:flex;gap:8px;margin-top:12px;align-items:end">@csrf<div style="flex:1"><label class="f">Blocked by</label><select class="in" name="depends_on" style="margin:0" required>@foreach($siblings as $s)<option value="{{ $s->id }}">{{ \Str::limit($s->title,50) }} ({{ $s->status }})</option>@endforeach</select></div><div><button class="btn sm">Add</button></div></form></div>
@endif
@if($task->exists)
<div class="card" style="max-width:680px"><div class="card-head"><h3>Client sign-off link</h3></div>
@if($task->public_token)
<p class="hint" style="margin-top:0">No login needed — the client opens, reviews and accepts.</p>
<code style="overflow-wrap:anywhere">{{ route('tasks.public',$task->public_token) }}</code>
<div class="btnrow" style="margin-top:8px"><button class="btn sec sm" onclick="navigator.clipboard.writeText('{{ route('tasks.public',$task->public_token) }}');this.textContent='Copied!';setTimeout(()=>this.textContent='Copy link',1500)">Copy link</button></div>
@else
<p class="hint" style="margin:0">Generate a link the client can open to accept this task.</p>
<form method="POST" action="{{ route('tasks.share',$task) }}" style="margin-top:10px">@csrf<button class="btn sec sm">Create client link</button></form>
@endif</div>
@endif
@if($task->exists && ($checklistTemplates ?? collect())->isNotEmpty())
<div class="card" style="max-width:680px"><div class="card-head"><h3>Checklist templates</h3></div>
<form method="POST" action="{{ route('tasks.applyChecklist',$task) }}" style="display:flex;gap:8px;align-items:end">@csrf<select class="in" name="template_id" style="margin:0;flex:1" required>@foreach($checklistTemplates as $ct)<option value="{{ $ct->id }}">{{ $ct->name }} ({{ count($ct->lines()) }} steps)</option>@endforeach</select><button class="btn sec sm">Apply</button></form></div>
@endif
@if($task->exists)
<div class="card" style="max-width:680px"><div class="card-head"><h3>Field checklist ({{ $task->checklist->where('is_done',true)->count() }}/{{ $task->checklist->count() }})</h3>@if($task->handed_over_at)<span class="badge b-green">handed over</span>@endif</div>
@forelse($task->checklist as $c)<div class="mi-row" style="border-color:var(--line)"><form method="POST" action="{{ route('tasks.checklist.toggle',$c) }}" style="display:inline">@csrf<button type="submit" class="rowcheck {{ $c->is_done ? 'done' : '' }}" title="Tick / untick">{{ $c->is_done ? '✓' : '' }}</button></form><span style="flex:1;{{ $c->is_done ? 'text-decoration:line-through;opacity:.6' : '' }}">{{ $c->label }}</span><form method="POST" action="{{ route('tasks.checklist.destroy',$c) }}" style="display:inline" data-confirm="Remove this step?">@csrf @method('DELETE')<button class="row-del">Delete</button></form></div>@empty<p class="hint">No steps — break the site work into tickable steps.</p>@endforelse
<form method="POST" action="{{ route('tasks.checklist.store',$task) }}" style="display:flex;gap:8px;margin-top:12px">@csrf<input class="in" name="label" required maxlength="255" style="margin:0;flex:1" placeholder="Add a step — e.g. Test all sockets…"><button class="btn sm">Add</button></form></div>
@endif
@if($task->exists)
<div class="card" style="max-width:680px"><div class="card-head"><h3>Client handover</h3>@if($task->handed_over_at)<span class="hint">{{ d($task->handed_over_at,'j M Y, h:i A') }}</span>@endif</div>
@if($task->handed_over_at)
<p style="margin:0">Handed over to <b>{{ $task->handed_over_to }}</b>@if($task->handover_note)<span class="hint"> — {{ $task->handover_note }}</span>@endif</p>
@else
<p class="hint" style="margin-top:0">Needs every checklist step done + at least one site photo attached below. @if(!$task->hasPhoto())<b>No photo attached yet.</b>@endif</p>
<form method="POST" action="{{ route('tasks.handover',$task) }}">@csrf
<div class="form-grid"><div><label class="f">Handed over to <span class="req">*</span></label><input class="in" name="handed_over_to" required maxlength="255" placeholder="Client name + signature note"></div>
<div><label class="f">Note</label><input class="in" name="handover_note" maxlength="255" placeholder="Optional — snags, warranty…"></div></div>
<div class="form-actions"><button class="btn" type="submit" data-confirm="Complete handover? The task closes as done.">Complete handover</button></div></form>
@endif</div>
@endif
@if($task->exists)
<div class="card" style="max-width:680px"><div class="card-head"><h3>Time · {{ \Modules\Tasks\TaskTime::fmt((int)($timeTotal ?? 0)) }}@if(($task->estimate_minutes ?? 0) > 0) / {{ \Modules\Tasks\TaskTime::fmt((int)$task->estimate_minutes) }} est.@endif</h3><span class="hint">who worked how long @ {{ money($task->rate()) }}/hr = {{ money($task->laborCost()) }}</span></div>
@forelse($task->times as $t)<div class="mi-row" style="border-color:var(--line)"><span class="av sm">{{ strtoupper(substr($t->user->name ?? '?',0,1)) }}</span><span style="flex:1"><b>{{ \Modules\Tasks\TaskTime::fmt($t->minutes) }}</b> <span class="hint">· {{ d($t->worked_on) }} · {{ $t->user->name ?? '—' }}</span>@if($t->note)<span style="white-space:pre-line">{{ $t->note }}</span>@endif</span>@if($t->user_id === auth()->id() || auth()->user()->canManageInvoices())<form method="POST" action="{{ route('tasks.time.destroy',$t) }}" style="display:inline" data-confirm="Remove this time entry?">@csrf @method('DELETE')<button class="row-del">Delete</button></form>@endif</div>@empty<p class="hint">No time logged yet.</p>@endforelse
<form method="POST" action="{{ route('tasks.time.store',$task) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:12px">@csrf
<div><label class="f">Hours</label><input class="in" type="number" step="0.25" min="0" name="hours" style="margin:0;width:90px" placeholder="0"></div>
<div><label class="f">Minutes</label><input class="in" type="number" step="1" min="0" name="minutes" style="margin:0;width:90px" placeholder="0"></div>
<div><label class="f">Date</label><input class="in" type="date" name="worked_on" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" style="margin:0" required></div>
<div style="flex:1;min-width:140px"><label class="f">Note</label><input class="in" name="note" maxlength="255" style="margin:0" placeholder="What got done…"></div>
<div><button class="btn sm">Log time</button></div></form></div>
@endif
@if($task->exists)
<div class="card" style="max-width:680px"><div class="card-head"><h3>Discussion ({{ $task->comments->count() }})</h3></div>
@forelse($task->comments as $c)<div class="mi-row" style="border-color:var(--line);align-items:flex-start"><span class="av sm">{{ strtoupper(substr($c->user->name ?? '?',0,1)) }}</span><span style="flex:1;min-width:0"><b>{{ $c->user->name ?? 'Someone' }} <span class="hint">· {{ d($c->created_at, 'j M, h:i A') }}</span></b><span style="white-space:pre-line">{{ $c->body }}</span>@if($c->file_path)<span><a class="icon-btn" href="{{ asset('storage/'.$c->file_path) }}" target="_blank" title="Open attachment"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 12-8.5 8.5a5.5 5.5 0 0 1-7.8-7.8L13 4.5a3.7 3.7 0 0 1 5.2 5.2l-8.2 8.2a1.85 1.85 0 0 1-2.6-2.6L14 8.7"/></svg>{{ \Str::limit($c->file_name ?? 'file', 28) }}</a></span>@endif</span>@if($c->user_id === auth()->id() || auth()->user()->canManageInvoices())<form method="POST" action="{{ route('tasks.comments.destroy',$c) }}" style="display:inline" data-confirm="Delete this comment?">@csrf @method('DELETE')<button class="row-del">Delete</button></form>@endif</div>@empty<p class="hint">No comments yet — kick off the discussion.</p>@endforelse
<form method="POST" action="{{ route('tasks.comments.store',$task) }}" enctype="multipart/form-data" style="margin-top:12px">@csrf
<textarea class="in" name="body" rows="2" required placeholder="Write a comment…"></textarea>
<div class="up-inline"><input class="in" style="margin:0;flex:1;min-width:160px" type="file" name="file"><button class="btn sm">Post</button></div></form></div>
@endif
@endsection
