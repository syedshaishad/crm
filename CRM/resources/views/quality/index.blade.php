@extends('layouts.app')
@section('title','Quality loop')
@section('content')
<div class="cols-2">
<div class="card" style="margin-top:0"><div class="card-head"><h3>Incidents</h3><span class="hint">what broke + why</span></div>
<form method="POST" action="{{ route('quality.incidents.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
<div style="flex:2;min-width:180px"><label class="f">What went wrong? <span class="req">*</span></label><input class="in" name="title" required maxlength="255" style="margin:0" placeholder="e.g. Wrong switch rating at site"></div>
<div><label class="f">Severity</label><select class="in" name="severity" style="margin:0">@foreach(\Modules\Projects\Incident::severities() as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
<div><label class="f">Project</label><select class="in" name="project_id" style="margin:0"><option value="">—</option>@foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
<div><label class="f">When</label><input class="in" type="date" name="occurred_on" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" style="margin:0"></div>
<div style="flex:1;min-width:160px"><label class="f">Details</label><input class="in" name="what_happened" style="margin:0" placeholder="Optional…"></div>
<div><button class="btn sm">Log</button></div></form>
<form method="GET" data-ajax-filter data-region="q-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px"><select class="in" style="margin:0" name="status"><option value="">All statuses</option>@foreach(['open'=>'Open','fixing'=>'Fixing','fixed'=>'Fixed'] as $v=>$l)<option value="{{ $v }}" {{ ($status ?? '')===$v?'selected':'' }}>{{ $l }}</option>@endforeach</select><button class="btn sec sm">Filter</button></form>
<div data-ajax-region="q-list">
@forelse($incidents as $i)<div style="margin-bottom:14px"><span class="badge {{ $i->severity==='critical' ? 'b-red' : ($i->severity==='high' ? 'b-amber' : 'b-grey') }}">{{ ucfirst($i->severity) }}</span> <span class="badge {{ $i->status==='fixed' ? 'b-green' : ($i->status==='fixing' ? 'b-blue' : 'b-amber') }}">{{ ucfirst($i->status) }}</span> <b>{{ $i->title }}</b> <span class="hint">@if($i->project){{ $i->project->name }} · @endif{{ $i->occurred_on ? d($i->occurred_on) : '' }}</span>
@if($i->what_happened)<div class="hint">{{ \Str::limit($i->what_happened,140) }}</div>@endif
<form method="POST" action="{{ route('quality.incidents.update',$i) }}" class="noprint" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;align-items:end">@csrf @method('PUT')<input class="in" name="root_cause" maxlength="1000" style="margin:0;flex:1;min-width:140px" value="{{ $i->root_cause }}" placeholder="Root cause…"><input class="in" name="fix" maxlength="1000" style="margin:0;flex:1;min-width:140px" value="{{ $i->fix }}" placeholder="Fix…"><select class="in" name="status" style="margin:0;width:auto">@foreach(['open'=>'Open','fixing'=>'Fixing','fixed'=>'Fixed'] as $v=>$l)<option value="{{ $v }}" {{ $i->status===$v?'selected':'' }}>{{ $l }}</option>@endforeach</select><button class="btn sec sm">Save</button></form></div>
@empty<p class="hint">No incidents — long may it last.</p>@endforelse
{{ $incidents->links('pagination::simple-default', ['pageName' => 'ipage']) }}</div></div>
<div class="card" style="margin-top:0"><div class="card-head"><h3>Kaizen ideas</h3><span class="hint">small bets, every retro</span></div>
<form method="POST" action="{{ route('quality.improvements.store') }}" class="noprint" style="display:flex;gap:8px;align-items:end;margin-bottom:12px">@csrf
<div style="flex:1"><label class="f">Idea <span class="req">*</span></label><input class="in" name="title" required maxlength="255" style="margin:0" placeholder="e.g. Photo checklist before leaving site"></div>
<div><button class="btn sm">Capture</button></div></form>
@foreach(['proposed'=>'Proposed','doing'=>'Doing','done'=>'Done'] as $st=>$sl)<div class="fsec" style="margin-top:10px">{{ $sl }}</div>
@forelse($improvements->where('status',$st) as $m)<div class="mi-row" style="border-color:var(--line)"><span style="flex:1"><b>{{ $m->title }}</b>@if($m->detail)<span class="hint">{{ \Str::limit($m->detail,80) }}</span>@endif</span>
@if($st !== 'done')<form method="POST" action="{{ route('quality.improvements.move',$m) }}" style="display:inline">@csrf<button class="link-btn" name="status" value="{{ $st === 'proposed' ? 'doing' : 'done' }}">{{ $st === 'proposed' ? 'Start →' : 'Done ✓' }}</button></form>@endif
<form method="POST" action="{{ route('quality.improvements.destroy',$m) }}" style="display:inline" data-confirm="Drop this idea?">@csrf @method('DELETE')<button class="link-btn danger">×</button></form></div>@empty<p class="hint">Nothing {{ strtolower($sl) }}.</p>@endforelse
@endforeach</div>
</div>
@endsection
