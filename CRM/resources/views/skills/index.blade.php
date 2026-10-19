@extends('layouts.app')
@section('title','Skills matrix')
@section('content')
<div class="card">
<div class="card-head"><h3>Who knows what</h3><span class="hint">1 learning → 5 teaches others</span></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Skill</th>@foreach(\App\Models\User::where('is_active',true)->orderBy('name')->get(['id','name']) as $u)<th>{{ \Str::limit($u->name,14) }}</th>@endforeach</tr>
@foreach($skills as $sk)<tr><td><b>{{ $sk->name }}</b> <form method="POST" action="{{ route('skills.destroy',$sk) }}" style="display:inline" data-confirm="Remove {{ $sk->name }}?">@csrf @method('DELETE')<button class="link-btn danger">×</button></form></td>
@foreach(\App\Models\User::where('is_active',true)->orderBy('name')->get(['id','name']) as $u)
@php $cell = ($matrix[$sk->id] ?? collect())->firstWhere('user_id', $u->id); @endphp
<td>@if($cell)<span class="badge {{ $cell->level >= 4 ? 'b-green' : ($cell->level === 3 ? 'b-blue' : 'b-grey') }}" title="{{ \Modules\Work\UserSkill::levels()[$cell->level] ?? '' }}">{{ $cell->level }}/5</span>@if($cell->certified)<span class="hint"> ⚑@if($cell->cert_expires_on) {{ d($cell->cert_expires_on,'M Y') }}@endif</span>@endif @if($canAll)<form method="POST" action="{{ route('skills.unrate',$cell) }}" style="display:inline" data-confirm="Remove this rating?">@csrf @method('DELETE')<button class="link-btn danger">×</button></form>@endif @else<span class="hint">—</span>@endif</td>
@endforeach</tr>@endforeach</table></div>
@if($canAll)
<hr><div class="cols-2">
<div><h3>Add skill</h3>
<form method="POST" action="{{ route('skills.store') }}" class="noprint" style="display:flex;gap:8px;align-items:end">@csrf<input class="in" name="name" required maxlength="120" style="margin:0;flex:1" placeholder="e.g. 3-phase wiring"><button class="btn sm">Add</button></form></div>
<div><h3>Rate someone</h3>
<form method="POST" action="{{ route('skills.rate') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">@csrf
<select class="in" name="user_id" style="margin:0">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
<select class="in" name="skill_id" style="margin:0">@foreach($skills as $sk)<option value="{{ $sk->id }}">{{ $sk->name }}</option>@endforeach</select>
<select class="in" name="level" style="margin:0">@for($i=1;$i<=5;$i++)<option value="{{ $i }}">{{ $i }} — {{ \Modules\Work\UserSkill::levels()[$i] }}</option>@endfor</select>
<label class="hint" style="display:flex;gap:4px;align-items:center"><input type="checkbox" name="certified" value="1" style="width:auto;margin:0"> Certified</label>
<input class="in" type="date" name="cert_expires_on" style="margin:0" title="Cert expiry">
<button class="btn sm">Save</button></form></div>
</div>
<div><h3>Log training</h3>
<form method="POST" action="{{ route('skills.train') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">@csrf
<select class="in" name="user_id" style="margin:0">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
<input class="in" name="title" required maxlength="200" style="margin:0;flex:1;min-width:160px" placeholder="Course / workshop…">
<input class="in" type="date" name="completed_on" value="{{ date('Y-m-d') }}" style="margin:0" required>
<input class="in" type="date" name="valid_till" style="margin:0" title="Valid till">
<button class="btn sm">Log</button></form></div>
@endif
</div>
@if($trainings->isNotEmpty())
<div class="card" style="margin-top:22px"><div class="card-head"><h3>Recent training</h3></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Who</th><th>What</th><th>Done</th><th>Valid till</th></tr>
@foreach($trainings as $t)<tr><td><b>{{ $t->user->name ?? '—' }}</b></td><td>{{ $t->title }}</td><td class="nowrap">{{ d($t->completed_on) }}</td><td class="nowrap">{{ $t->valid_till ? d($t->valid_till) : '—' }}</td></tr>@endforeach</table></div></div>
@endif
@endsection
