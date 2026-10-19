@extends('installer.layout')
@section('step','5')
@section('content')
<h1>5 — Mail / SMTP</h1>
<form method="POST" action="{{ route('installer.mail.store') }}">@csrf
<label>SMTP host</label><input name="MAIL_HOST" value="{{ old('MAIL_HOST','mail.yourdomain.com') }}" required>
<label>Port</label><input name="MAIL_PORT" value="{{ old('MAIL_PORT','465') }}" required>
<label>Username</label><input name="MAIL_USERNAME" value="{{ old('MAIL_USERNAME') }}">
<label>Password</label><input type="password" name="MAIL_PASSWORD">
<label>Encryption (ssl/tls/null)</label><input name="MAIL_ENCRYPTION" value="{{ old('MAIL_ENCRYPTION','ssl') }}">
<label>From address</label><input type="email" name="MAIL_FROM_ADDRESS" value="{{ old('MAIL_FROM_ADDRESS') }}" required>
<label>From name</label><input name="MAIL_FROM_NAME" value="{{ old('MAIL_FROM_NAME') }}" required>
<div class="btnrow"><button class="btn">Save → Integrations</button><a class="btn sec" href="{{ route('installer.company') }}">← Back</a></div></form>
@endsection
