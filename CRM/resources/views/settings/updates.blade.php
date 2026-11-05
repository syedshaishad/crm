@extends('layouts.app')
@section('title','Updates')
@section('content')
@php
  $preflight = collect($preflight ?? []);
  $blocked = $preflight->filter(fn ($c) => empty($c['ok']) && ! empty($c['fatal']))->count();
  $warnings = $preflight->filter(fn ($c) => empty($c['ok']) && empty($c['fatal']))->count();
  $ready = $blocked > 0 ? 'Blocked' : ($warnings > 0 ? 'Warnings' : 'Ready');
  $readyTone = $blocked > 0 ? 'is-red' : ($warnings > 0 ? 'is-amber' : 'is-green');
  $backupCount = count($backups ?? []);
  $latestBackup = $backups[0] ?? null;
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v3h16v-3"/></svg>
    </span>
    <div>
      <h1>Updates</h1>
      <div class="crumb">Installed version v{{ $current }} · backups and server readiness</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Settings
    </a>
    <a class="btn sm" href="{{ route('settings.updates.backup') }}">Download full backup</a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Current version</span>
    <b class="ix-metric-val">v{{ $current }}</b>
    <span class="ix-metric-sub">installed on this server</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Backups</span>
    <b class="ix-metric-val">{{ $backupCount }}</b>
    <span class="ix-metric-sub">kept on disk</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Latest backup</span>
    <b class="ix-metric-val is-money">{{ $latestBackup ? d(date('Y-m-d H:i:s', $latestBackup['at']),'j M, H:i') : '—' }}</b>
    <span class="ix-metric-sub">{{ $latestBackup ? fmt_size($latestBackup['size']) : 'none taken yet' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Readiness</span>
    <b class="ix-metric-val"><span class="ix-state {{ $readyTone }}"><i></i>{{ $ready }}</span></b>
    <span class="ix-metric-sub">{{ $blocked > 0 ? $blocked.' blocking '.Str::plural('check', $blocked) : ($warnings > 0 ? $warnings.' '.Str::plural('warning', $warnings) : 'safe to apply packages') }}</span>
  </div>
</div>

<div class="st-grid">
  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Upload &amp; apply package</h3><span class="hint">A database and file backup is taken before anything changes.</span></div>
      <span class="ix-tag">Update</span>
    </div>
    <p class="fhint">Upload a <code class="st-code">.zip</code> containing <code class="st-code">update.php</code> (returning <code class="st-code">['version' =&gt; 'x.y.z']</code>) plus optional <code class="st-code">files/</code> and <code class="st-code">migrations/</code> at the zip root.</p>
    <form method="POST" action="{{ route('settings.updates.apply') }}" enctype="multipart/form-data">@csrf
      <label class="f" for="upd-package">Package file</label>
      <input class="in" id="upd-package" type="file" name="package_file" accept=".zip">
      <div class="st-actions-inline">
        <button class="btn" type="submit">Upload &amp; apply with backup</button>
      </div>
    </form>
    <p class="fhint st-note">After applying, if the version number or features look unchanged: reload PHP-FPM (CloudPanel → PHP → Reload), purge any CDN cache (e.g. Cloudflare → Caching → Purge Everything), then hard-refresh (Ctrl+Shift+R). Production OPcache/CDN can keep serving the old code for a while.</p>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Email me backups</h3><span class="hint">Nightly zip straight to your inbox.</span></div>
      <span class="ix-state {{ setting('backups.email_enabled','0')==='1' ? 'is-green' : 'is-grey' }}"><i></i>{{ setting('backups.email_enabled','0')==='1' ? 'Sending' : 'Off' }}</span>
    </div>
    <form method="POST" action="{{ route('settings.updates.backupmail') }}">@csrf
      <div class="st-checks is-one">
        <label class="st-check">
          <input type="checkbox" name="backups_email_enabled" value="1" {{ setting('backups.email_enabled','0')==='1'?'checked':'' }}>
          <span><b>Email each nightly backup</b><br>Zips over 20 MB are skipped by mail — they stay downloadable here.</span>
        </label>
      </div>
      <label class="f" for="upd-email">Send to</label>
      <input class="in" id="upd-email" type="email" name="backups_email_to" value="{{ setting('backups.email_to') }}" placeholder="owner@example.com">
      <div class="st-actions-inline">
        <button class="btn sec sm" type="submit">Save email backups</button>
      </div>
    </form>
  </div>
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Backups</h3><span class="hint">Database and all uploaded files as one zip. Nightly auto-backup at 2 AM keeps the last {{ setting('backups.retain_count', 3) }} (maximum 3) — older backups are deleted automatically.</span></div>
    <span class="ix-tag">{{ $backupCount }} on disk</span>
  </div>
  <div class="ix-table-scroll">
    <table class="tbl ix-table upd-table">
      <thead><tr>
        <th class="ix-c-main">Backup</th>
        <th class="ix-c-when">Taken</th>
        <th class="ix-c-size">Size</th>
        <th class="ix-c-actions"></th>
      </tr></thead>
      <tbody>
        @forelse($backups as $b)
          <tr>
            <td class="ix-c-main"><span class="ix-name"><code class="st-code">{{ $b['file'] }}</code></span></td>
            <td class="ix-c-when nowrap">{{ d(date('Y-m-d H:i:s', $b['at'])) }}</td>
            <td class="ix-c-size nowrap">{{ fmt_size($b['size']) }}</td>
            <td class="ix-c-actions">
              <div class="ix-actions">
                <a class="ix-act" href="{{ route('settings.updates.backup.download',$b['file']) }}" title="Download {{ $b['file'] }}" aria-label="Download {{ $b['file'] }}">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v3h16v-3"/></svg>
                </a>
                <form method="POST" action="{{ route('settings.updates.backup.destroy',$b['file']) }}" data-confirm="Delete this backup?">@csrf @method('DELETE')
                  <button class="ix-act is-danger" title="Delete {{ $b['file'] }}" aria-label="Delete {{ $b['file'] }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="4">
            <div class="ix-empty">
              <span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/></svg></span>
              <strong>No backups yet</strong>
              <p>Download one above or wait for tonight's run.</p>
            </div>
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <p class="fhint st-note">Runs on site traffic, no server cron needed. VPS owners may still add <code class="st-code">* * * * * php artisan schedule:run</code>.</p>
</div>

<div class="st-grid">
  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Server readiness</h3><span class="hint">Checked live on this page load.</span></div>
      <span class="ix-state {{ $readyTone }}"><i></i>{{ $ready }}</span>
    </div>
    <div class="ix-table-scroll">
      <table class="tbl ix-table upd-ready-table">
        <thead><tr><th class="ix-c-main">Check</th><th class="ix-c-status">Status</th><th class="ix-c-detail">Detail</th></tr></thead>
        <tbody>
          @forelse($preflight as $c)
            <tr>
              <td class="ix-c-main"><span class="ix-name">{{ $c['label'] }}</span></td>
              <td class="ix-c-status"><span class="ix-state {{ $c['ok'] ? 'is-green' : ($c['fatal'] ? 'is-red' : 'is-amber') }}"><i></i>{{ $c['ok'] ? 'OK' : ($c['fatal'] ? 'Blocked' : 'Warning') }}</span></td>
              <td class="ix-c-detail"><span class="ix-sub">{{ $c['detail'] }}</span></td>
            </tr>
          @empty
            <tr><td colspan="3"><div class="ix-empty"><strong>Readiness unavailable</strong><p>The server check could not run on this request.</p></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Packages on disk</h3><span class="hint">Re-apply a package already uploaded to the server.</span></div>
      <span class="ix-tag">{{ count($packages ?? []) }}</span>
    </div>
    @forelse($packages ?? [] as $p)
      <div class="st-item">
        <span class="st-item-main"><b><code class="st-code">{{ $p }}</code></b><span>Ready to apply with a fresh backup.</span></span>
        <span class="st-item-actions">
          <form method="POST" action="{{ route('settings.updates.apply') }}">@csrf
            <input type="hidden" name="package" value="{{ $p }}">
            <button class="btn sec sm">Apply with backup</button>
          </form>
        </span>
      </div>
    @empty
      <div class="ix-empty">
        <span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8"/><path d="M1 3h22v5H1zM10 12h4"/></svg></span>
        <strong>No packages found</strong>
        <p>Upload a package above to see it listed here.</p>
      </div>
    @endforelse
  </div>
</div>
@endsection
