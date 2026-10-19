@extends('installer.layout')
@section('step','2')
@section('content')
<h1>2 — Database</h1>
@error('db')<div class="err" style="margin-bottom:12px">{{ $message }}</div>@enderror
<form method="POST" action="{{ route('installer.database.store') }}">@csrf
<label>MySQL host</label><input name="DB_HOST" value="{{ old('DB_HOST','127.0.0.1') }}" required>
<label>Port</label><input name="DB_PORT" value="{{ old('DB_PORT','3306') }}" required>
<label>Database name</label><input name="DB_DATABASE" value="{{ old('DB_DATABASE','crm') }}" required>
<label>Username</label><input name="DB_USERNAME" value="{{ old('DB_USERNAME','') }}" required>
<label>Password</label><input type="password" name="DB_PASSWORD" value="{{ old('DB_PASSWORD','') }}">
<div class="btnrow"><button class="btn">Test & migrate →</button><a class="btn sec" href="{{ route('installer.requirements') }}">← Back</a></div></form>
@endsection
