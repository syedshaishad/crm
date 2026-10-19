@extends('installer.layout')
@section('step','8')
@section('content')
<h1>8 — Complete installation</h1>
@if(session('storageWarning'))<div class="err">{{ session('storageWarning') }}</div>@endif
@if(session('ok'))<div class="alert">{{ session('ok') }}</div>@endif
<p>Everything is ready. This step generates the app key, saves your settings and locks the installer.</p>
<form method="POST" action="{{ route('installer.complete') }}">@csrf<div class="btnrow"><button class="btn">Complete installation</button><a class="btn sec" href="{{ route('installer.license') }}">← Back</a></div></form>
@endsection
