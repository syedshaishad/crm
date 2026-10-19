@extends('layouts.app')
@section('title','My account')
@section('content')
<div class="card" style="max-width:640px"><h3>My account ({{ $user->role }})</h3>
<form method="POST" action="{{ route('settings.account.update') }}">@csrf @method('PUT')
<label class="f">Name</label><input class="in" name="name" value="{{ old('name',$user->name) }}" required>
<label class="f">Email</label><input class="in" type="email" name="email" value="{{ old('email',$user->email) }}" required>
<hr><h3>Change password</h3>
<label class="f">Current password</label><input class="in" type="password" name="current_password">
<label class="f">New password (min 8)</label><input class="in" type="password" name="password">
<label class="f">Confirm new password</label><input class="in" type="password" name="password_confirmation">
<div class="form-actions"><button class="btn" type="submit">Save changes</button><a class="btn sec" href="{{ route('settings.index') }}">Cancel</a></div></form></div>
<div class="card" style="max-width:640px"><h3>Two-factor authentication</h3>
@if($user->twofa_enabled)
<p class="fhint">On — signing in asks for a code from your authenticator app.</p>
<form method="POST" action="{{ route('settings.account.2fa.disable') }}">@csrf
<label class="f">Current password (to switch it off)</label><input class="in" type="password" name="password" required>
<button class="btn sec">Switch off 2FA</button></form>
@elseif(session('twofa:setup_secret'))
<p class="fhint">Scan is not needed — open your authenticator app, add a key manually, and type this secret:</p>
<pre style="font-size:18px;letter-spacing:.1em;text-align:center">{{ chunk_split(session('twofa:setup_secret'), 4, ' ') }}</pre>
<form method="POST" action="{{ route('settings.account.2fa.confirm') }}">@csrf
<label class="f">6-digit code from the app</label><input class="in" name="code" required inputmode="numeric" placeholder="123456" autocomplete="off">
<button class="btn">Confirm & switch on</button></form>
@else
@if(session('twofa:codes'))
<div class="alert"><b>Save these recovery codes somewhere safe — each works once:</b><br><code>{{ implode(', ', session('twofa:codes')) }}</code></div>
@endif
<p class="fhint">Off — add a second step to signing in. Works with Google/Microsoft Authenticator, 1Password, etc.</p>
<form method="POST" action="{{ route('settings.account.2fa.setup') }}">@csrf<button class="btn sec">Set up 2FA</button></form>
@endif</div>
@endsection
