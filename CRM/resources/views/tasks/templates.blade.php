@extends('layouts.app')
@section('title','Task templates')
@section('content')
<div class="cols-2">
<div class="card" style="margin-top:0"><div class="card-head"><h3>Task starters</h3></div>
<form method="POST" action="{{ route('tasks.templates.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
<div><label class="f">Name</label><input class="in" name="name" required maxlength="150" style="margin:0" placeholder="e.g. New site visit"></div>
<div style="flex:1;min-width:160px"><label class="f">Task title</label><input class="in" name="title" required maxlength="255" style="margin:0"></div>
<div><label class="f">Est. min</label><input class="in" type="number" min="0" name="estimate_minutes" value="0" style="margin:0;width:110px"></div>
<div style="flex:1;min-width:200px"><label class="f">Checklist (one per line)</label><textarea class="in" name="checklist" rows="2" style="margin:0" placeholder="Call ahead…&#10;Carry meter…"></textarea></div>
<div><button class="btn sm">Save</button></div></form>
@forelse($taskTemplates as $t)<div class="mi-row" style="border-color:var(--line)"><span style="flex:1"><b>{{ $t->name }}</b> <span class="hint">{{ \Str::limit($t->title,50) }} · {{ count($t->lines()) }} steps</span></span><form method="POST" action="{{ route('tasks.fromTemplate') }}" style="display:inline">@csrf<input type="hidden" name="template_id" value="{{ $t->id }}"><button class="icon-btn">Use</button></form><form method="POST" action="{{ route('tasks.templates.destroy',$t) }}" style="display:inline" data-confirm="Remove {{ $t->name }}?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form></div>@empty<p class="hint">No task templates yet.</p>@endforelse</div>
<div class="card" style="margin-top:0"><div class="card-head"><h3>Checklist packs</h3></div>
<form method="POST" action="{{ route('tasks.checklistTemplates.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
<div><label class="f">Name</label><input class="in" name="name" required maxlength="150" style="margin:0" placeholder="e.g. Safety checks"></div>
<div style="flex:1;min-width:200px"><label class="f">Steps (one per line)</label><textarea class="in" name="lines" rows="2" required style="margin:0"></textarea></div>
<div><button class="btn sm">Save</button></div></form>
@forelse($checklistTemplates as $t)<div class="mi-row" style="border-color:var(--line)"><span style="flex:1"><b>{{ $t->name }}</b> <span class="hint">{{ count($t->lines()) }} steps</span></span><form method="POST" action="{{ route('tasks.checklistTemplates.destroy',$t) }}" style="display:inline" data-confirm="Remove {{ $t->name }}?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form></div>@empty<p class="hint">No checklist packs yet.</p>@endforelse</div>
</div>
@endsection
