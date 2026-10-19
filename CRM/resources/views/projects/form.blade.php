@extends('layouts.app')
@section('title',($project->exists ? 'Edit ' : 'New ').'project')
@section('content')
<div class="card" style="max-width:680px">
<h3>{{ $project->exists ? 'Edit project' : 'New project' }}</h3>
<form method="POST" action="{{ $project->exists ? route('projects.update',$project) : route('projects.store') }}">@csrf @if($project->exists)@method('PUT')@endif
<label class="f">Project name <span class="req">*</span></label>
<input class="in" name="name" value="{{ old('name',$project->name) }}" required autofocus placeholder="e.g. Website redesign">
<label class="f">Client <span class="req">*</span></label>
<select class="in" name="client_id" required>@foreach($clients as $c)<option value="{{ $c->id }}" {{ (string)old('client_id',$project->client_id)===(string)$c->id?'selected':'' }}>{{ $c->name }}</option>@endforeach</select>
<label class="f">What needs doing?</label>
<textarea class="in" name="description" rows="3" placeholder="Scope, deliverables, links…">{{ old('description',$project->description) }}</textarea>
<div class="fsec">Status</div>
<div class="pills">
@foreach(['new'=>'New','in_progress'=>'In progress','on_hold'=>'On hold','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l)
<label><input type="radio" name="status" value="{{ $v }}" {{ old('status',$project->status ?? 'new')===$v?'checked':'' }}><span>{{ $l }}</span></label>
@endforeach
</div>
<details class="adv"><summary>Schedule & budget (optional)</summary>
<div style="margin-top:10px" class="form-grid">
<div><label class="f">Start</label><input class="in" type="date" name="start_date" value="{{ old('start_date', $project->start_date ? $project->start_date->format('Y-m-d') : '') }}"></div>
<div><label class="f">Due</label><input class="in" type="date" name="due_date" value="{{ old('due_date', $project->due_date ? $project->due_date->format('Y-m-d') : '') }}"></div>
</div>
<label class="f">Budget (₹)</label><input class="in" type="number" step="0.01" min="0" name="budget" value="{{ old('budget',$project->budget ?? '') }}" placeholder="0"></details>
<div class="form-actions"><button class="btn" type="submit">{{ $project->exists ? 'Save changes' : 'Add project' }}</button><a class="btn sec" href="{{ $project->exists ? route('projects.show',$project) : route('projects.index') }}">Cancel</a></div>
</form></div>
@endsection
