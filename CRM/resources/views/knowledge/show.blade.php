@extends('layouts.app')
@section('title',$article->title)
@section('content')
<div class="card" style="max-width:780px">
<div class="card-head"><h3>{{ $article->title }}</h3><span class="badge b-grey">{{ $article->category }}</span></div>
<p class="hint" style="margin-top:0">By {{ $article->author->name ?? 'someone' }} · {{ d($article->updated_at,'j M Y') }}</p>
<div style="white-space:pre-line">{{ $article->body }}</div>
<hr>
<details class="adv noprint"><summary>Edit this article</summary>
<form method="POST" action="{{ route('knowledge.update',$article) }}" style="margin-top:10px">@csrf @method('PUT')
<label class="f">Title</label><input class="in" name="title" required maxlength="255" value="{{ $article->title }}">
<div class="form-grid"><div><label class="f">Kind</label><select class="in" name="category">@foreach(\Modules\Projects\Article::categories() as $c)<option {{ $article->category===$c?'selected':'' }}>{{ $c }}</option>@endforeach</select></div></div>
<label class="f">Body</label><textarea class="in" name="body" rows="8" required>{{ $article->body }}</textarea>
<div class="form-actions"><button class="btn sm" type="submit">Save</button>
</form>
<form method="POST" action="{{ route('knowledge.pin',$article) }}" style="display:inline">@csrf<button class="btn sec sm">{{ $article->pinned ? 'Unpin' : 'Pin to top' }}</button></form>
<form method="POST" action="{{ route('knowledge.destroy',$article) }}" style="display:inline" data-confirm="Delete this article?">@csrf @method('DELETE')<button class="btn sec sm danger">Delete</button></form></div>
</details>
</div>
@endsection
