@extends('layouts.app')
@section('title','Knowledge base')
@section('content')
<div class="page-head kb-head">
  <div class="kb-head-main">
    <span class="kb-head-ic" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v17H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 4.5v17"/><path d="M8 7h8M8 11h8M8 15h5"/></svg>
    </span>
    <div>
      <h1>Knowledge base</h1>
      <div class="crumb">{{ $total }} {{ \Illuminate\Support\Str::plural('article', $total) }} &middot; SOPs, guides and shared playbooks</div>
    </div>
  </div>
  <button class="btn sm" type="button" data-modal-open="kb-new">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
    New article
  </button>
</div>

<div class="kb-layout" data-ajax-region="kb-page">
  <div class="kb-main">
    <div class="card kb-list-card">
      <form method="GET" data-ajax-filter data-region="kb-page" class="kb-toolbar noprint">
        <label class="kb-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input class="in" type="search" name="q" value="{{ $q ?? '' }}" placeholder="Search articles by title or body" aria-label="Search knowledge base">
        </label>
        <select class="in kb-filter" name="category" aria-label="Filter by kind">
          <option value="">All kinds</option>
          @foreach($kinds as $kind)
            <option value="{{ $kind }}" {{ ($category ?? '') === $kind ? 'selected' : '' }}>{{ $kind }}</option>
          @endforeach
        </select>
        <button class="btn sec sm" type="submit">Filter</button>
        @if(($q ?? '') !== '' || ($category ?? '') !== '')
          <a class="btn sec sm" href="{{ route('knowledge.index') }}">Clear</a>
        @endif
      </form>

        @if(($q ?? '') !== '' || ($category ?? '') !== '')
          <div class="kb-resultline">
            <span>{{ $rows->total() }} {{ \Illuminate\Support\Str::plural('result', $rows->total()) }}@if(($q ?? '') !== '') for &ldquo;{{ $q }}&rdquo;@endif</span>
          </div>
        @endif

        <div class="kb-list">
          @forelse($rows as $a)
            @php
              $excerpt = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', $a->body ?? '')), 165);
              $author = $a->author->name ?? 'Team';
              $kindSlug = \Illuminate\Support\Str::slug($a->category);
            @endphp
            <a class="kb-item" href="{{ route('knowledge.show', $a) }}">
              <span class="kb-kind kb-kind-{{ $kindSlug }}">{{ $a->category }}</span>
              <span class="kb-body">
                <span class="kb-title">
                  {{ $a->title }}
                  @if($a->pinned)
                    <span class="kb-pin" role="img" aria-label="Pinned" title="Pinned">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6l-1 6 3 3v2H7v-2l3-3-1-6z"/><path d="M12 14v7"/></svg>
                    </span>
                  @endif
                </span>
                @if($excerpt !== '')
                  <span class="kb-excerpt">{{ $excerpt }}</span>
                @endif
                <span class="kb-meta">
                  <span class="kb-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($author, 0, 1)) }}</span>
                  <span>By <b>{{ $author }}</b></span>
                  <span class="kb-dot" aria-hidden="true"></span>
                  <span>Updated {{ d($a->updated_at, 'j M Y') }}</span>
                </span>
              </span>
              <span class="kb-go" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
              </span>
            </a>
          @empty
            <div class="kb-empty">
              <span class="kb-empty-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v17H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 4.5v17"/><path d="M8 8h8M8 12h5"/></svg>
              </span>
              <strong>No articles found</strong>
              <p>{{ (($q ?? '') !== '' || ($category ?? '') !== '') ? 'No articles match these filters. Clear them above to see everything.' : 'Published articles will appear here.' }}</p>
              @if(($q ?? '') === '' && ($category ?? '') === '')
                <button class="btn sm" type="button" data-modal-open="kb-new">Write the first article</button>
              @endif
            </div>
          @endforelse
        </div>

        @if($rows->total() > 0)
          {{ $rows->links() }}
        @endif
    </div>
  </div>

  <aside class="kb-side noprint">
    <div class="card kb-side-card">
      <div class="card-head"><h3>Browse by kind</h3></div>
      <div class="kb-cats">
        <a class="kb-cat {{ ($category ?? '') === '' ? 'active' : '' }}" href="{{ route('knowledge.index', array_filter(['q' => $q ?? ''], fn ($v) => $v !== '' && $v !== null)) }}">
          All articles <span>{{ $total }}</span>
        </a>
        @foreach($kinds as $kind)
          <a class="kb-cat {{ ($category ?? '') === $kind ? 'active' : '' }}" href="{{ route('knowledge.index', array_filter(['q' => $q ?? '', 'category' => $kind], fn ($v) => $v !== '' && $v !== null)) }}">
            {{ $kind }} <span>{{ $counts[$kind] ?? 0 }}</span>
          </a>
        @endforeach
      </div>
    </div>

    @if($pinned->isNotEmpty())
      <div class="card kb-side-card">
        <div class="card-head"><h3>Pinned</h3></div>
        <div class="kb-pin-list">
          @foreach($pinned as $p)
            <a href="{{ route('knowledge.show', $p) }}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3h6l-1 6 3 3v2H7v-2l3-3-1-6z"/><path d="M12 14v7"/></svg>
              <span>{{ $p->title }}</span>
            </a>
          @endforeach
        </div>
      </div>
    @endif
  </aside>
</div>

<div class="modal" id="kb-new" role="dialog" aria-modal="true" aria-labelledby="kb-new-title">
  <div class="modal-bg" data-modal-close></div>
  <div class="modal-card kb-modal">
    <div class="kb-modal-head">
      <div>
        <h3 id="kb-new-title">New article</h3>
        <p class="hint">Capture a process before it lives only in someone&rsquo;s head.</p>
      </div>
      <button type="button" class="icon-btn" data-modal-close aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </div>
    <form method="POST" action="{{ route('knowledge.store') }}">
      @csrf
      <label class="f" for="kb-title">Title <span class="req">*</span></label>
      <input class="in" id="kb-title" name="title" required maxlength="255" value="{{ old('title') }}" placeholder="e.g. How we run a project kick-off">
      <label class="f" for="kb-category">Kind</label>
      <select class="in" id="kb-category" name="category">
        @foreach(\Modules\Projects\Article::categories() as $kind)
          <option value="{{ $kind }}" {{ old('category') === $kind ? 'selected' : '' }}>{{ $kind }}</option>
        @endforeach
      </select>
      <label class="f" for="kb-body">Body <span class="req">*</span></label>
      <textarea class="in" id="kb-body" name="body" rows="8" required placeholder="Steps, rules, links... (blank lines = paragraphs)">{{ old('body') }}</textarea>
      <div class="form-actions">
        <button class="btn" type="submit">Publish article</button>
        <button class="btn sec" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</div>
@endsection
