@extends('layouts.app')
@section('title', $product->exists ? 'Edit product' : 'New product')
@section('content')
<div class="card" style="max-width:680px"><h3>{{ $product->exists ? 'Edit product' : 'New product' }}</h3>
<p class="fhint">Catalog entries prefill invoice and proposal lines. Issued documents keep their own copies, so edits never rewrite history.</p>
<form method="POST" action="{{ $product->exists ? route('products.update',$product) : route('products.store') }}" enctype="multipart/form-data">@csrf @if($product->exists)@method('PUT')@endif
<div class="form-grid">
<div><label class="f">Name <span class="req">*</span></label><input class="in" name="name" value="{{ old('name',$product->name) }}" required maxlength="255" placeholder="e.g. Website design"></div>
<div><label class="f">SKU / code</label><input class="in" name="sku" value="{{ old('sku',$product->sku) }}" maxlength="40" placeholder="e.g. WEB-DESIGN"></div>
</div>
<label class="f">Description</label><textarea class="in" name="description" rows="2" placeholder="Shown under the line on documents…">{{ old('description',$product->description) }}</textarea>
<div class="form-grid">
<div><label class="f">Rate (₹)</label><input class="in" name="rate" type="number" step="0.01" min="0" value="{{ old('rate',$product->rate ?? 0) }}"></div>
<div><label class="f">GST %</label><select class="in" name="tax_rate"><option value="">Use document default</option>@foreach([0,5,12,18,28] as $r)<option value="{{ $r }}" {{ (string)old('tax_rate',$product->tax_rate)===(string)$r?'selected':'' }}>{{ $r }}%</option>@endforeach</select></div>
<div><label class="f">HSN / SAC</label><input class="in" name="hsn_code" value="{{ old('hsn_code',$product->hsn_code) }}" maxlength="20" placeholder="998314"></div>
<div><label class="f">Unit</label><input class="in" name="unit" value="{{ old('unit',$product->unit ?? 'NOS') }}" list="unit-list" maxlength="10"></div>
</div>
<datalist id="unit-list">@foreach(setting_lines('invoice.units',['NOS','HRS','DAY','KGS','MTR','LTR','SET','JOB']) as $u)<option>{{ $u }}</option>@endforeach</datalist>
<label class="f">Photo <span class="hint">(shows in the picker)</span></label>@if($product->image_path)<img src="{{ asset('storage/'.$product->image_path) }}" alt="product photo" style="max-height:90px;border-radius:10px;margin-bottom:8px">@endif<input class="in" type="file" name="image" accept="image/*">
<label class="f">Status</label><select class="in" name="status"><option value="active" {{ old('status',$product->status ?? 'active')==='active'?'selected':'' }}>Active — appears in the picker</option><option value="archived" {{ old('status',$product->status)==='archived'?'selected':'' }}>Archived — hidden from the picker</option></select>
<div class="fsec">Stock (optional — tracked goods only, services stay at zero)</div>
<div class="form-grid">
<div><label class="f">Opening stock</label><input class="in" name="opening_stock" type="number" step="0.01" min="0" value="{{ old('opening_stock',$product->opening_stock ?? 0) }}"></div>
<div><label class="f">Low-stock alert at</label><input class="in" name="low_stock_at" type="number" step="0.01" min="0" value="{{ old('low_stock_at',$product->low_stock_at) }}" placeholder="Blank = no alert"></div>
</div>
<div class="form-actions"><button class="btn" type="submit">{{ $product->exists ? 'Save changes' : 'Create product' }}</button><a class="btn sec" href="{{ route('products.index') }}">Cancel</a></div>
</form></div>
@endsection
