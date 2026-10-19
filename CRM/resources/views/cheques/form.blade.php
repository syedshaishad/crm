@extends('layouts.app')
@section('title', $cheque->exists ? 'Edit '.$cheque->number : 'New cheque')
@section('content')
<div class="card" style="max-width:680px">
<h3>{{ $cheque->exists ? 'Edit '.$cheque->number : 'Record a post-dated cheque' }}</h3>
<form method="POST" action="{{ $cheque->exists ? route('cheques.update',$cheque) : route('cheques.store') }}">@csrf @if($cheque->exists)@method('PUT')@endif
<label class="f">Received from <span class="req">*</span></label>
<select class="in" name="client_id" required>@foreach($clients as $c)<option value="{{ $c->id }}" {{ (string)old('client_id',$cheque->client_id)===(string)$c->id?'selected':'' }}>{{ $c->company ? $c->company.' ('.$c->name.')' : $c->name }}</option>@endforeach</select>
<label class="f">Against invoice <span class="hint">(optional — clearance books the payment here)</span></label>
<select class="in" name="invoice_id"><option value="">No invoice</option>@foreach($invoices as $i)<option value="{{ $i->id }}" {{ (string)old('invoice_id',$cheque->invoice_id)===(string)$i->id?'selected':'' }}>{{ $i->number }}</option>@endforeach</select>
<div class="form-grid">
<div><label class="f">Cheque no <span class="req">*</span></label><input class="in" name="cheque_no" value="{{ old('cheque_no',$cheque->cheque_no) }}" required placeholder="6-digit no"></div>
<div><label class="f">Bank</label><input class="in" name="bank" value="{{ old('bank',$cheque->bank) }}" placeholder="e.g. HDFC"></div>
</div>
<div class="form-grid">
<div><label class="f">Amount (₹) <span class="req">*</span></label><input class="in" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount',$cheque->amount) }}" required></div>
<div><label class="f">Cheque date <span class="req">*</span></label><input class="in" type="date" name="cheque_date" value="{{ old('cheque_date', $cheque->cheque_date ? d($cheque->cheque_date,'Y-m-d') : date('Y-m-d')) }}" required></div>
</div>
<label class="f">Notes</label><textarea class="in" name="notes" rows="2">{{ old('notes',$cheque->notes) }}</textarea>
<div class="form-actions"><button class="btn" type="submit">{{ $cheque->exists ? 'Save changes' : 'Record cheque' }}</button><a class="btn sec" href="{{ $cheque->exists ? route('cheques.show',$cheque) : route('cheques.index') }}">Cancel</a></div>
</form></div>
@endsection
