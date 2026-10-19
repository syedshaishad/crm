<!DOCTYPE html>
<html lang="en">
<head>
@include('auth._head', ['pageTitle' => 'Two-factor check'])
</head>
<body style="background:var(--bg)">
<div class="auth"><div class="auth-card">
  <div class="auth-side">
    @if(!empty($brand['logo_dark_path'] ?? $brand['logo_path'] ?? ''))
      @include('brand.logo', ['fixed' => $brand['logo_dark_path'] ?? $brand['logo_path']])
    @endif
    <h1>Almost in</h1>
    <p>Enter the 6-digit code from your authenticator app. Lost your phone? Use one of your recovery codes instead.</p>
  </div>
  <div class="auth-form"><div class="auth-box">
    <h2>Two-factor check</h2>
    <p class="sub">{{ $brand['company_name'] ?? config('app.name') }}</p>
    @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('2fa.verify') }}">@csrf
      <label class="f" for="code">6-digit code</label>
      <input class="in" id="code" name="code" required autofocus autocomplete="one-time-code" inputmode="numeric" placeholder="123456">
      <button class="btn brand" style="width:100%;justify-content:center">Verify & sign in</button>
    </form>
    <p class="hint" style="margin-top:12px"><a href="/login">Back to sign in</a></p>
  </div></div>
</div></div>
@include('auth._theme')
</body>
</html>
