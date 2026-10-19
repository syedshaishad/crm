@extends('layouts.app')
@section('title','Updates')
@section('content')
<div class="card"><h3>Updates — v{{ $current }}</h3>
<p class="hint">Upload a <code>.zip</code> containing <code>update.php</code> (returning <code>['version' => 'x.y.z']</code>) plus optional <code>files/</code> and <code>migrations/</code> at the zip root. A DB + file backup is taken first.</p>
<p class="hint">After a successful apply, if the version number or features look unchanged: reload PHP-FPM (CloudPanel → PHP → Reload), purge any CDN cache (e.g. Cloudflare → Caching → Purge Everything), then hard-refresh (Ctrl+Shift+R). Production OPcache/CDN can keep serving the old code for a while.</p>
<form method="POST" action="{{ route('settings.updates.apply') }}" enctype="multipart/form-data">@csrf
<label class="f">Upload & apply package</label><input class="in" type="file" name="package_file" accept=".zip">
<button class="btn">Upload & apply with backup</button></form>
<hr><div class="card-head" style="margin-bottom:8px"><h3>Backups</h3><a class="btn sec sm" href="{{ route('settings.updates.backup') }}">Download full backup</a></div>
<p class="hint" style="margin-top:0">Database + all uploaded files as one zip. Nightly auto-backup at 2 AM keeps the last {{ setting('backups.retain_count', 7) }} — runs on site traffic, no server cron needed (VPS owners may still add <code>* * * * * php artisan schedule:run</code>).</p>
<div class="tbl-scroll"><table class="tbl"><tr><th>Backup</th><th>Taken</th><th style="text-align:right">Size</th><th></th></tr>
@forelse($backups as $b)<tr><td class="nowrap"><code>{{ $b['file'] }}</code></td><td class="nowrap">{{ d(date('Y-m-d H:i:s', $b['at'])) }}</td><td class="nowrap" style="text-align:right">{{ fmt_size($b['size']) }}</td>
<td><div class="row-actions"><a class="icon-btn" href="{{ route('settings.updates.backup.download',$b['file']) }}">Download</a>
<form method="POST" action="{{ route('settings.updates.backup.destroy',$b['file']) }}" style="display:inline" data-confirm="Delete this backup?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form></div></td></tr>
@empty<tr><td colspan="4"><div class="empty">No backups yet — download one above or wait for tonight's run.</div></td></tr>@endforelse</table></div>
<hr><div class="card-head" style="margin-bottom:8px"><h3>Email me backups</h3><span class="hint">nightly zip in your inbox</span></div>
<form method="POST" action="{{ route('settings.updates.backupmail') }}">@csrf
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:8px"><input type="checkbox" name="backups_email_enabled" value="1" {{ setting('backups.email_enabled','0')==='1'?'checked':'' }} style="width:auto;margin:0"> Email each nightly backup</label>
<label class="f">Send to</label><input class="in" type="email" name="backups_email_to" value="{{ setting('backups.email_to') }}" placeholder="owner@example.com" style="max-width:320px">
<p class="hint">Zips over 20 MB are skipped by mail (most inboxes refuse them) — they stay downloadable here.</p>
<div class="form-actions"><button class="btn sec sm" type="submit">Save email backups</button></div></form>
<hr><div class="card-head" style="margin-bottom:8px"><h3>Server readiness</h3><span class="hint">checked live on this page load</span></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Check</th><th>Status</th><th>Detail</th></tr>
@forelse($preflight ?? [] as $c)<tr><td>{{ $c['label'] }}</td><td><span class="badge {{ $c['ok'] ? 'b-green' : ($c['fatal'] ? 'b-red' : 'b-amber') }}">{{ $c['ok'] ? 'OK' : ($c['fatal'] ? 'BLOCKED' : 'WARN') }}</span></td><td class="hint">{{ $c['detail'] }}</td></tr>@empty<tr><td colspan="3"><div class="empty">Readiness unavailable.</div></td></tr>@endforelse</table></div>
<hr><ul>@forelse($packages as $p)<li><code>{{ $p }}</code><form method="POST" action="{{ route('settings.updates.apply') }}" style="display:inline">@csrf<input type="hidden" name="package" value="{{ $p }}"><button class="btn sec sm">Apply with backup</button></form></li>@empty<li>No packages found.</li>@endforelse</ul>
</div>
@endsection
