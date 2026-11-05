@extends('layouts.app')
@section('title','License')
@section('content')
@php
  $active = $license['active'] ?? false;
  $plan = $license['license_type'] ?? $license['plan'] ?? '—';
  $status = $license['status'] ?? 'unknown';
  $expires = $license['expires_at'] ?? null;
  $grace = $license['grace_until'] ?? null;
  $daysLeft = $expires ? (int) now()->diffInDays($expires, false) : null;
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
    </span>
    <div>
      <h1>License</h1>
      <div class="crumb">Validated against {{ $server }} · product {{ $product }}</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Settings
    </a>
    @if($hasKey)
      <form method="POST" action="{{ route('settings.license.refresh') }}">@csrf<button class="btn sm" type="submit">Check now</button></form>
    @endif
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Plan</span>
    <b class="ix-metric-val">{{ $plan }}</b>
    <span class="ix-metric-sub">status: {{ $status }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Expires</span>
    <b class="ix-metric-val is-money">{{ $expires ? d($expires) : '—' }}</b>
    <span class="ix-metric-sub">{{ $expires ? ($daysLeft >= 0 ? $daysLeft.' days left' : abs($daysLeft).' days ago') : 'managed by the server' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Offline grace</span>
    <b class="ix-metric-val is-money">{{ $grace ? d($grace, 'j M, H:i') : '—' }}</b>
    <span class="ix-metric-sub">{{ !empty($license['stale']) ? 'server unreachable — re-validating' : 'server-signed certificate' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Installation</span>
    <b class="ix-metric-val st-metric-code">{{ $installation }}</b>
    <span class="ix-metric-sub">{{ $domain }}</span>
  </div>
</div>

<div class="st-grid">
  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Activation</h3><span class="hint">The key is registered against this installation on the license server.</span></div>
      <span class="ix-state {{ $active ? 'is-green' : 'is-red' }}"><i></i>{{ $active ? 'Active' : 'Blocked' }}</span>
    </div>

    @if(!empty($license['reason']))
      <div class="err">{{ $license['reason'] }}</div>
    @endif
    @if(!empty($license['request_id']))
      <p class="hint">Server reference <code class="st-code">{{ $license['request_id'] }}</code> — quote it to support.</p>
    @endif

    @if(!$hasKey)
      <p class="fhint">Paste the key your reseller issued. It is registered against this installation on the license server — the app unlocks immediately.</p>
      <form method="POST" action="{{ route('settings.license.activate') }}">@csrf
        <label class="f" for="lic-key">License key</label>
        <input class="in st-metric-code" id="lic-key" name="license_key" required maxlength="200" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" autocomplete="off">
        <div class="st-actions-inline">
          <button class="btn" type="submit">Verify &amp; activate</button>
        </div>
      </form>
    @else
      <p class="hint">Key on file{{ $keyFromEnv ? ' (from LICENSE_KEY in .env)' : '' }} · server <code class="st-code">{{ $server }}</code> · re-checks automatically (hourly) and on demand.</p>
      <div class="st-actions-inline">
        <form method="POST" action="{{ route('settings.license.refresh') }}">@csrf<button class="btn sec sm" type="submit">Check now</button></form>
        <form method="POST" action="{{ route('settings.license.deactivate') }}" data-confirm="Release this installation's activation slot? The app locks until a key is activated again." data-confirm-tone="danger" data-confirm-title="Release activation?" data-confirm-ok="Release">@csrf<button class="btn sec sm" type="submit">Release activation…</button></form>
      </div>
      <p class="fhint st-note">Deleting local license state forces an immediate re-validation — it never grants access.</p>
    @endif
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>How enforcement works</h3><span class="hint">What happens when the server is unreachable.</span></div>
      <span class="ix-tag">Security</span>
    </div>
    <p class="hint st-note">Every install reports its key, domain, installation id and version to the license server and obeys the answer. Successful checks return a signed certificate that covers a server-decided offline window; while it verifies, the app runs without network.</p>
    <p class="hint st-note">When it lapses and the server is unreachable, the app blocks — it never fails open. Report a lost key or a moved server to your reseller (quote the installation id above).</p>
  </div>
</div>
@endsection
