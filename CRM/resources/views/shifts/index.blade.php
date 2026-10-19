@extends('layouts.app')
@section('title','Shift roster')
@section('content')
<div class="card">
<div class="card-head"><h3>Roster — this week + next</h3>@if($canEdit)<span class="hint">managers set it below</span>@endif</div>
@foreach($weeks as $wi)<div class="fsec">{{ $wi[0]->format('j M') }} – {{ $wi[6]->format('j M Y') }}</div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Who</th>@foreach($wi as $d)<th class="{{ $d->isToday() ? 'hl' : '' }}">{{ $d->format('D j') }}</th>@endforeach</tr>
@foreach($users as $u)<tr><td><b>{{ $u->name }}</b></td>@foreach($wi as $d)@php $m = ($marks[$u->id.'|'.$d->toDateString()] ?? collect())->first(); @endphp<td>@if($m)<span class="badge {{ str_contains($m->label,'off') ? 'b-grey' : 'b-blue' }}">{{ $m->label }}</span>@if($m->note)<div class="hint">{{ \Str::limit($m->note,30) }}</div>@endif @if($canEdit)<form method="POST" action="{{ route('shifts.destroy',$m) }}" style="display:inline" data-confirm="Clear this shift?">@csrf @method('DELETE')<button class="link-btn danger">×</button></form>@endif @else<span class="hint">—</span>@endif</td>@endforeach</tr>@endforeach</table></div>
@endforeach
@if($canEdit)
<hr><h3>Set shift</h3>
<form method="POST" action="{{ route('shifts.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">@csrf
<div><label class="f">Who</label><select class="in" name="user_id" style="margin:0">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
<div><label class="f">Day</label><input class="in" type="date" name="day" value="{{ date('Y-m-d') }}" style="margin:0" required></div>
<div><label class="f">Shift</label><select class="in" name="label" style="margin:0">@foreach(\Modules\Work\Shift::labels() as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
<div style="flex:1;min-width:140px"><label class="f">Note</label><input class="in" name="note" maxlength="255" style="margin:0" placeholder="Optional…"></div>
<div><button class="btn sm">Save</button></div></form>
@endif
</div>
@endsection
