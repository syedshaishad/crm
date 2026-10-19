@extends('layouts.app')
@section('title','Staff voice')
@section('content')
<div class="cols-2">
<div class="card">
<div class="card-head"><h3>Suggestion box</h3><span class="hint">100% anonymous</span></div>
<p class="fhint">No names are stored — not even for managers. Say the quiet part out loud.</p>
<form method="POST" action="{{ route('voice.suggest') }}">@csrf
<div class="pills" style="margin-bottom:10px">
@foreach(\Modules\Work\Suggestion::moods() as $v=>$l)<label><input type="radio" name="mood" value="{{ $v }}"><span>{{ $l }}</span></label>@endforeach
</div>
<textarea class="in" name="body" rows="3" required minlength="3" maxlength="2000" placeholder="What should change around here?"></textarea>
<div class="form-actions"><button class="btn sm">Drop it in the box</button></div></form>
<hr>
@forelse($suggestions as $s)<div style="margin-bottom:12px"><span class="badge b-grey">{{ \Modules\Work\Suggestion::moods()[$s->mood] ?? 'Note' }}</span> <span class="badge {{ $s->status==='done' ? 'b-green' : ($s->status==='reviewed' ? 'b-blue' : 'b-amber') }}">{{ $s->status }}</span><div style="white-space:pre-line;margin-top:4px">{{ $s->body }}</div>
@if($canAll && $s->status !== 'done')<form method="POST" action="{{ route('voice.review',$s) }}" style="display:inline">@csrf<button class="link-btn" name="status" value="{{ $s->status === 'new' ? 'reviewed' : 'done' }}">{{ $s->status === 'new' ? 'Mark reviewed' : 'Mark done' }}</button></form>@endif</div>@empty<p class="hint">The box is empty.</p>@endforelse
{{ $suggestions->links('pagination::simple-default', ['pageName' => 'spage']) }}</div>
<div class="card">
<div class="card-head"><h3>How likely to recommend us? (eNPS)</h3>@if($poll && !is_null($poll->enps()))<span class="badge {{ $poll->enps() >= 30 ? 'b-green' : ($poll->enps() >= 0 ? 'b-amber' : 'b-red') }}">eNPS {{ $poll->enps() }}</span>@endif</div>
@if($poll)
<p style="margin-top:0"><b>{{ $poll->question }}</b> <span class="hint">· {{ d($poll->month,'F Y') }}</span></p>
@php $b = $poll->buckets(); $tot = max(1, array_sum($b)); @endphp
<div class="statbar"><div style="width:{{ round($b['promoters']/$tot*100) }}%;background:#30d158"></div></div>
<div class="hint" style="margin:6px 0 12px">😊 {{ $b['promoters'] }} promoters · 😐 {{ $b['passive'] }} passive · ☹️ {{ $b['detractors'] }} detractors ({{ array_sum($b) }} votes)</div>
@if(!$mine)
<form method="POST" action="{{ route('voice.vote',$poll) }}">@csrf
<div class="pills" style="margin-bottom:10px">
@for($i=0;$i<=10;$i++)<label><input type="radio" name="score" value="{{ $i }}" {{ $i===10?'checked':'' }}><span>{{ $i }}</span></label>@endfor
</div>
<input class="in" name="comment" maxlength="500" placeholder="Why? (optional, anonymous)">
<div class="form-actions"><button class="btn sm">Vote anonymously</button></div></form>
@else
<p class="hint">You voted this month — results above are live.</p>
@endif
@else
<p class="hint">No active poll.</p>
@endif
@if($canAll)
<hr><h3>Open this month's poll</h3>
<form method="POST" action="{{ route('voice.poll') }}" class="noprint" style="display:flex;gap:8px;align-items:end">@csrf
<div style="flex:1"><label class="f">Question</label><input class="in" name="question" required maxlength="255" style="margin:0" value="How likely are you to recommend working here?" placeholder="How likely are you to recommend working here?"></div>
<div><label class="f">Month</label><input class="in" type="month" name="month" value="{{ date('Y-m') }}" style="margin:0" required></div>
<div><button class="btn sm">Open poll</button></div></form>
@if($polls->isNotEmpty())
<div class="tbl-scroll" style="margin-top:12px"><table class="tbl"><tr><th>Month</th><th style="text-align:right">Votes</th><th style="text-align:right">eNPS</th></tr>
@foreach($polls as $p)<tr><td>{{ d($p->month,'M Y') }}</td><td class="nowrap" style="text-align:right">{{ $p->votes_count }}</td><td class="nowrap" style="text-align:right"><b>{{ is_null($p->enps()) ? '—' : $p->enps() }}</b></td></tr>@endforeach</table></div>
@endif
@endif
</div>
</div>
@endsection
