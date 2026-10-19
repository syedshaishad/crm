@extends('layouts.app')
@section('title','Automations')
@section('content')
<div class="card">
<div class="card-head"><h3>Rules that work while you sleep</h3><form method="POST" action="{{ route('automations.run') }}" data-confirm="Evaluate all rules right now?">@csrf<button class="btn sec sm">Run now</button></form></div>
<p class="fhint">Triggers check every morning. Each rule fires at most once per item per day — nudges, never spam.</p>
@if(session('automation_lines'))<div class="alert" style="margin-bottom:12px">{{ implode('<br>', array_map('e', (array) session('automation_lines'))) }}</div>@endif
<form method="POST" action="{{ route('automations.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
<div><label class="f">Name</label><input class="in" name="name" required maxlength="150" style="margin:0" placeholder="e.g. Chase stale leads"></div>
<div><label class="f">When</label><select class="in" name="trigger" style="margin:0">@foreach(\Modules\Projects\WorkflowRule::triggers() as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
<div><label class="f">After (days)</label><input class="in" type="number" min="0" max="365" name="after_days" value="3" style="margin:0;width:110px"></div>
<div><label class="f">Do</label><select class="in" name="action" style="margin:0">@foreach(\Modules\Projects\WorkflowRule::actions() as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
<div><label class="f">Reassign to</label><select class="in" name="reassign_to" style="margin:0"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
<div><button class="btn sm">Save rule</button></div></form>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Rule</th><th>Trigger</th><th>Action</th><th>Status</th><th></th></tr>
@forelse($rules as $r)<tr>
<td><b>{{ $r->name }}</b></td>
<td>{{ \Modules\Projects\WorkflowRule::triggers()[$r->trigger] ?? $r->trigger }}@if($r->after_days > 0)<span class="hint"> · {{ $r->after_days }}d</span>@endif</td>
<td>{{ \Modules\Projects\WorkflowRule::actions()[$r->action] ?? $r->action }}@if($r->action==='reassign' && $r->target)<span class="hint"> → {{ $r->target->name }}</span>@endif</td>
<td><span class="badge {{ $r->is_active ? 'b-green' : 'b-grey' }}">{{ $r->is_active ? 'live' : 'paused' }}</span></td>
<td><div class="row-actions">
<form method="POST" action="{{ route('automations.toggle',$r) }}" style="display:inline">@csrf<button class="icon-btn">{{ $r->is_active ? 'Pause' : 'Resume' }}</button></form>
<form method="POST" action="{{ route('automations.destroy',$r) }}" style="display:inline" data-confirm="Remove {{ $r->name }}?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form></div></td></tr>
@empty<tr><td colspan="5"><div class="empty">No rules — overdue work just sits there. Add one above.</div></td></tr>@endforelse</table></div></div>
@if($firings->isNotEmpty())
<div class="card" style="margin-top:22px"><div class="card-head"><h3>Recent firings</h3></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>When</th><th>Rule</th><th>Subject</th></tr>
@foreach($firings as $f)<tr><td class="nowrap">{{ d($f->created_at,'j M, h:i A') }}</td><td>{{ $f->rule }}</td><td class="nowrap">{{ $f->subject_type }} #{{ $f->subject_id }}</td></tr>@endforeach</table></div></div>
@endif
@endsection
