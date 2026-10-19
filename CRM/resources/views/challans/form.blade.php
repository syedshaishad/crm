@extends('layouts.app')
@section('title', $challan->exists ? 'Edit '.$challan->number : 'New delivery challan')
@section('content')
<div class="card" style="max-width:780px">
<h3>{{ $challan->exists ? 'Edit '.$challan->number : 'New delivery challan' }}</h3>
<p class="fhint">Goods movement without a sale — job work, approvals, samples. No money moves; link an invoice when it bills.</p>
<form method="POST" action="{{ $challan->exists ? route('challans.update',$challan) : route('challans.store') }}">@csrf @if($challan->exists)@method('PUT')@endif
@if(request('invoice_id') && !$challan->exists)<input type="hidden" name="invoice_id" value="{{ request('invoice_id') }}">@endif
<label class="f">Client <span class="req">*</span></label>
<select class="in" name="client_id" required>@foreach($clients as $c)<option value="{{ $c->id }}" {{ (string)old('client_id',$challan->client_id)===(string)$c->id?'selected':'' }}>{{ $c->company ? $c->company.' ('.$c->name.')' : $c->name }}</option>@endforeach</select>
<div class="form-grid">
<div><label class="f">Challan date</label><input class="in" type="date" name="challan_date" value="{{ old('challan_date', $challan->challan_date ? d($challan->challan_date,'Y-m-d') : date('Y-m-d')) }}"></div>
<div><label class="f">Place of supply</label><select class="in" name="place_of_supply"><option value="">Select state</option>@foreach(indian_states() as $st)<option value="{{ $st }}" {{ old('place_of_supply',$challan->place_of_supply)===$st?'selected':'' }}>{{ $st }}</option>@endforeach</select></div>
</div>
<div class="form-grid">
<div><label class="f">Vehicle no</label><input class="in" name="vehicle_no" value="{{ old('vehicle_no',$challan->vehicle_no) }}" placeholder="KA 05 AB 1234" maxlength="20"></div>
<div><label class="f">E-way bill no</label><input class="in" name="eway_bill" value="{{ old('eway_bill',$challan->eway_bill) }}" maxlength="20"></div>
</div>
<label class="f">Transporter</label><input class="in" name="transporter" value="{{ old('transporter',$challan->transporter) }}" placeholder="Who carries it?">
<div class="fsec">What is moving?</div>
@include('products.picker')
<div id="items">
@if($challan->exists)
@foreach($challan->items as $it)
<div class="item-row">
<input class="in" name="items_existing[{{ $it->id }}][description]" value="{{ $it->description }}" required>
<div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><div style="flex:0 0 80px"><label class="f">Qty</label><input class="in" name="items_existing[{{ $it->id }}][qty]" type="number" step="0.01" value="{{ $it->qty }}" style="margin-bottom:0"></div>
<div style="flex:0 0 80px"><label class="f">Unit</label><input class="in" name="items_existing[{{ $it->id }}][unit]" value="{{ $it->unit ?? 'NOS' }}" list="unit-list" style="margin-bottom:0"></div>
<div style="flex:0 0 110px"><label class="f">HSN/SAC</label><input class="in" name="items_existing[{{ $it->id }}][hsn_code]" value="{{ $it->hsn_code }}" style="margin-bottom:0"></div>
<div style="flex:1;min-width:110px"><label class="f">Rate (Rs.)</label><input class="in" name="items_existing[{{ $it->id }}][rate]" type="number" step="0.01" min="0" value="{{ $it->rate }}" style="margin-bottom:0"></div>
<label class="hint" style="display:flex;gap:6px;align-items:center;margin-bottom:10px;white-space:nowrap"><input type="checkbox" name="remove_items[]" value="{{ $it->id }}" style="width:auto;margin:0"> Remove</label></div>
</div>
@endforeach
@else
@foreach($prefillItems as $i => $pf)
<div class="item-row">
<input class="in" name="items[{{ $i }}][description]" value="{{ $pf['description'] }}" required>
<div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><div style="flex:0 0 80px"><label class="f">Qty</label><input class="in" name="items[{{ $i }}][qty]" type="number" step="0.01" value="{{ $pf['qty'] }}" style="margin-bottom:0"></div>
<div style="flex:0 0 80px"><label class="f">Unit</label><input class="in" name="items[{{ $i }}][unit]" value="{{ $pf['unit'] }}" list="unit-list" style="margin-bottom:0"></div>
<div style="flex:0 0 110px"><label class="f">HSN/SAC</label><input class="in" name="items[{{ $i }}][hsn_code]" value="{{ $pf['hsn_code'] }}" style="margin-bottom:0"></div>
<div style="flex:1;min-width:110px"><label class="f">Rate (Rs.)</label><input class="in" name="items[{{ $i }}][rate]" type="number" step="0.01" min="0" value="{{ $pf['rate'] }}" style="margin-bottom:0"></div></div></div>
@endforeach
@if($prefillItems->isEmpty())
<div class="item-row"><input class="in" name="items[0][description]" placeholder="Item description" required>
<div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><div style="flex:0 0 80px"><label class="f">Qty</label><input class="in" name="items[0][qty]" type="number" step="0.01" value="1" style="margin-bottom:0"></div>
<div style="flex:0 0 80px"><label class="f">Unit</label><input class="in" name="items[0][unit]" value="NOS" list="unit-list" style="margin-bottom:0"></div>
<div style="flex:0 0 110px"><label class="f">HSN/SAC</label><input class="in" name="items[0][hsn_code]" placeholder="9983…" style="margin-bottom:0"></div>
<div style="flex:1;min-width:110px"><label class="f">Rate (Rs.)</label><input class="in" name="items[0][rate]" type="number" step="0.01" min="0" value="0" style="margin-bottom:0"></div></div></div>
@endif
@endif
</div>
<button type="button" class="btn sec sm" id="add-item">+ Add another item</button>
<datalist id="unit-list">@foreach(setting_lines('invoice.units',['NOS','HRS','DAY','KGS','MTR','LTR','SET','JOB']) as $u)<option>{{ $u }}</option>@endforeach</datalist>
<label class="f">Notes</label><textarea class="in" name="notes" rows="2">{{ old('notes',$challan->notes) }}</textarea>
<div class="form-actions"><button class="btn" type="submit">{{ $challan->exists ? 'Save changes' : 'Create challan' }}</button><a class="btn sec" href="{{ $challan->exists ? route('challans.show',$challan) : route('challans.index') }}">Cancel</a></div>
</form></div>
@endsection
