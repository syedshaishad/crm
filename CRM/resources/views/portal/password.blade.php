@extends('portal.layout')
@section('title','Change password')
@section('content')
<div class="page-head"><h1>Change password</h1><div class="crumb">Use at least 8 characters. You will stay signed in.</div></div>
<div class="card">
@if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
@if(session('ok'))<div class="alert">{{ session('ok') }}</div>@endif
<form method="POST" action="{{ route('portal.password.store') }}">@csrf
  <label class="f" for="cur-pw">Current password</label>
  <input class="in" id="cur-pw" type="password" name="current" required autocomplete="current-password">
  <label class="f" for="new-pw">New password</label>
  <input class="in" id="new-pw" type="password" name="password" required minlength="8" autocomplete="new-password">
  <label class="f" for="new-pw2">Confirm new password</label>
  <input class="in" id="new-pw2" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
  <div class="form-actions"><button class="btn" type="submit">Change password</button><a class="btn sec" href="{{ route('portal.dashboard') }}">Cancel</a></div>
</form>
</div>
@endsection
