@extends('layouts.app')
@section('title',$user->exists ? 'Edit user' : 'New user')
@section('content')
@php
  $perms = old('permissions', $user->permissions ?? []);
  $overrideCount = is_array($perms) ? count(array_filter($perms, fn ($v) => in_array($v, ['full','view','none'], true))) : 0;
  $isActive = (bool) old('is_active', $user->is_active ?? true);
  $roleNow = old('role', $user->role);
  $formAction = $user->exists ? route('settings.users.update',$user) : route('settings.users.store');
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.9-3.8 3.7-5.5 7-5.5s6.1 1.7 7 5.5"/></svg>
    </span>
    <div>
      <h1>{{ $user->exists ? 'Edit user' : 'New user' }}</h1>
      <div class="crumb">{{ $user->exists ? $user->email : 'Add a team member to this installation' }}</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.users.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Team
    </a>
    <button class="btn sm" type="submit" form="user-form">Save user</button>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Role</span>
    <b class="ix-metric-val">{{ ucfirst($roleNow) }}</b>
    <span class="ix-metric-sub">{{ $roleNow === 'admin' ? 'full access incl. settings' : 'workspace access' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Status</span>
    <b class="ix-metric-val">{{ $isActive ? 'Active' : 'Disabled' }}</b>
    <span class="ix-metric-sub">{{ $isActive ? 'can sign in' : 'sign-in blocked' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Module overrides</span>
    <b class="ix-metric-val">{{ $overrideCount }}</b>
    <span class="ix-metric-sub">{{ $overrideCount === 0 ? 'all inheriting the role default' : 'modules customised' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Weekly capacity</span>
    <b class="ix-metric-val">{{ $user->capacity_hours ?: '—' }}</b>
    <span class="ix-metric-sub">{{ $user->capacity_hours ? 'hours per week' : 'using the team default' }}</span>
  </div>
</div>

<form id="user-form" method="POST" action="{{ $formAction }}" enctype="multipart/form-data">@csrf @if($user->exists)@method('PUT')@endif

<div class="st-grid">
  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Identity &amp; access</h3><span class="hint">Sign-in details, role and activation.</span></div>
      <span class="ix-tag">Account</span>
    </div>

    <div class="st-profile">
      <div class="st-profile-av">@include('partials.avatar', ['user' => $user, 'class' => 'av lg'])</div>
      <div class="st-profile-fields">
        <label class="f" for="user-avatar">Profile picture <span class="hint">— optional, up to 4 MB</span></label>
        <input class="in" id="user-avatar" type="file" name="avatar" accept="image/*">
        @if($user->exists && $user->avatar_path)
          <label class="st-check is-plain">
            <input type="checkbox" name="remove_avatar" value="1">
            <span>Remove current picture</span>
          </label>
        @endif
      </div>
    </div>

    <div class="form-grid">
      <div>
        <label class="f" for="user-name">Name</label>
        <input class="in" id="user-name" name="name" value="{{ old('name',$user->name) }}" required autofocus autocomplete="off">
      </div>
      <div>
        <label class="f" for="user-email">Email</label>
        <input class="in" id="user-email" type="email" name="email" value="{{ old('email',$user->email) }}" required autocomplete="off">
      </div>
    </div>

    <div class="form-grid">
      <div>
        <label class="f" for="user-password">Password {{ $user->exists ? '(leave blank to keep)' : '' }}</label>
        <input class="in" id="user-password" type="password" name="password" {{ $user->exists ? '' : 'required' }} autocomplete="new-password">
      </div>
      <div>
        <label class="f" for="user-password-confirm">Confirm password</label>
        <input class="in" id="user-password-confirm" type="password" name="password_confirmation" autocomplete="new-password">
      </div>
    </div>

    <label class="f" for="role-select">Role</label>
    <select class="in" id="role-select" name="role">
      @foreach(['admin','manager','staff'] as $r)
        <option value="{{ $r }}" {{ $roleNow===$r ? 'selected' : '' }}>{{ ucfirst($r) }}{{ $r==='admin' ? ' — full access incl. settings' : ' — workspace, no settings' }}</option>
      @endforeach
    </select>

    <input type="hidden" name="is_active" value="0">
    <div class="st-checks is-one">
      <label class="st-check">
        <input type="checkbox" name="is_active" value="1" {{ $isActive ? 'checked' : '' }}>
        <span><b>Active</b><br>Allow this person to sign in. Disabling revokes their API tokens.</span>
      </label>
    </div>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Targets &amp; commission</h3><span class="hint">Collection goals used on the dashboard and payroll.</span></div>
      <span class="ix-tag">Performance</span>
    </div>
    <label class="f" for="user-target">Monthly collection target (₹) <span class="hint">— 0 means no target</span></label>
    <input class="in" id="user-target" type="number" step="0.01" min="0" name="monthly_target" value="{{ old('monthly_target',$user->monthly_target ?? 0) }}">
    <label class="f" for="user-commission">Commission on collections (%) <span class="hint">— 0 means none</span></label>
    <input class="in" id="user-commission" type="number" step="0.01" min="0" max="100" name="commission_rate" value="{{ old('commission_rate',$user->commission_rate ?? 0) }}">
    <p class="fhint st-note">Targets appear on the dashboard forecast; commission is calculated on receipts collected by this person.</p>

    <div class="fsec">Employee profile</div>
    <div class="form-grid">
      <div><label class="f" for="user-designation">Designation</label><input class="in" id="user-designation" name="designation" value="{{ old('designation',$user->designation) }}" placeholder="e.g. Site electrician"></div>
      <div><label class="f" for="user-department">Department</label><input class="in" id="user-department" name="department" value="{{ old('department',$user->department) }}" placeholder="e.g. Execution"></div>
      <div><label class="f" for="user-phone">Phone</label><input class="in" id="user-phone" name="phone" value="{{ old('phone',$user->phone) }}"></div>
      <div><label class="f" for="user-joining">Joining date</label><input class="in" id="user-joining" type="date" name="joining_date" value="{{ old('joining_date', $user->joining_date ? d($user->joining_date,'Y-m-d') : '') }}"></div>
      <div><label class="f" for="user-capacity">Weekly capacity (hrs)</label><input class="in" id="user-capacity" type="number" min="1" max="168" name="capacity_hours" value="{{ old('capacity_hours', $user->capacity_hours) }}" placeholder="Blank = team default"></div>
    </div>
    <label class="f" for="user-address">Address</label>
    <input class="in" id="user-address" name="address" value="{{ old('address',$user->address) }}">
  </div>
</div>

<div class="card ix-card" id="perm-matrix">
  <div class="card-head ix-card-head">
    <div><h3>Module access</h3><span class="hint">Inherit keeps the role default (full workspace). View only blocks writes with a 403. No access hides the section from the menu.</span></div>
    <span class="ix-tag">Permissions</span>
  </div>
  <div class="ix-table-scroll">
    <table class="tbl ix-table um-table">
      <thead><tr><th class="ix-c-main">Module</th><th class="ix-c-access">Access</th></tr></thead>
      <tbody>
        @foreach(perm_modules() as $key => $label)
          @php $cur = is_array($perms) ? ($perms[$key] ?? 'inherit') : 'inherit'; @endphp
          <tr>
            <td class="ix-c-main"><span class="ix-name">{{ $label }}</span><span class="ix-sub">{{ $key }}</span></td>
            <td class="ix-c-access">
              <select class="in" name="permissions[{{ $key }}]" aria-label="Access for {{ $label }}">
                <option value="inherit" {{ $cur==='inherit'?'selected':'' }}>Inherit role default</option>
                <option value="full" {{ $cur==='full'?'selected':'' }}>Full</option>
                <option value="view" {{ $cur==='view'?'selected':'' }}>View only</option>
                <option value="none" {{ $cur==='none'?'selected':'' }}>No access</option>
              </select>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <p class="fhint st-note">Invoice and proposal ownership (assigned or created) still applies on top of these settings.</p>
</div>

<div class="card ix-card st-card">
  <div class="st-actions">
    <div class="st-actions-txt">
      <strong>{{ $user->exists ? 'Save user changes' : 'Create user' }}</strong>
      <span>{{ $user->exists ? 'Identity, access, targets and employee profile.' : 'The person can sign in immediately once saved.' }}</span>
    </div>
    <div class="st-actions-btns">
      <a class="btn sec" href="{{ route('settings.users.index') }}">Cancel</a>
      <button class="btn" type="submit">{{ $user->exists ? 'Save user' : 'Create user' }}</button>
    </div>
  </div>
</div>
</form>

<script>
(function(){
  var role = document.getElementById('role-select'), mx = document.getElementById('perm-matrix');
  if (!role || !mx) return;
  function sync(){ mx.style.display = role.value === 'admin' ? 'none' : ''; }
  role.addEventListener('change', sync); sync();
})();
</script>
@endsection
