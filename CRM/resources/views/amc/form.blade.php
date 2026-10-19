@extends('layouts.app')
@section('title', $contract->exists ? 'Edit '.$contract->number : 'New contract')
@section('content')
<div class="card" style="max-width:680px">
<h3>{{ $contract->exists ? 'Edit '.$contract->number : 'New contract' }}</h3>
<form method="POST" action="{{ $contract->exists ? route('amc.update',$contract) : route('amc.store') }}">@csrf @if($contract->exists)@method('PUT')@endif
<label class="f">Title <span class="req">*</span></label>
<input class="in" name="title" value="{{ old('title',$contract->title) }}" required placeholder="e.g. Annual maintenance — POS rollout">
<div class="form-grid">
<div><label class="f">Type</label><select class="in" name="type"><option value="amc" {{ old('type',$contract->type ?? 'amc')==='amc'?'selected':'' }}>AMC (paid)</option><option value="warranty" {{ old('type',$contract->type)==='warranty'?'selected':'' }}>Warranty (free)</option></select></div>
<div><label class="f">Value (₹)</label><input class="in" type="number" step="0.01" min="0" name="value" value="{{ old('value',$contract->value ?? 0) }}"></div>
</div>
<label class="f">Client <span class="req">*</span></label>
<select class="in" name="client_id" required>@foreach($clients as $c)<option value="{{ $c->id }}" {{ (string)old('client_id',$contract->client_id)===(string)$c->id?'selected':'' }}>{{ $c->company ? $c->company.' ('.$c->name.')' : $c->name }}</option>@endforeach</select>
<div class="form-grid">
<div><label class="f">Product <span class="hint">(optional)</span></label><select class="in" name="product_id"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}" {{ (string)old('product_id',$contract->product_id)===(string)$p->id?'selected':'' }}>{{ $p->name }}</option>@endforeach</select></div>
<div><label class="f">Invoice <span class="hint">(optional)</span></label><select class="in" name="invoice_id"><option value="">—</option>@foreach($invoices as $i)<option value="{{ $i->id }}" {{ (string)old('invoice_id',$contract->invoice_id)===(string)$i->id?'selected':'' }}>{{ $i->number }}</option>@endforeach</select></div>
</div>
<div class="form-grid">
<div><label class="f">Starts</label><input class="in" type="date" name="start_date" value="{{ old('start_date', $contract->start_date ? d($contract->start_date,'Y-m-d') : date('Y-m-d')) }}"></div>
<div><label class="f">Ends</label><input class="in" type="date" name="end_date" value="{{ old('end_date', $contract->end_date ? d($contract->end_date,'Y-m-d') : '') }}"></div>
</div>
<label class="f">Notes</label><textarea class="in" name="notes" rows="2">{{ old('notes',$contract->notes) }}</textarea>
<div class="form-actions"><button class="btn" type="submit">{{ $contract->exists ? 'Save changes' : 'Create contract' }}</button><a class="btn sec" href="{{ $contract->exists ? route('amc.show',$contract) : route('amc.index') }}">Cancel</a></div>
</form></div>
@endsection
