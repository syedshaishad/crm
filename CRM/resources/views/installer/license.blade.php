@extends('installer.layout')
@section('step','7')
@section('content')
<h1>7 — License activation</h1>
<p>This copy only runs with a valid license. Paste the key your reseller issued — it is registered against this installation on the license server right now.</p>
<form method="POST" action="{{ route('installer.license.store') }}">@csrf
<label>License key</label><input name="license_key" required maxlength="200" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" autocomplete="off" style="font-family:monospace">
@if(empty($apiConfigured))
<label>Reseller API key <span class="hint">(from your reseller — shipped in .env when pre-provisioned)</span></label><input name="api_key" maxlength="100" autocomplete="off" style="font-family:monospace">
<label>Reseller API secret</label><input name="api_secret" type="password" maxlength="200" autocomplete="off">
@else
<p class="hint">Reseller API credentials are already configured on this install.</p>
@endif
<div class="btnrow"><button class="btn">Verify & activate →</button><a class="btn sec" href="{{ route('installer.integrations') }}">← Back</a></div></form>
<p class="hint">No key yet? Ask your reseller. Without activation the installer cannot finish and the app stays locked.</p>
@endsection
