@extends('layouts.app')
@section('title','Knowledge base')
@section('content')
<div class="card">
<div class="card-head"><h3>How we do things</h3><a class="btn sm sec noprint" href="{{ route('knowledge.index') }}">All</a></div>
<form method="GET" data-ajax-filter data-region="kb-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search SOPs, guides…"><select class="in" style="margin:0" name="category"><option value="">All kinds</option>@foreach(\Modules\Projects\Article::categories() as $c)<option {{ ($category ?? '')===$c?'selected':'' }}>{{ $c }}</option>@endforeach</select><button class="btn sec sm">Search</button></form>
<div data-ajax-region="kb-list">
@forelse($rows as $a)<div class="mi-row" style="border-color:var(--line)">@if($a->pinned)<span title="Pinned">📌</span>@endif<span style="flex:1"><a href="{{ route('knowledge.show',$a) }}"><b>{{ $a->title }}</b></a> <span class="badge b-grey">{{ $a->category }}</span><span class="hint"> · {{ $a->author->name ?? '' }}</span></span></div>@empty<div class="empty">Nothing written yet — fix that below.</div>@endforelse
{{ $rows->links() }}</div>
<hr><h3>Write it down</h3>
<form method="POST" action="{{ route('knowledge.store') }}" class="noprint">@csrf
<div class="form-grid"><div><label class="f">Title <span class="req">*</span></label><input class="in" name="title" required maxlength="255"></div>
<div><label class="f">Kind</label><select class="in" name="category">@foreach(\Modules\Projects\Article::categories() as $c)<option>{{ $c }}</option>@endforeach</select></div></div>
<label class="f">Body <span class="req">*</span></label><textarea class="in" name="body" rows="6" required placeholder="Steps, rules, links… (blank lines = paragraphs)"></textarea>
<div class="form-actions"><button class="btn" type="submit">Publish</button></div></form></div>
@endsection
