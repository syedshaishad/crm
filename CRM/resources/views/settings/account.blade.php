@extends('layouts.app')
@section('title','My account')
@section('content')
@php
  $hasAvatar = (bool) $user->avatar_path;
  $twofa = (bool) $user->twofa_enabled;
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.9-3.8 3.7-5.5 7-5.5s6.1 1.7 7 5.5"/></svg>
    </span>
    <div>
      <h1>My account</h1>
      <div class="crumb">{{ $user->name }} · {{ $user->role }}</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Settings
    </a>
    <button class="btn sm" type="submit" form="account-form">Save changes</button>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Role</span>
    <b class="ix-metric-val">{{ ucfirst($user->role) }}</b>
    <span class="ix-metric-sub">{{ $user->role === 'admin' ? 'full workspace and settings access' : 'workspace access' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Two-factor</span>
    <b class="ix-metric-val">{{ $twofa ? 'On' : 'Off' }}</b>
    <span class="ix-metric-sub">{{ $twofa ? 'authenticator code required' : 'add a second sign-in step' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Profile picture</span>
    <b class="ix-metric-val">{{ $hasAvatar ? 'Uploaded' : 'Initials' }}</b>
    <span class="ix-metric-sub">{{ $hasAvatar ? 'shown across the app' : 'upload one below' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Member since</span>
    <b class="ix-metric-val is-money">{{ $user->created_at ? d($user->created_at,'M Y') : '—' }}</b>
    <span class="ix-metric-sub">{{ $user->email }}</span>
  </div>
</div>

<form id="account-form" method="POST" action="{{ route('settings.account.update') }}" enctype="multipart/form-data">@csrf @method('PUT')
<div class="st-grid">
  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Profile</h3><span class="hint">Your photo appears in the top bar, team list and push notifications.</span></div>
      <span class="ix-tag">Identity</span>
    </div>
    <div class="st-profile">
      <div class="st-profile-av">@include('partials.avatar', ['user' => $user, 'class' => 'av lg'])</div>
      <div class="st-profile-fields">
        <label class="f" for="acc-avatar">Profile picture <span class="hint">— JPG, PNG, GIF or WebP, up to 4 MB</span></label>
        <input class="in" id="acc-avatar" type="file" name="avatar" accept="image/*">
        @if($user->avatar_path)
          <label class="st-check is-plain">
            <input type="checkbox" name="remove_avatar" value="1">
            <span>Remove current picture</span>
          </label>
        @endif
      </div>
    </div>
    <label class="f" for="acc-name">Name</label>
    <input class="in" id="acc-name" name="name" value="{{ old('name',$user->name) }}" required>
    <label class="f" for="acc-email">Email</label>
    <input class="in" id="acc-email" type="email" name="email" value="{{ old('email',$user->email) }}" required>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Change password</h3><span class="hint">Leave blank to keep your current password.</span></div>
      <span class="ix-tag">Security</span>
    </div>
    <label class="f" for="acc-current">Current password</label>
    <input class="in" id="acc-current" type="password" name="current_password" autocomplete="current-password">
    <label class="f" for="acc-password">New password (min 8)</label>
    <input class="in" id="acc-password" type="password" name="password" autocomplete="new-password">
    <label class="f" for="acc-password-confirm">Confirm new password</label>
    <input class="in" id="acc-password-confirm" type="password" name="password_confirmation" autocomplete="new-password">
    <p class="fhint st-note">Changing your password signs you out of other devices.</p>
  </div>
</div>

<div class="card ix-card st-card">
  <div class="st-actions">
    <div class="st-actions-txt">
      <strong>Save account changes</strong>
      <span>Profile details and password.</span>
    </div>
    <div class="st-actions-btns">
      <a class="btn sec" href="{{ route('settings.index') }}">Cancel</a>
      <button class="btn" type="submit">Save changes</button>
    </div>
  </div>
</div>
</form>

<div class="st-grid">
  <div class="card ix-card" data-push-settings data-csrf="{{ csrf_token() }}" data-public-key-url="{{ route('push.publicKey') }}" data-subscribe-url="{{ route('push.subscribe') }}" data-unsubscribe-url="{{ route('push.unsubscribe') }}" data-test-url="{{ route('push.test') }}" data-sw-url="{{ asset('sw.js') }}">
    <div class="card-head ix-card-head">
      <div><h3>Push notifications</h3><span class="hint">Get task, payment and invoice alerts on this device, even when the CRM is closed. Your profile picture is used as the notification icon.</span></div>
      <span class="ix-tag">Device</span>
    </div>
    <p class="hint st-note" data-push-status role="status">Checking this device…</p>
    <div class="st-actions-inline">
      <button type="button" class="btn sm" data-push-enable>Enable on this device</button>
      <button type="button" class="btn sec sm" data-push-test style="display:none">Send test</button>
      <button type="button" class="btn sec sm" data-push-disable style="display:none">Turn off</button>
    </div>
    <p class="fhint">Needs HTTPS (or localhost). The notification sound follows your device settings; an in-app chime plays while the CRM is open. On iPhone, add the CRM to your Home Screen first.</p>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Two-factor authentication</h3><span class="hint">Works with Google/Microsoft Authenticator, 1Password and similar apps.</span></div>
      <span class="ix-state {{ $twofa ? 'is-green' : 'is-grey' }}"><i></i>{{ $twofa ? 'On' : 'Off' }}</span>
    </div>
    @if($user->twofa_enabled)
      <p class="fhint">On — signing in asks for a code from your authenticator app.</p>
      <form method="POST" action="{{ route('settings.account.2fa.disable') }}">@csrf
        <label class="f" for="acc-2fa-pass">Current password (to switch it off)</label>
        <input class="in" id="acc-2fa-pass" type="password" name="password" required autocomplete="current-password">
        <div class="st-actions-inline"><button class="btn sec">Switch off 2FA</button></div>
      </form>
    @elseif(session('twofa:setup_secret'))
      <p class="fhint">Scan is not needed — open your authenticator app, add a key manually, and type this secret:</p>
      <pre class="st-secret">{{ chunk_split(session('twofa:setup_secret'), 4, ' ') }}</pre>
      <form method="POST" action="{{ route('settings.account.2fa.confirm') }}">@csrf
        <label class="f" for="acc-2fa-code">6-digit code from the app</label>
        <input class="in" id="acc-2fa-code" name="code" required inputmode="numeric" placeholder="123456" autocomplete="off">
        <div class="st-actions-inline"><button class="btn">Confirm &amp; switch on</button></div>
      </form>
    @else
      @if(session('twofa:codes'))
        <div class="alert"><b>Save these recovery codes somewhere safe — each works once:</b><br><code>{{ implode(', ', session('twofa:codes')) }}</code></div>
      @endif
      <p class="fhint">Off — add a second step to signing in.</p>
      <form method="POST" action="{{ route('settings.account.2fa.setup') }}">@csrf
        <div class="st-actions-inline"><button class="btn sec">Set up 2FA</button></div>
      </form>
    @endif
  </div>
</div>
@endsection
