@extends('layouts.app')
@section('title','Automations')
@section('content')
@php
  $triggers = \Modules\Projects\WorkflowRule::triggers();
  $actions = \Modules\Projects\WorkflowRule::actions();
  $stateVal = $state ?? '';
  $triggerVal = $trigger ?? '';
  $hasFilters = ($q ?? '') !== '' || $stateVal !== '' || $triggerVal !== '';
  $stateUrl = fn ($s) => route('automations.index', array_merge(request()->except(['page', 'state']), $s ? ['state' => $s] : []));
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4.5 13.5H11L10 22l8.5-11.5H12z"/></svg>
    </span>
    <div>
      <h1>Automations</h1>
      <div class="crumb">{{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('rule', $stats['total']) }} · {{ $stats['live'] }} live · checks every morning</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <form method="POST" action="{{ route('automations.run') }}" data-confirm="Evaluate all rules right now?">
      @csrf
      <button class="btn sm sec">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8 5 11 7-11 7z"/></svg>
        Run now
      </button>
    </form>
  </div>
</div>

@if(session('automation_lines'))
  <div class="alert" style="margin-bottom:14px">{{ implode('<br>', array_map('e', (array) session('automation_lines'))) }}</div>
@endif

<div data-ajax-region="automation-list">
  <div class="ix-metrics">
    <div class="ix-metric"><span class="ix-metric-lbl">All rules</span><b class="ix-metric-val">{{ $stats['total'] }}</b><span class="ix-metric-sub">{{ $stats['live'] }} live · {{ $stats['paused'] }} paused</span></div>
    <div class="ix-metric"><span class="ix-metric-lbl">Live</span><b class="ix-metric-val">{{ $stats['live'] }}</b><span class="ix-metric-sub">nudging automatically</span></div>
    <div class="ix-metric"><span class="ix-metric-lbl">Paused</span><b class="ix-metric-val">{{ $stats['paused'] }}</b><span class="ix-metric-sub">kept but not firing</span></div>
    <div class="ix-metric"><span class="ix-metric-lbl">Firings logged</span><b class="ix-metric-val">{{ $stats['firings'] }}</b><span class="ix-metric-sub">once per item per day</span></div>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Rules</h3><span class="hint">{{ $rules->count() }} {{ \Illuminate\Support\Str::plural('rule', $rules->count()) }}{{ $hasFilters ? ' matching filters' : ' in this workspace' }}</span></div>
    </div>

    <div class="ix-statuses noprint">
      <a class="ix-chip {{ $stateVal === '' ? 'on' : '' }}" href="{{ $stateUrl('') }}"><i style="background:var(--dc-ink2)"></i>All <b>{{ $stats['total'] }}</b></a>
      <a class="ix-chip {{ $stateVal === 'live' ? 'on' : '' }}" href="{{ $stateUrl('live') }}"><i style="background:var(--dc-green)"></i>Live <b>{{ $stats['live'] }}</b></a>
      <a class="ix-chip {{ $stateVal === 'paused' ? 'on' : '' }}" href="{{ $stateUrl('paused') }}"><i style="background:var(--dc-muted)"></i>Paused <b>{{ $stats['paused'] }}</b></a>
    </div>

    <form method="GET" data-ajax-filter data-region="automation-list" class="ix-filters noprint">
      <label class="ix-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
        <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search rule name…" autocomplete="off">
      </label>
      <select class="in ix-filter" name="trigger">
        <option value="">All triggers</option>
        @foreach($triggers as $v => $l)
          <option value="{{ $v }}" {{ $triggerVal === $v ? 'selected' : '' }}>{{ $l }}</option>
        @endforeach
      </select>
      <button class="btn sec sm ix-filter-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filter</button>
      @if($hasFilters)<a class="btn sm sec ix-clear-btn" href="{{ route('automations.index') }}">Clear</a>@endif
    </form>

    <form method="POST" action="{{ route('automations.store') }}" data-ajax-add data-region="automation-list" class="ix-quick au-quick noprint">
      @csrf
      <span class="ix-quick-lbl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4.5 13.5H11L10 22l8.5-11.5H12z"/></svg>New rule</span>
      <label class="au-f is-name" for="au-name"><span>Rule name</span><input id="au-name" class="in" name="name" required maxlength="150" placeholder="e.g. Chase stale leads" autocomplete="off"></label>
      <label class="au-f is-trigger" for="au-trigger"><span>When</span><select id="au-trigger" class="in" name="trigger">@foreach($triggers as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></label>
      <label class="au-f is-days" for="au-days"><span>After</span><span class="au-days"><input id="au-days" class="in" type="number" min="0" max="365" name="after_days" value="3" autocomplete="off"><small>days</small></span></label>
      <label class="au-f is-action" for="au-action"><span>Do</span><select id="au-action" class="in" name="action">@foreach($actions as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></label>
      <label class="au-f is-target" for="au-target"><span>Reassign to</span><select id="au-target" class="in" name="reassign_to"><option value="">— Not needed —</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></label>
      <button class="btn sm au-save">Save rule</button>
    </form>

    <div class="tbl-scroll roomy ix-table-scroll">
      <table class="tbl ix-table au-table">
        <thead>
          <tr><th class="ix-c-main">Rule</th><th class="ix-c-trigger">Trigger</th><th class="ix-c-action">Action</th><th class="ix-c-status">Status</th><th class="ix-c-fired">Last fired</th><th class="ix-c-actions" aria-label="Actions"></th></tr>
        </thead>
        <tbody>
        @forelse($rules as $r)
          @php
            $paused = ! $r->is_active;
            $lastOn = $lastFired[$r->id] ?? null;
            $lastAgo = $lastOn ? \Illuminate\Support\Carbon::parse($lastOn)->diffForHumans(['short' => true, 'parts' => 1]) : null;
          @endphp
          <tr>
            <td class="ix-c-main">
              <div class="ix-main">
                <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4.5 13.5H11L10 22l8.5-11.5H12z"/></svg></span>
                <div class="ix-main-txt">
                  <span class="ix-name">{{ $r->name }}</span>
                  <span class="ix-sub">Once per item per day</span>
                </div>
              </div>
            </td>
            <td class="ix-c-trigger">
              <div class="ix-main-txt">
                <span class="ix-vehicle">{{ $triggers[$r->trigger] ?? $r->trigger }}</span>
                <span class="ix-sub">{{ $r->after_days > 0 ? 'after '.$r->after_days.' '.\Illuminate\Support\Str::plural('day', $r->after_days) : 'same day' }}</span>
              </div>
            </td>
            <td class="ix-c-action">
              <div class="ix-main-txt">
                <span class="ix-vehicle">{{ $actions[$r->action] ?? $r->action }}</span>
                @if($r->action === 'reassign' && $r->target)<span class="ix-sub">→ {{ $r->target->name }}</span>@endif
              </div>
            </td>
            <td class="ix-c-status"><span class="ix-state {{ $paused ? 'is-grey' : 'is-green' }}"><i></i>{{ $paused ? 'Paused' : 'Live' }}</span></td>
            <td class="ix-c-fired">
              <div class="ix-stack-end">
                <span class="ix-vehicle">{{ $lastAgo ?: 'Never' }}</span>
                <span class="ix-sub">{{ $lastOn ? d($lastOn, 'j M Y') : 'no runs yet' }}</span>
              </div>
            </td>
            <td class="ix-c-actions">
              <div class="ix-actions">
                <form method="POST" action="{{ route('automations.toggle', $r) }}">@csrf<button class="ix-act" title="{{ $paused ? 'Resume rule' : 'Pause rule' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">@if($paused)<path d="m8 5 11 7-11 7z"/>@else<path d="M9 5v14M15 5v14"/>@endif</svg></button></form>
                <form method="POST" action="{{ route('automations.destroy', $r) }}" data-confirm="Remove {{ $r->name }}?" data-refresh='[data-ajax-region="automation-list"]'>@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete rule"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="6"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4.5 13.5H11L10 22l8.5-11.5H12z"/></svg></span><strong>{{ $hasFilters ? 'No rules match these filters' : 'No automations yet' }}</strong><p>{{ $hasFilters ? 'Try a different search or clear the active filters.' : 'Add a rule above and overdue work will start chasing itself.' }}</p>@if($hasFilters)<div class="ix-empty-actions"><a class="btn sm sec" href="{{ route('automations.index') }}">Clear filters</a></div>@endif</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($firings->isNotEmpty())
    <div class="card ix-card au-firings">
      <div class="card-head ix-card-head">
        <div><h3>Recent firings</h3><span class="hint">{{ $firings->count() }} most recent · newest first</span></div>
      </div>
      <div class="tbl-scroll roomy ix-table-scroll">
        <table class="tbl ix-table af-table">
          <thead>
            <tr><th class="ix-c-when">When</th><th class="ix-c-rule">Rule</th><th class="ix-c-subject">Subject</th></tr>
          </thead>
          <tbody>
          @foreach($firings as $f)
            <tr>
              <td class="ix-c-when"><span class="ix-vehicle">{{ d($f->created_at, 'j M, h:i A') }}</span></td>
              <td class="ix-c-rule"><span class="ix-name">{{ $f->rule }}</span></td>
              <td class="ix-c-subject"><span class="ix-sub">{{ class_basename($f->subject_type) }} #{{ $f->subject_id }}</span></td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
</div>
@endsection
