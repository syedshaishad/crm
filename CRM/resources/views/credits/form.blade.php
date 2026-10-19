@extends('layouts.app')
@section('title','New credit note')
@section('content')
<div class="card" style="max-width:680px">
<h3>New credit note</h3>
<form method="POST" action="{{ route('credits.store') }}">@csrf
<label class="f">Client <span class="req">*</span></label>
<select class="in" name="client_id" required>@foreach($clients as $c)<option value="{{ $c->id }}" {{ (string)($note->client_id ?? '')===(string)$c->id?'selected':'' }}>{{ $c->company ? $c->company.' ('.$c->name.')' : $c->name }}</option>@endforeach</select>
<label class="f">Against invoice <span class="hint">(optional — pick at apply time otherwise)</span></label>
<select class="in" name="invoice_id"><option value="">No specific invoice</option>@if($invoice)<option value="{{ $invoice->id }}" selected>{{ $invoice->number }} — {{ money($invoice->total) }}</option>@endif</select>
<div class="form-grid">
<div><label class="f">Date</label><input class="in" type="date" name="date" value="{{ date('Y-m-d') }}"></div>
<div><label class="f">Amount (₹) <span class="req">*</span></label><input class="in" type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"></div>
</div>
<label class="f">Reason</label><input class="in" name="reason" placeholder="e.g. Billing correction for October" maxlength="255">
<label class="f">Notes</label><textarea class="in" name="notes" rows="2"></textarea>
<div class="form-actions"><button class="btn" type="submit">Create credit note ({{ $prefix }}…)</button><a class="btn sec" href="{{ route('credits.index') }}">Cancel</a></div>
</form></div>
@endsection
