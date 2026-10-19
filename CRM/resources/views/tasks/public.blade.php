@extends('portal.layout')
@section('title','Task sign-off')
@section('content')
@php $brand = $brand ?? []; @endphp
<div class="page-head"><h1>{{ $task->title }}</h1><div class="crumb">{{ $task->project->name ?? 'Direct task' }} · {{ ucfirst($task->status) }}</div></div>
<div class="card">
@if($task->description)<p style="margin-top:0">{{ $task->description }}</p>@endif
@if($task->checklist->isNotEmpty())
<div class="fsec" style="margin-top:0">What was covered</div>
@foreach($task->checklist as $c)<div class="mi-row" style="border-color:var(--line)"><span class="rowcheck {{ $c->is_done ? 'done' : '' }}">{{ $c->is_done ? '✓' : '' }}</span><span style="flex:1">{{ $c->label }}</span></div>@endforeach
@endif
@if($task->handed_over_at)
<div class="alert" style="margin-top:14px">Signed off by <b>{{ $task->handed_over_to }}</b> on {{ d($task->handed_over_at,'j M Y, h:i A') }}. Thank you!</div>
@else
<hr><h3>Accept this work</h3>
<p class="fhint">Happy with it? Drop your name and hit accept — that closes the task.</p>
<form method="POST" action="{{ route('tasks.public.accept',$task->public_token) }}">@csrf
<label class="f">Your name <span class="req">*</span></label><input class="in" name="handed_over_to" required maxlength="255" placeholder="e.g. {{ $task->project->client->name ?? 'Client' }}">
<label class="f">Note <span class="hint">(snags, if any)</span></label><input class="in" name="handover_note" maxlength="255" placeholder="Optional…">
<div class="form-actions"><button class="btn brand" type="submit">Accept work</button></div></form>
@endif
</div>
<p class="footer-note">Sent by {{ $brand['company_name'] ?? config('app.name') }}</p>
@endsection
