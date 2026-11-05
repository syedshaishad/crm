@extends('layouts.app')
@section('title','Meetings')
@section('content')
@php
  $people = function ($meeting) use ($staff) {
      return collect($meeting->attendees ?? [])->map(function ($attendee) use ($staff) {
          if (is_numeric($attendee)) {
              $user = $staff[(int) $attendee] ?? null;
              return $user ? ['name' => $user->name, 'user' => $user] : null;
          }
          if (! is_string($attendee) || trim($attendee) === '') {
              return null;
          }
          return ['name' => $attendee, 'user' => $staff->firstWhere('name', $attendee)];
      })->filter()->values();
  };
  $openSchedule = $errors->has('title') || $errors->has('starts_at') || $errors->has('duration_minutes') || $errors->has('attendees') || $errors->has('agenda') || $errors->has('project_id');
@endphp
<div class="page-head mtg-head">
  <div class="mtg-head-main">
    <span class="mtg-head-ic" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/><path d="m9 15 2 2 4-4"/></svg>
    </span>
    <div>
      <h1>Meetings</h1>
      <div class="crumb">{{ $upcoming->count() }} upcoming &middot; agenda in, minutes out</div>
    </div>
  </div>
  <button class="btn sm" type="button" data-modal-open="mtg-new">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
    Schedule meeting
  </button>
</div>

<div class="mtg-layout">
  <section class="card mtg-card">
    <div class="card-head mtg-card-head">
      <div>
        <h3>Upcoming</h3>
        <span class="hint">Soonest first</span>
      </div>
      <span class="mtg-count">{{ $upcoming->count() }}</span>
    </div>

    <div class="mtg-list">
      @forelse($upcoming as $m)
        @php $names = $people($m); @endphp
        <article class="mtg-item">
          <div class="mtg-date {{ $m->starts_at->isToday() ? 'is-today' : '' }}">
            <b>{{ $m->starts_at->format('j') }}</b>
            <span>{{ $m->starts_at->format('M') }}</span>
          </div>
          <div class="mtg-body">
            <div class="mtg-title">{{ $m->title }}</div>
            <div class="mtg-meta">
              <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/></svg>
                {{ $m->starts_at->format('D') }} &middot; {{ $m->starts_at->format('g:i A') }} – {{ $m->endsAt()->format('g:i A') }}
              </span>
              <span>{{ $m->duration_minutes }} min</span>
              @if($m->project)
                <span>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  {{ $m->project->name }}
                </span>
              @endif
            </div>
            @if($m->agenda)
              <p class="mtg-agenda">{{ \Illuminate\Support\Str::limit($m->agenda, 220) }}</p>
            @endif
            @if($names->isNotEmpty())
              <div class="mtg-people">
                <span class="mtg-avatars">
                  @foreach($names->take(4) as $person)
                    @if($person['user'])
                      @include('partials.avatar', ['user' => $person['user'], 'class' => 'avatar sm'])
                    @else
                      <span class="mtg-more" title="{{ $person['name'] }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($person['name'], 0, 1)) }}</span>
                    @endif
                  @endforeach
                  @if($names->count() > 4)
                    <span class="mtg-more">+{{ $names->count() - 4 }}</span>
                  @endif
                </span>
                <span class="mtg-people-label">
                  {{ $names->take(2)->pluck('name')->implode(', ') }}{{ $names->count() > 2 ? ' +'.($names->count() - 2) : '' }}
                </span>
              </div>
            @endif
            <details class="mtg-minutes">
              <summary>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                {{ $m->notes ? 'Minutes' : 'Add minutes' }}
              </summary>
              <form method="POST" action="{{ route('meetings.notes', $m) }}">
                @csrf @method('PUT')
                <textarea class="in" name="notes" rows="3" placeholder="Decisions, owners, next steps...">{{ $m->notes }}</textarea>
                <div class="mtg-min-actions">
                  <button class="btn sm" type="submit">Save minutes</button>
                </div>
              </form>
              <form method="POST" action="{{ route('meetings.destroy', $m) }}" data-confirm="Remove this meeting?" class="mtg-remove">
                @csrf @method('DELETE')
                <button class="mtg-delete" type="submit">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
                  Remove meeting
                </button>
              </form>
            </details>
          </div>
        </article>
      @empty
        <div class="mtg-empty">
          <span class="mtg-empty-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/><path d="m9 15 2 2 4-4"/></svg>
          </span>
          <strong>Nothing scheduled</strong>
          <p>Your calendar is clear. Schedule the next kickoff, review or client call.</p>
          <button class="btn sm" type="button" data-modal-open="mtg-new">Schedule a meeting</button>
        </div>
      @endforelse
    </div>
  </section>

  <aside class="mtg-side">
    <section class="card mtg-card mtg-past">
      <div class="card-head">
        <div>
          <h3>Past</h3>
          <span class="hint">{{ $past->total() }} on record</span>
        </div>
      </div>

      <div class="mtg-past-list">
        @forelse($past as $m)
          @php $count = count($m->attendees ?? []); @endphp
          <article class="mtg-past-item">
            <div class="mtg-past-date">{{ $m->starts_at->format('j M Y') }}</div>
            <div class="mtg-past-title">{{ $m->title }}</div>
            <div class="mtg-past-meta">
              @if($m->project)
                <span>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  {{ $m->project->name }}
                </span>
              @endif
              @if($count)
                <span>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5 6.5-5s5.7 1.5 6.5 5"/><path d="M16 8.5a3 3 0 0 1 0 5.8"/><path d="M17.5 20c-.3-2-1.2-3.6-2.6-4.6"/></svg>
                  {{ $count }} {{ \Illuminate\Support\Str::plural('attendee', $count) }}
                </span>
              @endif
            </div>
            @if($m->notes)
              <p class="mtg-past-notes">{{ \Illuminate\Support\Str::limit($m->notes, 180) }}</p>
            @endif
          </article>
        @empty
          <p class="mtg-past-empty">No past meetings yet.</p>
        @endforelse
      </div>

      @if($past->hasPages())
        <div class="mtg-past-pager">{{ $past->links() }}</div>
      @endif
    </section>
  </aside>
</div>

<div class="modal {{ $openSchedule ? 'open' : '' }}" id="mtg-new" role="dialog" aria-modal="true" aria-labelledby="mtg-new-title">
  <div class="modal-bg" data-modal-close></div>
  <div class="modal-card mtg-modal">
    <div class="mtg-modal-head">
      <div>
        <h3 id="mtg-new-title">Schedule a meeting</h3>
        <p class="hint">Put the agenda in now, so the minutes write themselves later.</p>
      </div>
      <button type="button" class="icon-btn" data-modal-close aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </div>
    <form method="POST" action="{{ route('meetings.store') }}">
      @csrf
      <label class="f" for="mtg-title">Title <span class="req">*</span></label>
      <input class="in" id="mtg-title" name="title" required maxlength="255" value="{{ old('title') }}" placeholder="e.g. Site review with client">
      <div class="form-grid">
        <div>
          <label class="f" for="mtg-when">When <span class="req">*</span></label>
          <input class="in" id="mtg-when" type="datetime-local" name="starts_at" required value="{{ old('starts_at', now()->addHour()->startOfHour()->format('Y-m-d\TH:i')) }}">
        </div>
        <div>
          <label class="f" for="mtg-mins">Duration</label>
          <input class="in" id="mtg-mins" type="number" min="5" max="480" name="duration_minutes" value="{{ old('duration_minutes', 30) }}">
        </div>
      </div>
      <div class="form-grid">
        <div>
          <label class="f" for="mtg-project">Project</label>
          <select class="in" id="mtg-project" name="project_id">
            <option value="">No project</option>
            @foreach($projects as $p)
              <option value="{{ $p->id }}" {{ (string) old('project_id') === (string) $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="has-drop" data-attendee-picker>
          <label class="f">Attendees</label>
          <button type="button" class="att-picker-btn" aria-haspopup="true">
            <span data-attendee-label>Choose attendees</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
          </button>
          <div class="drop att-picker-menu">
            @forelse($users as $u)
              <label class="att-opt">
                <input type="checkbox" name="attendees[]" value="{{ $u->id }}" @checked(in_array($u->id, (array) old('attendees', [])))>
                @include('partials.avatar', ['user' => $u, 'class' => 'avatar sm'])
                <span>{{ $u->name }}</span>
              </label>
            @empty
              <p class="hint" style="padding:8px 10px;margin:0">No active staff accounts.</p>
            @endforelse
          </div>
        </div>
      </div>
      <label class="f" for="mtg-agenda">Agenda</label>
      <textarea class="in" id="mtg-agenda" name="agenda" rows="3" placeholder="What needs to be decided?">{{ old('agenda') }}</textarea>
      <div class="form-actions">
        <button class="btn" type="submit">Schedule</button>
        <button class="btn sec" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var picker = document.querySelector('[data-attendee-picker]');
  if (!picker) return;
  var label = picker.querySelector('[data-attendee-label]');
  function paint() {
    var n = picker.querySelectorAll('input[name="attendees[]"]:checked').length;
    label.textContent = n ? n + (n === 1 ? ' attendee selected' : ' attendees selected') : 'Choose attendees';
  }
  picker.addEventListener('change', paint);
  paint();
})();
</script>
@endsection
