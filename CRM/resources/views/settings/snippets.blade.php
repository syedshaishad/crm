@extends('layouts.app')
@section('title','Content library')
@section('content')
@php
  $kinds = \Modules\Proposals\Snippet::kinds();
  $total = $snippets->count();
  $usedKinds = $snippets->pluck('kind')->unique()->count();
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h7M9 17h5"/></svg>
    </span>
    <div>
      <h1>Content library</h1>
      <div class="crumb">{{ $total }} reusable {{ Str::plural('boilerplate', $total) }} across {{ $usedKinds }} {{ Str::plural('section', $usedKinds) }}</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Settings
    </a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Snippets</span>
    <b class="ix-metric-val">{{ $total }}</b>
    <span class="ix-metric-sub">saved boilerplates</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Sections in use</span>
    <b class="ix-metric-val">{{ $usedKinds }}<span class="br-metric-of">/{{ count($kinds) }}</span></b>
    <span class="ix-metric-sub">scope, results, terms and notes</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Latest edit</span>
    <b class="ix-metric-val is-money">{{ optional($snippets->sortByDesc('updated_at')->first())->updated_at ? d($snippets->sortByDesc('updated_at')->first()->updated_at,'j M Y') : '—' }}</b>
    <span class="ix-metric-sub">most recently changed</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Available to</span>
    <b class="ix-metric-val">Proposals</b>
    <span class="ix-metric-sub">insertable while editing</span>
  </div>
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Add a snippet</h3><span class="hint">Write once, reuse on every proposal. One point per line works best.</span></div>
    <span class="ix-tag">New</span>
  </div>
  <form method="POST" action="{{ route('settings.snippets.store') }}">@csrf
    <div class="form-grid">
      <div><label class="f" for="snip-title">Title</label><input class="in" id="snip-title" name="title" required maxlength="120" placeholder="e.g. Standard SEO scope"></div>
      <div><label class="f" for="snip-kind">Goes into</label><select class="in" id="snip-kind" name="kind">@foreach($kinds as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
    </div>
    <label class="f" for="snip-body">Text</label>
    <textarea class="in" id="snip-body" name="body" rows="3" required placeholder="One point per line…"></textarea>
    <div class="st-actions-inline">
      <button class="btn" type="submit">Add snippet</button>
      <span class="hint">Staff can insert this from the proposal editor.</span>
    </div>
  </form>
</div>

<div class="st-grid">
  @foreach($kinds as $k => $l)
    @php $list = $snippets->where('kind',$k); @endphp
    <div class="card ix-card">
      <div class="card-head ix-card-head">
        <div><h3>{{ $l }}</h3><span class="hint">{{ $list->count() }} {{ Str::plural('snippet', $list->count()) }} for this section.</span></div>
        <span class="ix-tag">{{ $list->count() }}</span>
      </div>
      @forelse($list as $s)
        <details class="st-snippet">
          <summary>
            <span class="st-snippet-title">{{ $s->title }}</span>
            <span class="st-snippet-meta">{{ \Str::limit(preg_replace('/\s+/', ' ', $s->body), 60) }}</span>
          </summary>
          <div class="st-snippet-body">
            <form method="POST" action="{{ route('settings.snippets.update',$s) }}">@csrf @method('PUT')
              <label class="f" for="snip-{{ $s->id }}-title">Title</label>
              <input class="in" id="snip-{{ $s->id }}-title" name="title" value="{{ $s->title }}" required maxlength="120">
              <label class="f" for="snip-{{ $s->id }}-body">Text</label>
              <textarea class="in" id="snip-{{ $s->id }}-body" name="body" rows="4" required>{{ $s->body }}</textarea>
              <input type="hidden" name="kind" value="{{ $s->kind }}">
              <div class="st-actions-inline">
                <button class="btn sec sm" type="submit">Save</button>
              </div>
            </form>
            <form method="POST" action="{{ route('settings.snippets.destroy',$s) }}" data-confirm="Delete this snippet?" class="st-snippet-delete">@csrf @method('DELETE')
              <button class="btn sec sm danger" type="submit">Delete</button>
            </form>
          </div>
        </details>
      @empty
        <div class="ix-empty">
          <span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h7"/></svg></span>
          <strong>No snippets yet</strong>
          <p>Add one above and it will appear here.</p>
        </div>
      @endforelse
    </div>
  @endforeach
</div>
@endsection
