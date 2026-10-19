@extends('layouts.app')
@section('title','License')
@section('content')
<div class="card"><div class="card-head"><div><h3>License</h3><span class="hint">Validated against {{ $server }} · product {{ $product }}</span></div>@if($license['active'] ?? false)<span class="badge b-green">Active</span>@else <span class="badge b-red">Blocked</span>@endif</div>
<div class="grid stats cols-4" style="margin-bottom:14px">
<div class="stat acc" style="--acc:#0a84ff"><div class="lbl">Plan</div><b style="font-size:18px">{{ $license['license_type'] ?? $license['plan'] ?? '—' }}</b><div class="sub">status: {{ $license['status'] ?? 'unknown' }}</div></div>
<div class="stat acc" style="--acc:#bf5af2"><div class="lbl">Expires</div><b style="font-size:18px">{{ !empty($license['expires_at']) ? d($license['expires_at']) : '—' }}</b><div class="sub">@if(!empty($license['expires_at'])){{ (int) now()->diffInDays($license['expires_at'], false) }} days left @else managed by server @endif</div></div>
<div class="stat acc" style="--acc:#30d158"><div class="lbl">Offline grace</div><b style="font-size:18px">@if(!empty($license['grace_until'])){{ d($license['grace_until'], 'j M, H:i') }}@else — @endif</b><div class="sub">@if(!empty($license['stale']))re-validating on schedule — server unreachable @else server-signed certificate @endif</div></div>
<div class="stat acc" style="--acc:#ff9f0a"><div class="lbl">Installation</div><b style="font-size:13px;word-break:break-all">{{ $installation }}</b><div class="sub">{{ $domain }}</div></div>
</div>
@if(!empty($license['reason']))<div class="err">{{ $license['reason'] }}</div>@endif
@if(!empty($license['request_id']))<p class="hint">Server reference <code>{{ $license['request_id'] }}</code> — quote it to support.</p>@endif
@if(!$hasKey)
<div class="card" style="max-width:720px;margin-bottom:14px"><h3>Activate with a license key</h3>
<p class="fhint">Paste the key your reseller issued. It is registered against this installation on the license server — the app unlocks immediately.</p>
<form method="POST" action="{{ route('settings.license.activate') }}">@csrf
<label class="f">License key</label><input class="in" name="license_key" required maxlength="200" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" autocomplete="off" style="font-family:monospace">
<div class="form-actions"><button class="btn" type="submit">Verify & activate</button></div></form></div>
@else
<p class="hint">Key on file{{ $keyFromEnv ? ' (from <code>LICENSE_KEY</code> in .env)' : '' }} · server <code>{{ $server }}</code> · re-checks automatically (hourly) and on demand below. Deleting local license state forces an immediate re-validation — it never grants access.</p>
<div class="btnrow" style="margin-bottom:14px">
<form method="POST" action="{{ route('settings.license.refresh') }}">@csrf<button class="btn sec sm" type="submit">Check now</button></form>
<form method="POST" action="{{ route('settings.license.deactivate') }}" data-confirm="Release this installation's activation slot? The app locks until a key is activated again." data-confirm-tone="danger" data-confirm-title="Release activation?" data-confirm-ok="Release">@csrf<button class="btn sec sm" type="submit">Release activation…</button></form>
</div>
@endif
<details class="adv"><summary>How enforcement works</summary><p class="hint">Every install reports its key, domain, installation id and version to the license server and obeys the answer. Successful checks return a signed certificate that covers a server-decided offline window; while it verifies, the app runs without network. When it lapses and the server is unreachable, the app blocks — it never fails open. Report a lost key or a moved server to your reseller (quote the installation id above).</p></details>
</div>
@endsection
