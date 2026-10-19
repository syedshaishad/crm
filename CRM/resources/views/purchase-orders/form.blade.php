@extends('layouts.app')
@section('title', $order->exists ? 'Edit '.$order->number : 'New purchase order')
@section('content')
<div class="card" style="max-width:780px">
<h3>{{ $order->exists ? 'Edit '.$order->number : 'New purchase order' }}</h3>
<form method="POST" action="{{ $order->exists ? route('purchase-orders.update',$order) : route('purchase-orders.store') }}">@csrf @if($order->exists)@method('PUT')@endif
<label class="f">Vendor <span class="req">*</span></label>
<input class="in" name="vendor" value="{{ old('vendor',$order->vendor) }}" required placeholder="Supplier name">
<div class="form-grid">
<div><label class="f">Vendor email</label><input class="in" type="email" name="vendor_email" value="{{ old('vendor_email',$order->vendor_email) }}"></div>
<div><label class="f">Project <span class="hint">(optional)</span></label><select class="in" name="project_id"><option value="">No project</option>@foreach($projects as $p)<option value="{{ $p->id }}" {{ (string)old('project_id',$order->project_id)===(string)$p->id?'selected':'' }}>{{ $p->name }}</option>@endforeach</select></div>
</div>
<div class="form-grid">
<div><label class="f">Issue date</label><input class="in" type="date" name="issue_date" value="{{ old('issue_date', $order->issue_date ? d($order->issue_date,'Y-m-d') : date('Y-m-d')) }}"></div>
<div><label class="f">Expected by</label><input class="in" type="date" name="expected_on" value="{{ old('expected_on', $order->expected_on ? d($order->expected_on,'Y-m-d') : '') }}"></div>
</div>
<div class="form-grid">
<div><label class="f">Discount (₹)</label><input class="in" type="number" step="0.01" min="0" name="discount" value="{{ old('discount',$order->discount ?? 0) }}"></div>
<div><label class="f">Assign to</label><select class="in" name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $u)<option value="{{ $u->id }}" {{ (string)old('assigned_to',$order->assigned_to)===(string)$u->id?'selected':'' }}>{{ $u->name }} ({{ $u->role }})</option>@endforeach</select></div>
</div>
<div class="fsec">Default GST for new lines (each line can differ)</div>
<div class="pills" id="gst-defaults">
@foreach([0,5,12,18,28] as $r)
<label><input type="radio" name="gst_rate" value="{{ $r }}" {{ (string)old('gst_rate',$order->gst_rate ?? 18)===(string)$r?'checked':'' }}><span>{{ $r }}%</span></label>
@endforeach
</div>
<div class="fsec">What are you ordering?</div>
@include('products.picker')
<div id="items">
@if($order->exists)
@foreach($order->items as $it)
<div class="item-row">
<input class="in" name="items_existing[{{ $it->id }}][description]" value="{{ $it->description }}" required>
<div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><div style="flex:0 0 80px"><label class="f">Qty</label><input class="in" name="items_existing[{{ $it->id }}][qty]" type="number" step="0.01" value="{{ $it->qty }}" style="margin-bottom:0"></div>
<div style="flex:0 0 80px"><label class="f">Unit</label><input class="in" name="items_existing[{{ $it->id }}][unit]" value="{{ $it->unit ?? 'NOS' }}" list="unit-list" style="margin-bottom:0"></div>
<div style="flex:0 0 110px"><label class="f">HSN/SAC</label><input class="in" name="items_existing[{{ $it->id }}][hsn_code]" value="{{ $it->hsn_code }}" style="margin-bottom:0"></div>
<div style="flex:1;min-width:110px"><label class="f">Rate (Rs.)</label><input class="in" name="items_existing[{{ $it->id }}][rate]" type="number" step="0.01" min="0" value="{{ $it->rate }}" style="margin-bottom:0"></div>
<div style="flex:0 0 90px"><label class="f">GST%</label><select class="in" name="items_existing[{{ $it->id }}][tax_rate]" style="margin-bottom:0">@foreach([0,5,12,18,28] as $r)<option value="{{ $r }}" {{ (string)($it->tax_rate ?? $order->gst_rate)===(string)$r?'selected':'' }}>{{ $r }}%</option>@endforeach</select></div>
<label class="hint" style="display:flex;gap:6px;align-items:center;margin-bottom:10px;white-space:nowrap"><input type="checkbox" name="remove_items[]" value="{{ $it->id }}" style="width:auto;margin:0"> Remove</label></div>
</div>
@endforeach
@else
<div class="item-row"><input class="in" name="items[0][description]" placeholder="Item description" required>
<div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><div style="flex:0 0 80px"><label class="f">Qty</label><input class="in" name="items[0][qty]" type="number" step="0.01" value="1" style="margin-bottom:0"></div>
<div style="flex:0 0 80px"><label class="f">Unit</label><input class="in" name="items[0][unit]" value="NOS" list="unit-list" style="margin-bottom:0"></div>
<div style="flex:0 0 110px"><label class="f">HSN/SAC</label><input class="in" name="items[0][hsn_code]" placeholder="9983…" style="margin-bottom:0"></div>
<div style="flex:1;min-width:110px"><label class="f">Rate (Rs.)</label><input class="in" name="items[0][rate]" type="number" step="0.01" min="0" value="0" style="margin-bottom:0"></div>
<div style="flex:0 0 90px"><label class="f">GST%</label><select class="in" name="items[0][tax_rate]" style="margin-bottom:0">@foreach([0,5,12,18,28] as $r)<option value="{{ $r }}" {{ (string)($order->gst_rate ?? 18)===(string)$r?'selected':'' }}>{{ $r }}%</option>@endforeach</select></div></div></div>
@endif
</div>
<button type="button" class="btn sec sm" id="add-item">+ Add another item</button>
<datalist id="unit-list">@foreach(setting_lines('invoice.units',['NOS','HRS','DAY','KGS','MTR','LTR','SET','JOB']) as $u)<option>{{ $u }}</option>@endforeach</datalist>
<div class="inv-total" id="inv-total" style="margin-top:12px"></div>
<label class="f">Notes</label><textarea class="in" name="notes" rows="2">{{ old('notes',$order->notes) }}</textarea>
<div class="form-actions"><button class="btn" type="submit">{{ $order->exists ? 'Save changes' : 'Create order' }}</button><a class="btn sec" href="{{ $order->exists ? route('purchase-orders.show',$order) : route('purchase-orders.index') }}">Cancel</a></div>
</form></div>
@endsection
