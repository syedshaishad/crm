@extends('layouts.app')
@section('title','Users')
@section('content')
@php
  $filters = ['q' => $q ?? '', 'role' => $role ?? '', 'status' => $status ?? ''];
  $hasFilters = $filters['q'] !== '' || $filters['role'] !== '' || $filters['status'] !== '';
  $base = route('settings.users.index');
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2 20c.8-3.8 3.4-5.5 7-5.5s6.2 1.7 7 5.5"/><path d="M17 8h5M19.5 5.5v5"/></svg>
    </span>
    <div>
      <h1>Team</h1>
      <div class="crumb">{{ number_format($stats['total']) }} {{ Str::plural('person', $stats['total']) }} · {{ $stats['admins'] }} {{ Str::plural('administrator', $stats['admins']) }}</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Settings
    </a>
    <a class="btn sm" href="{{ route('settings.users.create') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      New user
    </a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Total people</span>
    <b class="ix-metric-val">{{ number_format($stats['total']) }}</b>
    <span class="ix-metric-sub">on this installation</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Active</span>
    <b class="ix-metric-val">{{ number_format($stats['active']) }}</b>
    <span class="ix-metric-sub">can sign in today</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Administrators</span>
    <b class="ix-metric-val">{{ number_format($stats['admins']) }}</b>
    <span class="ix-metric-sub">full settings access</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Disabled</span>
    <b class="ix-metric-val">{{ number_format($stats['disabled']) }}</b>
    <span class="ix-metric-sub">{{ $stats['disabled'] ? 'sign-in blocked' : 'everyone can sign in' }}</span>
  </div>
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Team directory</h3><span class="hint">Search, filter and manage roles, activation and module access.</span></div>
    <span class="ix-tag">{{ $users->total() }} shown</span>
  </div>

  <div class="ix-statuses noprint">
    <a class="ix-chip {{ ! $hasFilters ? 'on' : '' }}" href="{{ $base }}">All <b>{{ $stats['total'] }}</b></a>
    <a class="ix-chip {{ $filters['status'] === 'active' ? 'on' : '' }}" href="{{ $base }}?status=active"><i style="background:var(--dc-green)"></i>Active <b>{{ $stats['active'] }}</b></a>
    <a class="ix-chip {{ $filters['status'] === 'disabled' ? 'on' : '' }}" href="{{ $base }}?status=disabled"><i style="background:var(--dc-muted)"></i>Disabled <b>{{ $stats['disabled'] }}</b></a>
    <a class="ix-chip {{ $filters['role'] === 'admin' ? 'on' : '' }}" href="{{ $base }}?role=admin"><i style="background:var(--dc-blue)"></i>Admins <b>{{ $stats['admins'] }}</b></a>
  </div>

  <form method="GET" data-ajax-filter data-region="users-list" class="ix-filters noprint">
    <label class="ix-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <input class="in" name="q" value="{{ $filters['q'] }}" placeholder="Search name or email…" aria-label="Search users">
    </label>
    <select class="in ix-filter" name="role" aria-label="Filter by role">
      <option value="">All roles</option>
      @foreach(['admin','manager','staff'] as $rl)
        <option value="{{ $rl }}" {{ $filters['role']===$rl?'selected':'' }}>{{ ucfirst($rl) }}</option>
      @endforeach
    </select>
    <select class="in ix-filter" name="status" aria-label="Filter by status">
      <option value="">Any status</option>
      <option value="active" {{ $filters['status']==='active'?'selected':'' }}>Active</option>
      <option value="disabled" {{ $filters['status']==='disabled'?'selected':'' }}>Disabled</option>
    </select>
    <button class="btn sec sm ix-filter-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16M7 12h10M10 19h4"/></svg>
      Filter
    </button>
    @if($hasFilters)<a class="btn sec sm" href="{{ $base }}">Clear</a>@endif
  </form>

  <div data-ajax-region="users-list">
    <div class="ix-table-scroll">
      <table class="tbl ix-table us-table">
        <thead><tr>
          <th class="ix-c-main">Name</th>
          <th class="ix-c-email">Email</th>
          <th class="ix-c-role">Role</th>
          <th class="ix-c-status">Status</th>
          <th class="ix-c-actions"></th>
        </tr></thead>
        <tbody>
          @forelse($users as $u)
            @php $roleTone = $u->role === 'admin' ? 'is-blue' : ($u->role === 'manager' ? 'is-amber' : 'is-grey'); @endphp
            <tr>
              <td class="ix-c-main">
                <span class="st-user">
                  @include('partials.avatar', ['user' => $u, 'class' => 'av sm'])
                  <span class="ix-main-txt">
                    <span class="ix-name">{{ $u->name }}</span>
                    <span class="ix-sub">{{ $u->designation ?: 'Team member' }}{{ $u->department ? ' · '.$u->department : '' }}</span>
                  </span>
                </span>
              </td>
              <td class="ix-c-email"><a class="ix-email" href="mailto:{{ $u->email }}">{{ $u->email }}</a></td>
              <td class="ix-c-role"><span class="ix-state {{ $roleTone }}"><i></i>{{ ucfirst($u->role) }}</span></td>
              <td class="ix-c-status"><span class="ix-state {{ $u->is_active ? 'is-green' : 'is-grey' }}"><i></i>{{ $u->is_active ? 'Active' : 'Disabled' }}</span></td>
              <td class="ix-c-actions">
                <div class="ix-actions">
                  <a class="ix-act" href="{{ route('settings.users.edit',$u) }}" title="Edit {{ $u->name }}" aria-label="Edit {{ $u->name }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                  </a>
                  @if($u->id !== auth()->id())
                    <form method="POST" action="{{ route('settings.users.destroy',$u) }}" data-confirm="Delete {{ $u->email }}?">@csrf @method('DELETE')
                      <button class="ix-act is-danger" title="Delete {{ $u->name }}" aria-label="Delete {{ $u->name }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
                      </button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5">
              <div class="ix-empty">
                <span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.9-3.8 3.7-5.5 7-5.5s6.1 1.7 7 5.5"/></svg></span>
                <strong>No users found</strong>
                <p>{{ $hasFilters ? 'Try a different search or clear the filters.' : 'Create the first team member to get started.' }}</p>
                <div class="ix-empty-actions">
                  @if($hasFilters)<a class="btn sec sm" href="{{ $base }}">Clear filters</a>@endif
                  <a class="btn sm" href="{{ route('settings.users.create') }}">New user</a>
                </div>
              </div>
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($users->hasPages())<div class="st-pager">{{ $users->links() }}</div>@endif
  </div>
</div>
@endsection
