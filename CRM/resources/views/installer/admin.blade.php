@extends('installer.layout')
@section('step','3')
@section('content')
<h1>3 — Administrator account</h1>
<form method="POST" action="{{ route('installer.admin.store') }}">@csrf
<label>Name</label><input name="name" value="{{ old('name') }}" required>
<label>Email</label><input type="email" name="email" value="{{ old('email') }}" required>
<label>Password (min 8)</label><input type="password" name="password" required>
<label>Confirm password</label><input type="password" name="password_confirmation" required>
<div class="btnrow"><button class="btn">Create admin →</button><a class="btn sec" href="{{ route('installer.database') }}">← Back</a></div></form>
@endsection
