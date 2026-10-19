@extends('portal.layout')
@section('title','My work')
@section('content')
<div class="page-head"><h1>Work in progress</h1><div class="crumb">Your projects, live task progress, and a rating box.</div></div>
@forelse($projects as $p)
@php $done = $p->tasks->where('status','done')->count(); $tot = max(1, $p->tasks->count()); @endphp
<div class="card">
<div class="card-head"><h3>{{ $p->name }}</h3><span class="badge {{ $p->status==='completed' ? 'b-green' : 'b-blue' }}">{{ $p->status }}</span></div>
<div class="statbar"><div style="width:{{ round($done/$tot*100) }}%"></div></div>
<div class="hint" style="margin:6px 0 10px">{{ $done }} of {{ $p->tasks->count() }} tasks done</div>
@foreach($p->tasks->take(8) as $t)<div class="mi-row" style="border-color:var(--line)"><span class="rowcheck {{ $t->status==='done' ? 'done' : '' }}">{{ $t->status==='done' ? '✓' : '' }}</span><span style="flex:1">{{ $t->title }}</span>@if($t->handed_over_at)<span class="badge b-green">signed</span>@elseif($t->public_token)<a class="btn sm sec" href="{{ route('tasks.public',$t->public_token) }}">Review</a>@endif</div>@endforeach
@if(($feedback[$p->id] ?? collect())->isNotEmpty())
<hr><div class="fsec" style="margin-top:0">Your ratings</div>
@foreach($feedback[$p->id] as $f)<div class="hint" style="margin-bottom:4px">★ {{ $f->score }}/5 @if($f->comment)— {{ \Str::limit($f->comment,100) }}@endif</div>@endforeach
@else
<hr><h3>Rate this project</h3>
<form method="POST" action="{{ route('portal.work.feedback',$p) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">@csrf
<div><label class="f">Score</label><select class="in" name="score" style="margin:0">@for($i=5;$i>=1;$i--)<option value="{{ $i }}">{{ $i }} — {{ [1=>'Poor',2=>'Fair',3=>'Good',4=>'Great',5=>'Excellent'][$i] ?? '' }}</option>@endfor</select></div>
<div style="flex:1;min-width:160px"><label class="f">Comment</label><input class="in" name="comment" maxlength="1000" style="margin:0" placeholder="What went well? What didn't?"></div>
<div><button class="btn sm">Send rating</button></div></form>
@endif
</div>
@empty
<div class="card"><div class="empty">No projects yet — your work will show up here.</div></div>
@endforelse
@endsection
