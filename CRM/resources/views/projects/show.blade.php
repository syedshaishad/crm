@extends('layouts.app')
@section('title',$project->name)
@section('content')
@php
  $stColor = $project->status === 'completed' ? 'b-green' : ($project->status === 'in_progress' ? 'b-blue' : ($project->status === 'on_hold' ? 'b-amber' : 'b-grey'));
  $doneCount = $project->tasks->where('status','done')->count();
  $taskCount = $project->tasks->count();
  $pcost = (float) $project->expenses()->where('status','approved')->sum('amount');
  $puse = $project->budget > 0 ? min(100, round($pcost / $project->budget * 100)) : 0;
  $pbilled = $project->billedTotal();
  $pprofit = round($pbilled - $pcost, 2);
  $pmargin = $pbilled > 0 ? round($pprofit / $pbilled * 100, 1) : 0;
  $overBudget = $project->budget > 0 && $pcost > $project->budget;
  $plab = $labor ?? 0;
  $trueCost = round($pcost + $plab, 2);
  $trueProfit = round($pbilled - $trueCost, 2);
  $estH = \Modules\Tasks\TaskTime::fmt((int)($estMin ?? 0));
  $workedH = \Modules\Tasks\TaskTime::fmt((int)($workedMin ?? 0));
@endphp
<div class="inv-hero noprint">
  <div><div class="inv-num">{{ $project->name }}</div>
    <div class="inv-chips">
      <span class="badge {{ $stColor }}">{{ $project->status }}</span>
      <span class="chip">Client: <a href="{{ route('clients.show',$project->client) }}">{{ $project->client->company ?? $project->client->name ?? '—' }}</a></span>
      @if($project->start_date || $project->due_date)<span class="chip">{{ d($project->start_date) }} → {{ d($project->due_date) }}</span>@endif
      <span class="chip">{{ $doneCount }}/{{ $taskCount }} tasks done</span>
      @if(!is_null($project->csat()))<span class="chip">★ {{ $project->csat() }}/5 client rating</span>@endif
    </div></div>
  <div class="btnrow">
    <a class="btn sec sm" href="{{ route('projects.edit',$project) }}">Edit</a>
    <a class="btn sm" href="{{ route('tasks.create',['project_id'=>$project->id]) }}">+ Task</a>
    <a class="btn sm sec" href="{{ route('expenses.create',['project_id'=>$project->id]) }}">+ Expense</a>
  </div>
</div>

<div class="card">
  @if($project->description)<p style="margin-top:0">{{ $project->description }}</p>@endif
  <div class="grid stats cols-4">
    <div class="stat"><div class="lbl">Status</div><b style="font-size:18px"><span class="badge {{ $stColor }}">{{ $project->status }}</span></b></div>
    <div class="stat"><div class="lbl">Budget</div><b>{{ money($project->budget) }}</b></div>
    <div class="stat"><div class="lbl">Costs</div><b>{{ money($pcost) }}</b><div class="sub">margin {{ money($project->budget - $pcost) }}</div></div>
    <div class="stat"><div class="lbl">Billed</div><b>{{ money($pbilled) }}</b><div class="sub">filed invoices</div></div>
    <div class="stat"><div class="lbl">Profit</div><b style="{{ $pprofit < 0 ? 'color:var(--bad)' : '' }}">{{ money($pprofit) }}</b><div class="sub">{{ $pmargin }}% margin</div></div>
    <div class="stat"><div class="lbl">Labor</div><b>{{ money($plab) }}</b><div class="sub">{{ $workedH }} logged / {{ $estH }} est.</div></div>
    <div class="stat"><div class="lbl">True margin</div><b style="{{ $trueProfit < 0 ? 'color:var(--bad)' : '' }}">{{ money($trueProfit) }}</b><div class="sub">after {{ money($trueCost) }} true cost</div></div>
    <div class="stat"><div class="lbl">Tasks</div><b>{{ $taskCount }}</b><div class="sub">{{ $doneCount }} done</div></div>
  </div>
  @if($overBudget)<div class="due-banner over" style="margin:14px 0 0">Over budget — costs {{ money($pcost) }} beat the {{ money($project->budget) }} budget by {{ money($pcost - $project->budget) }}.</div>@endif
  <div style="margin-top:14px"><div class="hint">Budget used — {{ money($pcost) }} of {{ money($project->budget) }} ({{ $puse }}%)</div><div class="statbar"><div style="width:{{ $puse }}%;{{ $puse >= 100 ? 'background:#ff453a' : '' }}"></div></div></div>
</div>

<div class="card"><div class="card-head"><h3>Tasks ({{ $taskCount }})</h3><a class="btn sm sec noprint" href="{{ route('tasks.create',['project_id'=>$project->id]) }}">+ New task</a></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Done</th><th>Title</th><th>Status</th><th>Priority</th><th>Due</th><th class="noprint"></th></tr>
@forelse($project->tasks as $t)<tr>
<td><form method="POST" action="{{ route('tasks.toggle',$t) }}" data-ajax-toggle>@csrf<button type="submit" class="rowcheck {{ $t->status === 'done' ? 'done' : '' }}" title="Done / reopen">{{ $t->status === 'done' ? '✓' : '' }}</button></form></td>
<td><a href="{{ route('tasks.edit',$t) }}">{{ $t->title }}</a></td>
<td><span class="badge {{ $t->status==='done' ? 'b-green' : ($t->status==='in_progress' ? 'b-blue' : ($t->status==='review' ? 'b-amber' : 'b-grey')) }}">{{ $t->status }}</span></td>
<td><span class="badge {{ ($t->priority ?? 'medium')==='urgent' ? 'b-red' : (($t->priority ?? 'medium')==='high' ? 'b-amber' : 'b-grey') }}">{{ $t->priority ?? 'medium' }}</span></td>@php $tOver = $t->due_date && substr((string) $t->due_date, 0, 10) < date('Y-m-d') && !in_array($t->status, ['done','completed']); @endphp<td class="nowrap">@if($tOver)<span class="badge b-red" title="Past due date">overdue</span> @endif{{ d($t->due_date) }}</td>
<td class="noprint"><div class="row-actions"><a class="icon-btn" href="{{ route('tasks.edit',$t) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Edit</a></div></td></tr>
@empty<tr><td colspan="6"><div class="empty">No tasks yet.</div></td></tr>@endforelse</table></div></div>

@php
$gTasks = $project->tasks->filter(fn($t) => $t->due_date)->sortBy('due_date')->values();
$gMin = $gTasks->map(fn($t) => $t->start_date ? $t->start_date->toDateString() : $t->due_date->toDateString())->min();
$gMax = $gTasks->map(fn($t) => $t->due_date->toDateString())->max();
$crit = $project->criticalPath();
@endphp
@if($gTasks->isNotEmpty())
<div class="card"><div class="card-head"><h3>Schedule</h3><span class="hint">{{ d($gMin) }} → {{ d($gMax) }}@if($crit) · critical: {{ count($crit) }} linked</span>@endif</div>
@foreach($gTasks as $t)@php $bar = $t->bar($gMin, $gMax); @endphp
<div style="display:flex;gap:10px;align-items:center;margin-bottom:6px"><div style="flex:0 0 220px;min-width:0"><b style="font-size:13px">{{ \Str::limit($t->title,32) }}</b>@if($t->isBlocked()) <span class="badge b-red">blocked</span>@endif @if(in_array($t->id, $crit))<span class="chip">critical</span>@endif @if($t->baseline_due && $t->due_date && $t->due_date->gt($t->baseline_due))<span class="hint">+{{ $t->due_date->diffInDays($t->baseline_due) }}d late</span>@endif</div><div style="flex:1;background:var(--surface-2);border-radius:6px;height:18px;position:relative"><div title="{{ d($t->start_date ?? $t->due_date) }} → {{ d($t->due_date) }}" style="position:absolute;left:{{ $bar['off'] }}%;width:{{ $bar['len'] }}%;top:0;bottom:0;border-radius:6px;background:{{ $t->status==='done' ? '#30d158' : ($t->isBlocked() ? '#ff453a' : '#0a84ff') }}"></div></div><div style="flex:0 0 90px;text-align:right"><span class="hint">{{ d($t->due_date) }}</span></div></div>
@endforeach
<div class="hint">Blue = scheduled · red = blocked by another task · green = done. Bars start at the start date (or back off the estimate).</div></div>
@endif

<div class="card"><div class="card-head"><h3>Unbilled time ({{ ($unbilled ?? collect())->count() }})</h3>@if(($unbilled ?? collect())->isNotEmpty())<form method="POST" action="{{ route('projects.billTime',$project) }}" data-confirm="Create a draft invoice from all unbilled time? Each task becomes one line.">@csrf<button class="btn sm">Bill time as invoice</button></form>@endif</div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Date</th><th>Task</th><th>Who</th><th style="text-align:right">Time</th><th style="text-align:right">Value</th></tr>
@forelse($unbilled ?? [] as $u)<tr><td class="nowrap">{{ d($u->worked_on) }}</td><td>{{ $u->task->title ?? '—' }}</td><td>{{ $u->user->name ?? '—' }}</td><td class="nowrap" style="text-align:right">{{ \Modules\Tasks\TaskTime::fmt($u->minutes) }}</td><td class="nowrap" style="text-align:right">{{ money(round($u->minutes/60*($u->task->rate() ?? 0),2)) }}</td></tr>@empty<tr><td colspan="5"><div class="empty">Everything logged is billed. Nice.</div></td></tr>@endforelse</table></div></div>

<div class="card"><div class="card-head"><h3>Project costs</h3><a class="btn sm sec noprint" href="{{ route('expenses.create',['project_id'=>$project->id]) }}">+ Add cost</a></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Number</th><th>Date</th><th>Title</th><th style="text-align:right">Amount</th><th class="noprint"></th></tr>
@forelse($project->expenses as $e)<tr><td class="nowrap"><a href="{{ route('expenses.show',$e) }}">{{ $e->number ?? '—' }}</a></td><td class="nowrap">{{ d($e->spent_on) }}</td><td><a href="{{ route('expenses.show',$e) }}">{{ $e->title }}</a></td><td class="nowrap" style="text-align:right">{{ money($e->amount) }}</td>
<td class="noprint"><div class="row-actions"><a class="icon-btn" href="{{ route('expenses.edit',$e) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Edit</a></div></td></tr>
@empty<tr><td colspan="5"><div class="empty">No costs booked to this project.</div></td></tr>@endforelse</table></div></div>

@php $costCats = $project->costByCategory(); $forecast = $project->forecastProfit(); $pInvoices = $project->invoices()->orderByDesc('id')->get(); @endphp
<div class="card"><div class="card-head"><h3>Finance</h3><span class="hint">forecast {{ money($forecast) }} if the rest goes to estimate</span></div>
<div class="cols-2">
<div><div class="fsec" style="margin-top:0">Costs by category</div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Category</th><th style="text-align:right">Amount</th></tr>
@forelse($costCats as $cat=>$amt)<tr><td>{{ ucfirst($cat) }}</td><td class="nowrap" style="text-align:right">{{ money($amt) }}</td></tr>@empty<tr><td colspan="2"><div class="empty">No approved costs yet.</div></td></tr>@endforelse</table></div>
<div class="hint" style="margin-top:8px">Labor so far {{ money($project->laborTotal()) }} · to complete ≈ {{ money($project->remainingCost()) }}</div></div>
<div><div class="fsec" style="margin-top:0">Billed ({{ $pInvoices->count() }})</div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Invoice</th><th>Status</th><th style="text-align:right">Total</th></tr>
@forelse($pInvoices as $inv)<tr><td><a href="{{ route('invoices.show',$inv) }}">{{ $inv->number }}</a></td><td>{{ ucfirst($inv->status) }}</td><td class="nowrap" style="text-align:right">{{ money($inv->total) }}</td></tr>@empty<tr><td colspan="3"><div class="empty">Nothing billed yet.</div></td></tr>@endforelse</table></div></div>
</div></div>

<div class="card"><div class="card-head"><h3>Documents ({{ $project->documents->count() }})</h3><a class="hint" href="{{ route('documents.index',['project_id'=>$project->id]) }}">All files →</a></div>
<form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="noprint" style="margin-bottom:14px">@csrf<input type="hidden" name="project_id" value="{{ $project->id }}">
<div class="up-inline"><input class="in" style="margin:0;flex:1;min-width:160px" type="file" name="file" required><button class="btn sm">Upload</button></div></form>
@forelse($project->documents as $dd)
@php $ext = file_ext($dd->name); @endphp
@if($loop->first)<div class="fm-grid">@endif
  <div class="file-tile">
    <a class="file-ic {{ file_icon_class($ext) }}" href="{{ route('documents.download',$dd) }}" title="Download {{ $dd->name }}">{{ file_icon_label($ext) }}</a>
    <div class="file-name" title="{{ $dd->name }}">{{ $dd->name }}</div>
    <div class="hint">{{ fmt_size($dd->size) }}</div>
    <div class="file-actions noprint">
      <a class="icon-btn" href="{{ route('documents.download',$dd) }}" title="Download {{ $dd->name }}" aria-label="Download {{ $dd->name }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v3h16v-3"/></svg></a>
      <form method="POST" action="{{ route('documents.destroy',$dd) }}" style="display:inline" data-confirm="Delete document?">@csrf @method('DELETE')<button class="icon-btn danger" title="Delete {{ $dd->name }}" aria-label="Delete {{ $dd->name }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
    </div>
  </div>
@if($loop->last)</div>@endif
@empty<div class="empty">No files yet — upload above.</div>@endforelse</div>
@endsection
