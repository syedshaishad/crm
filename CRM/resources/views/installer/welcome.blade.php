@extends('installer.layout')
@section('step','0')
@section('content')
<h1 style="margin-top:0">Install your CRM</h1>
<p class="hint">Requirements → database → admin → company → mail → integrations → license → done. Takes about 5 minutes. Have your license key ready — the app stays locked until it is activated. The installer locks itself afterwards.</p>
<div class="btnrow" style="margin-top:18px"><a class="btn" href="{{ route('installer.requirements') }}">Start installation →</a></div>
@endsection
