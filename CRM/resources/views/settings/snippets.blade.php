@extends('layouts.app')
@section('title','Content library')
@section('content')
<div class="card" style="max-width:720px"><div class="card-head"><h3>Proposal boilerplates</h3><span class="hint">insertable into scope, results, terms, notes</span></div>
<p class="hint" style="margin-top:0">Reusable paragraphs staff can drop into proposals — write once, reuse on every quote.</p>
<form method="POST" action="{{ route('settings.snippets.store') }}">@csrf
<div class="form-grid">
<div><label class="f">Title</label><input class="in" name="title" required maxlength="120" placeholder="e.g. Standard SEO scope" style="margin-bottom:0"></div>
<div><label class="f">Goes into</label><select class="in" name="kind" style="margin-bottom:0">@foreach(\Modules\Proposals\Snippet::kinds() as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
</div>
<label class="f">Text</label><textarea class="in" name="body" rows="3" required placeholder="One point per line…"></textarea>
<div class="form-actions"><button class="btn sm" type="submit">Add snippet</button></div></form></div>
@foreach(\Modules\Proposals\Snippet::kinds() as $k=>$l)
@php $list = $snippets->where('kind',$k); @endphp
@if($list->isNotEmpty())
<div class="card" style="max-width:720px"><div class="card-head"><h3>{{ $l }}</h3></div>
@foreach($list as $s)
<form method="POST" action="{{ route('settings.snippets.update',$s) }}" style="margin-bottom:6px">@csrf @method('PUT')
<input class="in" name="title" value="{{ $s->title }}" required maxlength="120">
<textarea class="in" name="body" rows="2" required>{{ $s->body }}</textarea>
<input type="hidden" name="kind" value="{{ $s->kind }}">
<div class="btnrow"><button class="btn sec sm" type="submit">Save</button></div></form>
<form method="POST" action="{{ route('settings.snippets.destroy',$s) }}" style="margin-bottom:12px" data-confirm="Delete this snippet?">@csrf @method('DELETE')<button class="btn sec sm danger" type="submit">Delete</button></form>
@endforeach</div>
@endif
@endforeach
@endsection
