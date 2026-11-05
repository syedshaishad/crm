@extends('layouts.app')
@section('title','Task templates')
@section('content')
@php
  $hasFilters = ($q ?? '') !== '';
  $starters = $taskTemplates->count();
  $packs = $checklistTemplates->count();
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a3 3 0 0 1 6 0"/><path d="m9.5 13.5 2 2 3.5-4"/></svg>
    </span>
    <div>
      <h1>Task templates</h1>
      <div class="crumb">{{ $stats['starters'] }} {{ \Illuminate\Support\Str::plural('starter', $stats['starters']) }} · {{ $stats['packs'] }} checklist {{ \Illuminate\Support\Str::plural('pack', $stats['packs']) }} · ready to reuse</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('tasks.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      All tasks
    </a>
  </div>
</div>

<div data-ajax-region="templates-list">
  <div class="ix-metrics">
    <div class="ix-metric"><span class="ix-metric-lbl">Task starters</span><b class="ix-metric-val">{{ $stats['starters'] }}</b><span class="ix-metric-sub">one-click task presets</span></div>
    <div class="ix-metric"><span class="ix-metric-lbl">Starter steps</span><b class="ix-metric-val">{{ $stats['starter_steps'] }}</b><span class="ix-metric-sub">checklist steps inside starters</span></div>
    <div class="ix-metric"><span class="ix-metric-lbl">Checklist packs</span><b class="ix-metric-val">{{ $stats['packs'] }}</b><span class="ix-metric-sub">plug into any task</span></div>
    <div class="ix-metric"><span class="ix-metric-lbl">Pack steps</span><b class="ix-metric-val">{{ $stats['pack_steps'] }}</b><span class="ix-metric-sub">steps across packs</span></div>
  </div>

  <form method="GET" data-ajax-filter data-region="templates-list" class="ix-filters noprint">
    <label class="ix-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search starters, task titles or checklist packs…" autocomplete="off">
    </label>
    <button class="btn sec sm ix-filter-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filter</button>
    @if($hasFilters)<a class="btn sm sec ix-clear-btn" href="{{ route('tasks.templates') }}">Clear</a>@endif
  </form>

  <div class="tpl-grid">
    <div class="card ix-card tpl-card">
      <div class="card-head ix-card-head">
        <div><h3>Task starters</h3><span class="hint">{{ $starters }} {{ \Illuminate\Support\Str::plural('starter', $starters) }}{{ $hasFilters ? ' matching search' : ' ready to use' }}</span></div>
      </div>

      <form method="POST" action="{{ route('tasks.templates.store') }}" data-ajax-add data-region="templates-list" class="tpl-form noprint">
        @csrf
        <div class="tpl-f"><label class="f" for="tt-name">Name</label><input id="tt-name" class="in" name="name" required maxlength="150" placeholder="e.g. New site visit"></div>
        <div class="tpl-f"><label class="f" for="tt-title">Task title</label><input id="tt-title" class="in" name="title" required maxlength="255" placeholder="What gets created"></div>
        <div class="tpl-f"><label class="f" for="tt-est">Estimate (min)</label><input id="tt-est" class="in" type="number" min="0" name="estimate_minutes" value="0"></div>
        <div class="tpl-f"><label class="f" for="tt-pri">Priority</label><select id="tt-pri" class="in" name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
        <div class="tpl-f is-wide"><label class="f" for="tt-check">Checklist steps (one per line)</label><textarea id="tt-check" class="in" name="checklist" rows="2" placeholder="Call ahead…&#10;Carry meter…"></textarea></div>
        <div class="tpl-actions"><button class="btn sm">Save starter</button></div>
      </form>

      <div class="tbl-scroll roomy ix-table-scroll">
        <table class="tbl ix-table tp-table">
          <thead>
            <tr><th class="ix-c-main">Starter</th><th class="ix-c-steps">Steps</th><th class="ix-c-actions" aria-label="Actions"></th></tr>
          </thead>
          <tbody>
          @forelse($taskTemplates as $t)
            @php $steps = count($t->lines()); @endphp
            <tr>
              <td class="ix-c-main">
                <div class="ix-main">
                  <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a3 3 0 0 1 6 0"/><path d="m9.5 13.5 2 2 3.5-4"/></svg></span>
                  <div class="ix-main-txt">
                    <span class="ix-name">{{ $t->name }}</span>
                    <span class="ix-sub">{{ $t->title }}@if($t->estimate_minutes) · {{ $t->estimate_minutes }} min @endif</span>
                  </div>
                </div>
              </td>
              <td class="ix-c-steps">
                <div class="ix-stack-end">
                  <span class="ix-count">{{ $steps }}</span>
                  <span class="ix-sub">{{ \Illuminate\Support\Str::plural('step', $steps) }}</span>
                </div>
              </td>
              <td class="ix-c-actions">
                <div class="ix-actions">
                  <form method="POST" action="{{ route('tasks.fromTemplate') }}">@csrf<input type="hidden" name="template_id" value="{{ $t->id }}"><button class="ix-act" title="Create a task from this starter"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8 5 11 7-11 7z"/></svg></button></form>
                  <form method="POST" action="{{ route('tasks.templates.destroy', $t) }}" data-confirm="Remove {{ $t->name }}?" data-refresh='[data-ajax-region="templates-list"]'>@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete starter"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="3"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a3 3 0 0 1 6 0"/><path d="m9.5 13.5 2 2 3.5-4"/></svg></span><strong>{{ $hasFilters ? 'No starters match this search' : 'No task starters yet' }}</strong><p>{{ $hasFilters ? 'Try a different search or clear the filter.' : 'Save a starter above to create one-click tasks.' }}</p>@if($hasFilters)<div class="ix-empty-actions"><a class="btn sm sec" href="{{ route('tasks.templates') }}">Clear search</a></div>@endif</div></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card ix-card tpl-card">
      <div class="card-head ix-card-head">
        <div><h3>Checklist packs</h3><span class="hint">{{ $packs }} {{ \Illuminate\Support\Str::plural('pack', $packs) }}{{ $hasFilters ? ' matching search' : ' reusable anywhere' }}</span></div>
      </div>

      <form method="POST" action="{{ route('tasks.checklistTemplates.store') }}" data-ajax-add data-region="templates-list" class="tpl-form noprint">
        @csrf
        <div class="tpl-f is-wide"><label class="f" for="ct-name">Name</label><input id="ct-name" class="in" name="name" required maxlength="150" placeholder="e.g. Safety checks"></div>
        <div class="tpl-f is-wide"><label class="f" for="ct-lines">Steps (one per line)</label><textarea id="ct-lines" class="in" name="lines" rows="3" required placeholder="Check harness…&#10;Sign off…"></textarea></div>
        <div class="tpl-actions"><button class="btn sm">Save pack</button></div>
      </form>

      <div class="tbl-scroll roomy ix-table-scroll">
        <table class="tbl ix-table tp-table">
          <thead>
            <tr><th class="ix-c-main">Pack</th><th class="ix-c-steps">Steps</th><th class="ix-c-actions" aria-label="Actions"></th></tr>
          </thead>
          <tbody>
          @forelse($checklistTemplates as $t)
            @php
              $lines = $t->lines();
              $steps = count($lines);
            @endphp
            <tr>
              <td class="ix-c-main">
                <div class="ix-main">
                  <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg></span>
                  <div class="ix-main-txt">
                    <span class="ix-name">{{ $t->name }}</span>
                    <span class="ix-sub">{{ $steps ? \Illuminate\Support\Str::limit(implode(' · ', array_slice($lines, 0, 2)), 76) : 'No steps yet' }}</span>
                  </div>
                </div>
              </td>
              <td class="ix-c-steps">
                <div class="ix-stack-end">
                  <span class="ix-count">{{ $steps }}</span>
                  <span class="ix-sub">{{ \Illuminate\Support\Str::plural('step', $steps) }}</span>
                </div>
              </td>
              <td class="ix-c-actions">
                <div class="ix-actions">
                  <form method="POST" action="{{ route('tasks.checklistTemplates.destroy', $t) }}" data-confirm="Remove {{ $t->name }}?" data-refresh='[data-ajax-region="templates-list"]'>@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete checklist pack"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="3"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg></span><strong>{{ $hasFilters ? 'No packs match this search' : 'No checklist packs yet' }}</strong><p>{{ $hasFilters ? 'Try a different search or clear the filter.' : 'Save a pack above to reuse the same steps on any task.' }}</p>@if($hasFilters)<div class="ix-empty-actions"><a class="btn sm sec" href="{{ route('tasks.templates') }}">Clear search</a></div>@endif</div></td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
