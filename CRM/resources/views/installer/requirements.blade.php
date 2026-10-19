@extends('installer.layout')
@section('step','1')
@section('content')
<h1>1 — Server requirements</h1>
<div class="check {{ $report['php_ok'] ? 'ok' : 'bad' }}"><span>PHP {{ $report['php_version'] }} (needs ≥ {{ $report['min_php'] }})</span><b>{{ $report['php_ok'] ? 'OK' : 'UPGRADE PHP' }}</b></div>
@foreach($report['extensions'] as $ext=>$ok)<div class="check {{ $ok ? 'ok' : 'bad' }}"><span>{{ $ext }} extension</span><b>{{ $ok ? 'OK' : 'MISSING' }}</b></div>@endforeach
@foreach($report['writable'] as $p=>$ok)<div class="check {{ $ok ? 'ok' : 'bad' }}"><span>{{ $p }} writable</span><b>{{ $ok ? 'OK' : 'chmod 755/775' }}</b></div>@endforeach
@if($report['all_ok'])<br><a class="btn" href="{{ route('installer.database') }}">Continue → Database</a>
@else <p>Fix the red items on your hosting (cPanel → Select PHP Version / file permissions), then refresh.</p>@endif
@endsection
