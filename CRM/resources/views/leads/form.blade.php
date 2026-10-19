@extends('layouts.app')
@section('title',($lead->exists ? 'Edit ' : 'New ').'lead')
@section('content')
<div class="card" style="max-width:680px">
<h3>{{ $lead->exists ? 'Edit lead' : 'New lead' }}</h3>
<form method="POST" action="{{ $lead->exists ? route('leads.update',$lead) : route('leads.store') }}">@csrf @if($lead->exists)@method('PUT')@endif
<div class="form-grid">
<div><label class="f">Name <span class="req">*</span></label><input class="in" name="name" value="{{ old('name',$lead->name) }}" required autofocus placeholder="Contact person"></div>
<div><label class="f">Company</label><input class="in" name="company" value="{{ old('company',$lead->company) }}" placeholder="Company / shop"></div>
</div>
<div class="form-grid">
<div><label class="f">Phone</label><input class="in" name="phone" value="{{ old('phone',$lead->phone) }}" placeholder="10-digit mobile" autocomplete="off"></div>
<div><label class="f">Email</label><input class="in" type="email" name="email" value="{{ old('email',$lead->email) }}" placeholder="name@example.com"></div>
</div>
<div class="form-grid">
<div><label class="f">City</label><input class="in" name="city" value="{{ old('city',$lead->city) }}" placeholder="e.g. Hyderabad"></div>
<div><label class="f">State</label><select class="in" name="state"><option value="">Select state</option>@foreach(indian_states() as $st)<option value="{{ $st }}" {{ old('state',$lead->state)===$st?'selected':'' }}>{{ $st }}</option>@endforeach</select></div>
</div>
<div class="form-grid">
<div><label class="f">Source</label><select class="in" name="source">@foreach($sources as $src)<option value="{{ $src }}" {{ old('source',$lead->source)===$src?'selected':'' }}>{{ ucfirst($src) }}</option>@endforeach</select></div>
<div><label class="f">Expected value (₹)</label><input class="in" type="number" step="0.01" min="0" name="value" value="{{ old('value',$lead->value) }}" placeholder="0.00"></div>
</div>
<label class="f">Stage</label>
<div class="pills">
@foreach(\Modules\Leads\Lead::stages() as $sv=>$sl)
<label><input type="radio" name="stage" value="{{ $sv }}" {{ old('stage',$lead->stage ?? 'new')===$sv?'checked':'' }}><span>{{ $sl }}</span></label>
@endforeach
</div>
<div class="form-grid">
<div><label class="f">Follow up on</label><input class="in" type="date" name="follow_up_on" value="{{ old('follow_up_on', $lead->follow_up_on ? $lead->follow_up_on->format('Y-m-d') : '') }}"></div>
<div><label class="f">Assigned to</label><select class="in" name="assigned_to"><option value="">Unassigned</option>@foreach($users as $u)<option value="{{ $u->id }}" {{ (string)old('assigned_to',$lead->assigned_to)===(string)$u->id?'selected':'' }}>{{ $u->name }} ({{ $u->role }})</option>@endforeach</select></div>
</div>
<label class="f">Notes</label><textarea class="in" name="notes" rows="2" placeholder="Requirement, budget, next step…">{{ old('notes',$lead->notes) }}</textarea>
<div class="form-actions"><button class="btn" type="submit">{{ $lead->exists ? 'Save changes' : 'Add lead' }}</button><a class="btn sec" href="{{ route('leads.index') }}">Cancel</a>@if($lead->exists && !in_array($lead->stage,['won','lost']))<form method="POST" action="{{ route('leads.convert',$lead) }}" style="display:inline">@csrf<button class="btn sec" type="submit">Convert to client</button></form>@endif</div></form>
</div>
@endsection
