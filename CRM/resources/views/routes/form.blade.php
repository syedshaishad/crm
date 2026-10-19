@extends('layouts.app')
@section('title','Plan route')
@section('content')
<div class="card" style="max-width:720px">
<h3>Plan a delivery route</h3>
<form method="POST" action="{{ route('routes.store') }}">@csrf
<div class="form-grid">
<div><label class="f">Date <span class="req">*</span></label><input class="in" type="date" name="route_date" value="{{ old('route_date', date('Y-m-d')) }}" required></div>
<div><label class="f">Vehicle</label><input class="in" name="vehicle_no" value="{{ old('vehicle_no') }}" maxlength="40" placeholder="e.g. TS 09 AB 1234"></div>
</div>
<label class="f">Driver</label><input class="in" name="driver" value="{{ old('driver') }}" maxlength="120" placeholder="Who is driving?">
<label class="f">Stops <span class="req">*</span> <span class="hint">— tick issued challans in drop order</span></label>
@if($open->isEmpty())<p class="hint">No issued challans waiting — issue one first.</p>@endif
<div class="tbl-scroll" style="max-height:300px;overflow:auto"><table class="tbl"><tr><th></th><th>Challan</th><th>Client</th><th>Date</th></tr>
@foreach($open as $c)<tr><td><input type="checkbox" name="challans[]" value="{{ $c->id }}" style="width:auto;margin:0"></td><td class="nowrap"><b>{{ $c->number }}</b></td><td>{{ $c->client->company ?? $c->client->name ?? '—' }}</td><td class="nowrap">{{ d($c->challan_date) }}</td></tr>@endforeach</table></div>
<label class="f">Notes</label><textarea class="in" name="notes" rows="2">{{ old('notes') }}</textarea>
<div class="form-actions"><button class="btn" type="submit">Plan route</button><a class="btn sec" href="{{ route('routes.index') }}">Cancel</a></div>
</form></div>
@endsection
