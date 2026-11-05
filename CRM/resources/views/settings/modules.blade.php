@extends('layouts.app')
@section('title','Modules')
@section('content')
@php
  $enabled = 0; $disabled = 0;
  foreach ($modules as $m) {
      $on = ($over[$m] ?? config('modules.enabled.'.$m, true)) ? true : false;
      if ($on) { $enabled++; } else { $disabled++; }
  }
  $total = count($modules);
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v5"/></svg>
    </span>
    <div>
      <h1>Modules</h1>
      <div class="crumb">{{ $enabled }} of {{ $total }} workspace {{ Str::plural('area', $total) }} visible</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Settings
    </a>
    <button class="btn sm" type="submit" form="modules-form">Save modules</button>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Total modules</span>
    <b class="ix-metric-val">{{ $total }}</b>
    <span class="ix-metric-sub">switchable workspace areas</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Visible</span>
    <b class="ix-metric-val">{{ $enabled }}</b>
    <span class="ix-metric-sub">shown in nav, search and API</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Hidden</span>
    <b class="ix-metric-val">{{ $disabled }}</b>
    <span class="ix-metric-sub">{{ $disabled ? 'direct URLs return 404' : 'nothing is hidden' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Always on</span>
    <b class="ix-metric-val">Core</b>
    <span class="ix-metric-sub">settings, updates and licensing</span>
  </div>
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Workspace modules</h3><span class="hint">Switch areas off to simplify this install. Hidden modules vanish from the nav, search, import/export and API. Settings, updates and licensing always stay on.</span></div>
    <span class="ix-tag">{{ $total }} areas</span>
  </div>

  <form id="modules-form" method="POST" action="{{ route('settings.modules.store') }}">@csrf
    <div class="st-switch-grid">
      @foreach($modules as $m)
        @php $on = ($over[$m] ?? config('modules.enabled.'.$m, true)) ? true : false; @endphp
        <label class="st-toggle">
          <span class="st-toggle-main">
            <b>{{ ucfirst($m) }}</b>
            <span>{{ $on ? 'Visible in navigation, search and API' : 'Hidden everywhere — direct links 404' }}</span>
          </span>
          <input class="st-switch" type="checkbox" name="off[]" value="{{ $m }}" {{ $on ? '' : 'checked' }} aria-label="Hide {{ $m }}">
        </label>
      @endforeach
    </div>

    <div class="st-actions">
      <div class="st-actions-txt">
        <strong>Save module visibility</strong>
        <span>Changes take effect on the next page load.</span>
      </div>
      <div class="st-actions-btns">
        <a class="btn sec" href="{{ route('settings.index') }}">Cancel</a>
        <button class="btn" type="submit">Save modules</button>
      </div>
    </div>
  </form>
</div>
@endsection
