@extends('portal.layout')
@section('title','Change password')
@section('content')
<div class="card" style="max-width:520px"><h3>Change password</h3>
<form method="POST" action="{{ route('portal.password.store') }}">@csrf
<label class="f">Current password</label><input class="in" type="password" name="current" required>
<label class="f">New password</label><input class="in" type="password" name="password" required minlength="8">
<label class="f">Confirm new password</label><input class="in" type="password" name="password_confirmation" required minlength="8">
<div class="form-actions"><button class="btn" type="submit">Change password</button><a class="btn sec" href="{{ route('portal.dashboard') }}">Cancel</a></div>
</form></div>
@endsection
