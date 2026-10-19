@extends('layouts.app')
@section('title','Meetings')
@section('content')
<div class="card">
<div class="card-head"><h3>Meetings</h3><span class="hint">agenda in, minutes out</span></div>
<form method="POST" action="{{ route('meetings.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
<div style="flex:2;min-width:180px"><label class="f">Title <span class="req">*</span></label><input class="in" name="title" required maxlength="255" style="margin:0" placeholder="e.g. Site review with client"></div>
<div><label class="f">When <span class="req">*</span></label><input class="in" type="datetime-local" name="starts_at" style="margin:0" required></div>
<div><label class="f">Mins</label><input class="in" type="number" min="5" max="480" name="duration_minutes" value="30" style="margin:0;width:100px"></div>
<div><label class="f">Project</label><select class="in" name="project_id" style="margin:0"><option value="">—</option>@foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
<div style="flex:1;min-width:160px"><label class="f">Attendees</label><select class="in" name="attendees[]" multiple style="margin:0;min-height:42px">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
<div style="flex:1;min-width:180px"><label class="f">Agenda</label><input class="in" name="agenda" style="margin:0" placeholder="3 bullets max…"></div>
<div><button class="btn sm">Schedule</button></div></form>
<div class="fsec" style="margin-top:0">Upcoming</div>
@forelse($upcoming as $m)<div style="margin-bottom:14px"><b>{{ $m->title }}</b> <span class="hint">{{ d($m->starts_at,'D, j M · h:i A') }} · {{ $m->duration_minutes }}m</span>@if($m->project)<span class="hint"> · {{ $m->project->name }}</span>@endif
@if($m->agenda)<div class="hint">{{ \Str::limit($m->agenda,160) }}</div>@endif
<form method="POST" action="{{ route('meetings.notes',$m) }}" class="noprint" style="display:flex;gap:6px;margin-top:6px">@csrf @method('PUT')<input class="in" name="notes" style="margin:0;flex:1" value="{{ $m->notes }}" placeholder="Minutes / decisions…"><button class="btn sec sm">Save</button></form>
<form method="POST" action="{{ route('meetings.destroy',$m) }}" class="noprint" style="margin-top:4px" data-confirm="Remove this meeting?">@csrf @method('DELETE')<button class="link-btn danger">Remove meeting</button></form></div>
@empty<p class="hint">Nothing scheduled — enjoy the quiet.</p>@endforelse
@if($past->isNotEmpty())
<hr><div class="fsec">Past</div>
@forelse($past as $m)<div style="margin-bottom:10px"><b>{{ $m->title }}</b> <span class="hint">{{ d($m->starts_at,'j M Y') }}</span>@if($m->notes)<div style="white-space:pre-line">{{ \Str::limit($m->notes,200) }}</div>@endif </div>@empty <p class="hint">No past meetings.</p> @endforelse
{{ $past->links() }}
@endif
</div>
@endsection
