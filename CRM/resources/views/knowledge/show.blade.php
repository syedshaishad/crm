@extends('layouts.app')
@section('title',$article->title)
@section('content')
@php
  $author = $article->author->name ?? 'Team';
  $kindSlug = \Illuminate\Support\Str::slug($article->category);
@endphp
<div class="kb-show-wrap">
  <div class="kb-show-top">
    <a class="kb-back" href="{{ route('knowledge.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
      Knowledge base
    </a>
  </div>

  <article class="card kb-article">
    <div class="kb-article-top">
      <span class="kb-kind kb-kind-{{ $kindSlug }}">{{ $article->category }}</span>
      @if($article->pinned)
        <span class="kb-pinned-tag">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3h6l-1 6 3 3v2H7v-2l3-3-1-6z"/><path d="M12 14v7"/></svg>
          Pinned
        </span>
      @endif
    </div>

    <h1 class="kb-article-title">{{ $article->title }}</h1>

    <div class="kb-meta">
      <span class="kb-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($author, 0, 1)) }}</span>
      <span>By <b>{{ $author }}</b></span>
      <span class="kb-dot" aria-hidden="true"></span>
      <span>Updated {{ d($article->updated_at, 'j M Y') }}</span>
    </div>

    <div class="kb-prose">{!! nl2br(e($article->body)) !!}</div>

    <div class="kb-article-actions noprint">
      <form method="POST" action="{{ route('knowledge.pin', $article) }}">@csrf
        <button class="btn sec sm" type="submit">{{ $article->pinned ? 'Unpin' : 'Pin to top' }}</button>
      </form>
      <form method="POST" action="{{ route('knowledge.destroy', $article) }}" data-confirm="Delete this article? This cannot be undone.">@csrf @method('DELETE')
        <button class="btn sec sm danger" type="submit">Delete</button>
      </form>
    </div>
  </article>

  <details class="adv kb-edit noprint" id="kb-edit">
    <summary>Edit this article</summary>
    <form method="POST" action="{{ route('knowledge.update', $article) }}">
      @csrf @method('PUT')
      <label class="f" for="kb-edit-title">Title</label>
      <input class="in" id="kb-edit-title" name="title" required maxlength="255" value="{{ old('title', $article->title) }}">
      <div class="form-grid">
        <div>
          <label class="f" for="kb-edit-category">Kind</label>
          <select class="in" id="kb-edit-category" name="category">
            @foreach(\Modules\Projects\Article::categories() as $kind)
              <option value="{{ $kind }}" {{ $article->category === $kind ? 'selected' : '' }}>{{ $kind }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <label class="f" for="kb-edit-body">Body</label>
      <textarea class="in" id="kb-edit-body" name="body" rows="10" required>{{ old('body', $article->body) }}</textarea>
      <div class="form-actions">
        <button class="btn" type="submit">Save changes</button>
        <a class="btn sec" href="{{ route('knowledge.show', $article) }}">Cancel</a>
      </div>
    </form>
  </details>
</div>
@endsection
