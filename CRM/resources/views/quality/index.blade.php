@extends('layouts.app')
@section('title','Quality loop')
@section('content')
@php
  $incidentStatuses = \Modules\Projects\Incident::statuses();
  $improvementStatuses = \Modules\Projects\Improvement::statuses();
@endphp
<div class="page-head ql-head">
  <div class="ql-head-main">
    <span class="ql-head-ic" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-3.5 8-10V5l-8-3-8 3v7c0 6.5 8 10 8 10z"/><path d="m9 11.5 2 2 4-4.5"/></svg>
    </span>
    <div>
      <h1>Quality loop</h1>
      <div class="crumb">Incidents, root causes and small improvements</div>
    </div>
  </div>
  <button class="btn sm" type="button" data-modal-open="ql-incident">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
    Log incident
  </button>
</div>

<div class="ql-layout">
  <div class="ql-main">
    <section class="card ql-card" data-ajax-region="q-list">
      <div class="card-head ql-card-head">
        <div>
          <h3>Incidents</h3>
          <span class="hint">What broke, why, and what changed</span>
        </div>
        <form method="GET" data-ajax-filter data-region="q-list" class="ql-filter noprint">
          <select class="in" name="status" aria-label="Filter incidents by status">
            <option value="">All statuses</option>
            @foreach($incidentStatuses as $value => $label)
              <option value="{{ $value }}" {{ ($status ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
          <button class="btn sec sm" type="submit">Filter</button>
          @if(($status ?? '') !== '')
            <a class="btn sec sm" href="{{ route('quality.index') }}">Clear</a>
          @endif
        </form>
      </div>

      <div class="ql-list">
        @forelse($incidents as $i)
          @php
            $state = strtolower($i->status);
            $severity = strtolower($i->severity);
          @endphp
          <article class="ql-incident {{ $state === 'fixed' ? 'is-fixed' : '' }}">
            <div class="ql-incident-top">
              <span class="ql-sev ql-sev-{{ $severity }}"><i></i>{{ ucfirst($i->severity) }}</span>
              <span class="ql-state ql-state-{{ $state }}"><i></i>{{ $incidentStatuses[$i->status] ?? ucfirst($i->status) }}</span>
              <span class="ql-date">{{ $i->occurred_on ? d($i->occurred_on, 'j M Y') : 'No date' }}</span>
            </div>
            <div class="ql-incident-title">{{ $i->title }}</div>
            @if($i->project)
              <div class="ql-incident-meta">
                <span>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  {{ $i->project->name }}
                </span>
              </div>
            @endif
            @if($i->what_happened)
              <p class="ql-incident-desc">{{ $i->what_happened }}</p>
            @endif
            @if($i->root_cause || $i->fix)
              <div class="ql-resolution">
                @if($i->root_cause)
                  <div><span>Root cause</span><p>{{ $i->root_cause }}</p></div>
                @endif
                @if($i->fix)
                  <div><span>Fix</span><p>{{ $i->fix }}</p></div>
                @endif
              </div>
            @endif
            <details class="ql-resolve">
              <summary>Edit resolution</summary>
              <form method="POST" action="{{ route('quality.incidents.update', $i) }}">
                @csrf @method('PUT')
                <div class="form-grid">
                  <div>
                    <label class="f" for="ql-root-{{ $i->id }}">Root cause</label>
                    <input class="in" id="ql-root-{{ $i->id }}" name="root_cause" maxlength="1000" value="{{ $i->root_cause }}" placeholder="Why did it happen?">
                  </div>
                  <div>
                    <label class="f" for="ql-fix-{{ $i->id }}">Fix</label>
                    <input class="in" id="ql-fix-{{ $i->id }}" name="fix" maxlength="1000" value="{{ $i->fix }}" placeholder="What changed?">
                  </div>
                </div>
                <div class="ql-resolve-actions">
                  <select class="in" name="status" aria-label="Incident status">
                    @foreach($incidentStatuses as $value => $label)
                      <option value="{{ $value }}" {{ $i->status === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                  <button class="btn sm" type="submit">Save changes</button>
                </div>
              </form>
              <form method="POST" action="{{ route('quality.incidents.destroy', $i) }}" data-confirm="Delete this incident? This cannot be undone.">
                @csrf @method('DELETE')
                <button class="ql-delete" type="submit">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
                  Delete incident
                </button>
              </form>
            </details>
          </article>
        @empty
          <div class="ql-empty">
            <span class="ql-empty-ic" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-3.5 8-10V5l-8-3-8 3v7c0 6.5 8 10 8 10z"/><path d="m9 11.5 2 2 4-4.5"/></svg>
            </span>
            <strong>{{ ($status ?? '') !== '' ? 'No incidents match this filter' : 'No incidents logged' }}</strong>
            <p>{{ ($status ?? '') !== '' ? 'Clear the filter to see every incident.' : 'When something breaks, log it here so the fix sticks.' }}</p>
            @if(($status ?? '') !== '')
              <a class="btn sec sm" href="{{ route('quality.index') }}">Clear filter</a>
            @else
              <button class="btn sm" type="button" data-modal-open="ql-incident">Log the first incident</button>
            @endif
          </div>
        @endforelse
      </div>

      @if($incidents->total() > 0)
        {{ $incidents->links() }}
      @endif
    </section>
  </div>

  <aside class="ql-side">
    <section class="card ql-card ql-kaizen">
      <div class="card-head">
        <div>
          <h3>Kaizen ideas</h3>
          <span class="hint">Small bets, every retro</span>
        </div>
      </div>
      <form method="POST" action="{{ route('quality.improvements.store') }}" class="ql-idea-form noprint">
        @csrf
        <input class="in" name="title" required maxlength="255" placeholder="Capture an idea..." aria-label="Kaizen idea">
        <button class="btn sm" type="submit">Add</button>
      </form>

      @foreach($improvementStatuses as $state => $label)
        @php $group = $improvements->where('status', $state); @endphp
        <section class="ql-idea-group ql-group-{{ $state }}">
          <div class="ql-idea-head">
            <i aria-hidden="true"></i>
            <span>{{ $label }}</span>
            <span class="ql-idea-count">{{ $group->count() }}</span>
          </div>
          @forelse($group as $m)
            <div class="ql-idea {{ $state === 'done' ? 'is-done' : '' }}">
              <div class="ql-idea-main">
                <span class="ql-idea-title">{{ $m->title }}</span>
                @if($m->detail)
                  <span class="ql-idea-detail">{{ $m->detail }}</span>
                @endif
              </div>
              <div class="ql-idea-actions">
                @if($state !== 'done')
                  <form method="POST" action="{{ route('quality.improvements.move', $m) }}" class="ql-inline">
                    @csrf
                    <button class="ql-mini" type="submit" name="status" value="{{ $state === 'proposed' ? 'doing' : 'done' }}">
                      @if($state === 'proposed')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m8 5 11 7-11 7z"/></svg>
                        Start
                      @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                        Done
                      @endif
                    </button>
                  </form>
                @endif
                <form method="POST" action="{{ route('quality.improvements.destroy', $m) }}" class="ql-inline" data-confirm="Drop this idea?">
                  @csrf @method('DELETE')
                  <button class="ql-mini ql-mini-danger" type="submit" aria-label="Drop idea" title="Drop idea">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                  </button>
                </form>
              </div>
            </div>
          @empty
            <p class="ql-idea-empty">No ideas here yet.</p>
          @endforelse
        </section>
      @endforeach
    </section>
  </aside>
</div>

<div class="modal" id="ql-incident" role="dialog" aria-modal="true" aria-labelledby="ql-incident-title">
  <div class="modal-bg" data-modal-close></div>
  <div class="modal-card ql-modal">
    <div class="ql-modal-head">
      <div>
        <h3 id="ql-incident-title">Log an incident</h3>
        <p class="hint">Record what broke so the next job goes better.</p>
      </div>
      <button type="button" class="icon-btn" data-modal-close aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </div>
    <form method="POST" action="{{ route('quality.incidents.store') }}">
      @csrf
      <label class="f" for="ql-title">Title <span class="req">*</span></label>
      <input class="in" id="ql-title" name="title" required maxlength="255" value="{{ old('title') }}" placeholder="e.g. Wrong switch rating at site">
      <div class="form-grid">
        <div>
          <label class="f" for="ql-severity">Severity</label>
          <select class="in" id="ql-severity" name="severity">
            @foreach(\Modules\Projects\Incident::severities() as $value => $label)
              <option value="{{ $value }}" {{ old('severity', 'medium') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="f" for="ql-when">When</label>
          <input class="in" id="ql-when" type="date" name="occurred_on" value="{{ old('occurred_on', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}">
        </div>
      </div>
      <label class="f" for="ql-project">Project</label>
      <select class="in" id="ql-project" name="project_id">
        <option value="">No project</option>
        @foreach($projects as $p)
          <option value="{{ $p->id }}" {{ (string) old('project_id') === (string) $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
        @endforeach
      </select>
      <label class="f" for="ql-what">What happened?</label>
      <textarea class="in" id="ql-what" name="what_happened" rows="4" placeholder="Context, symptoms, anything useful...">{{ old('what_happened') }}</textarea>
      <div class="form-actions">
        <button class="btn" type="submit">Log incident</button>
        <button class="btn sec" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</div>
@endsection
