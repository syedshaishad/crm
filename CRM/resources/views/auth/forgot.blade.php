<!DOCTYPE html>
<html lang="en">
<head>
@include('auth._head', ['pageTitle' => 'Reset password'])
</head>
<body style="background:var(--bg)"><div class="auth"><div class="auth-card">
<div class="auth-side"><h1>Reset your password</h1><p>We will email you a secure reset link.</p></div>
<div class="auth-form"><div class="auth-box"><h2>Forgot password</h2>
@if(session('ok'))<div class="alert">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.email') }}">@csrf
<label class="f">Email</label><input class="in" type="email" name="email" required>
<button class="btn brand" style="width:100%;justify-content:center">Send reset link</button>
<p class="hint" style="margin-top:12px"><a href="/login">← Back to sign in</a></p></form></div></div>
</div></div>
@include('auth._theme')
</body>
</html>
