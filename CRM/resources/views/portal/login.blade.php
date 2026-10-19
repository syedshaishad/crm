@extends('portal.layout')
@section('title','Client sign in')
@section('content')
<div class="card" style="max-width:440px;margin:6vh auto">
  <div class="card-head"><h3>Client sign in</h3></div>
  <p class="hint" style="margin-top:0">View your invoices and proposals, sign and pay online.</p>
  <form method="POST" action="{{ route('portal.login') }}">@csrf
    <label class="f" for="email">Email</label>
    <input class="in" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
    <label class="f" for="password">Password</label>
    <input class="in" id="password" type="password" name="password" required>
    <label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:16px"><input type="checkbox" name="remember" value="1" style="width:auto;margin:0"> Remember me</label>
    <button class="btn brand" style="width:100%;justify-content:center">Sign in</button>
  </form>
  <p class="hint" style="margin:12px 0 0">No login yet? Ask {{ $brand['company_name'] ?? 'us' }} to create your portal access.</p>
</div>
@endsection
