@extends('layouts.app')
@section('title','Modules')
@section('content')
<div class="card" style="max-width:680px"><div class="card-head"><h3>Modules</h3></div>
<p class="hint" style="margin-top:0">Switch workspace areas off to simplify this install. Hidden modules vanish from the nav, search, import/export and API (direct URLs 404). Settings, updates and licensing always stay on.</p>
<form method="POST" action="{{ route('settings.modules.store') }}">@csrf
@foreach($modules as $m)
@php $on = ($over[$m] ?? config('modules.enabled.'.$m, true)) ? true : false; @endphp
<div class="mi-row" style="border-color:var(--line)">
  <span style="flex:1"><b>{{ $m }}</b><span>{{ $on ? 'visible' : 'hidden' }}</span></span>
  <label class="hint" style="display:flex;gap:8px;align-items:center;margin:0"><input type="checkbox" name="off[]" value="{{ $m }}" {{ $on ? '' : 'checked' }} style="width:auto;margin:0"> Off</label>
</div>
@endforeach
<div class="form-actions" style="margin-top:14px"><button class="btn" type="submit">Save modules</button><a class="btn sec" href="{{ route('settings.index') }}">Cancel</a></div>
</form></div>
@endsection
