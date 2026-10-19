<!DOCTYPE html>
<html lang="en">
<head>
@include('auth._head', ['pageTitle' => 'Set new password'])
</head>
<body style="background:var(--bg)"><div class="auth"><div class="auth-card">
<div class="auth-side"><h1>Choose a new password</h1><p>Minimum 8 characters.</p></div>
<div class="auth-form"><div class="auth-box"><h2>Reset password</h2>
@if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.update') }}">@csrf
<input type="hidden" name="token" value="{{ $token }}">
<label class="f">Email</label><input class="in" type="email" name="email" value="{{ old('email',$email) }}" required>
<label class="f">New password</label><input class="in" type="password" name="password" required>
<label class="f">Confirm password</label><input class="in" type="password" name="password_confirmation" required>
<button class="btn brand" style="width:100%;justify-content:center">Set new password</button></form></div></div>
</div></div>
@include('auth._theme')
</body>
</html>
