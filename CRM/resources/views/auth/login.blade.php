<!DOCTYPE html>
<html lang="en">
<head>
@include('auth._head', ['pageTitle' => 'Sign in'])
</head>
<body style="background:var(--bg)">
<div class="auth"><div class="auth-card">
  <div class="auth-side">
    @if(!empty($brand['logo_dark_path'] ?? $brand['logo_path'] ?? ''))
      @include('brand.logo', ['fixed' => $brand['logo_dark_path'] ?? $brand['logo_path']])
    @endif
    <h1>{{ $brand['login_heading'] ?: ('Welcome to '.($brand['company_name'] ?? 'your workspace')) }}</h1>
    <p>{{ $brand['login_subtext'] ?: ($brand['tagline'] ?? 'Clients, projects, invoices, payments and reports in one self-hosted app.') }}</p>
  </div>
  <div class="auth-form"><div class="auth-box">
    <h2>Sign in</h2>
    <p class="sub">{{ $brand['company_name'] ?? config('app.name') }} · v{{ app_version() }}</p>
    @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    <form method="POST" action="/login">@csrf
      <label class="f" for="email">Email</label>
      <input class="in" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
      <label class="f" for="password">Password</label>
      <input class="in" id="password" type="password" name="password" required>
      <label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:16px"><input type="checkbox" name="remember" value="1" style="width:auto;margin:0"> Remember me</label>
      <button class="btn brand" style="width:100%;justify-content:center">Sign in</button>
    </form>
    <p class="hint" style="margin-top:12px"><a href="{{ route('password.request') }}">Forgot password?</a> · <a href="{{ route('portal.login') }}">Client login →</a></p>
  </div></div>
</div></div>
@include('auth._theme')
</body></html>
