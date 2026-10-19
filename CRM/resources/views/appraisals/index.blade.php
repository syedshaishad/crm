@extends('layouts.app')
@section('title','Appraisals')
@section('content')
<div class="card">
<div class="card-head"><h3>Performance reviews</h3>@if(!$canAll)<span class="hint">your reviews</span>@endif</div>
@if($canAll)
<form method="POST" action="{{ route('appraisals.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
<div><label class="f">Who</label><select class="in" name="user_id" style="margin:0">@foreach($users as $u)<option value="{{ $u->id }}" {{ (string)($user_id ?? '')===(string)$u->id?'selected':'' }}>{{ $u->name }}</option>@endforeach</select></div>
<div><label class="f">Period</label><select class="in" name="period" style="margin:0">@foreach(\Modules\Work\Appraisal::periods() as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
<div><label class="f">Rating</label><select class="in" name="rating" style="margin:0">@for($i=1;$i<=5;$i++)<option value="{{ $i }}">{{ $i }} — {{ ['','Needs work','Fair','Good','Great','Outstanding'][$i] }}</option>@endfor</select></div>
<div style="flex:1;min-width:140px"><label class="f">Strengths</label><input class="in" name="strengths" style="margin:0" placeholder="What they do well…"></div>
<div style="flex:1;min-width:140px"><label class="f">Improve</label><input class="in" name="improvements" style="margin:0" placeholder="What to work on…"></div>
<div style="flex:1;min-width:140px"><label class="f">Goals</label><input class="in" name="goals" style="margin:0" placeholder="Next period goals…"></div>
<div><button class="btn sm">Save review</button></div></form>
@endif
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Who</th><th>Period</th><th>Rating</th><th>Review</th><th>By</th><th></th></tr>
@forelse($rows as $a)<tr>
<td><b>{{ $a->user->name ?? '—' }}</b></td>
<td class="nowrap">{{ \Modules\Work\Appraisal::periods()[$a->period] ?? $a->period }}</td>
<td><span class="badge {{ $a->rating >= 4 ? 'b-green' : ($a->rating === 3 ? 'b-blue' : 'b-amber') }}">{{ $a->rating }}/5</span></td>
<td>@if($a->strengths)<div><b>+</b> {{ \Str::limit($a->strengths,80) }}</div>@endif @if($a->improvements)<div><b>△</b> {{ \Str::limit($a->improvements,80) }}</div>@endif @if($a->goals)<div><b>◎</b> {{ \Str::limit($a->goals,80) }}</div>@endif</td>
<td class="nowrap">{{ $a->reviewer->name ?? '—' }}</td>
<td>@if($canAll)<form method="POST" action="{{ route('appraisals.destroy',$a) }}" style="display:inline" data-confirm="Remove this appraisal?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form>@endif</td></tr>
@empty<tr><td colspan="6"><div class="empty">No appraisals yet.</div></td></tr>@endforelse</table></div>
{{ $rows->links() }}</div>
@endsection
