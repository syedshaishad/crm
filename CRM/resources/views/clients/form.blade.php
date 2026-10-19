@extends('layouts.app')
@section('title',($client->exists ? 'Edit ' : 'New ').'client')
@section('content')
<div class="card" style="max-width:680px">
<h3>{{ $client->exists ? 'Edit client' : 'New client' }}</h3>
<p class="fhint">Just the name is required — everything else can wait.</p>
<form method="POST" action="{{ $client->exists ? route('clients.update',$client) : route('clients.store') }}">@csrf @if($client->exists)@method('PUT')@endif
<label class="f">Name <span class="req">*</span></label>
<input class="in" name="name" value="{{ old('name',$client->name) }}" required autofocus placeholder="e.g. Acme Pvt Ltd or Rahul Sharma" autocomplete="off">
<div class="form-grid">
<div><label class="f">Email</label><input class="in" type="email" name="email" value="{{ old('email',$client->email) }}" placeholder="billing@client.com" autocomplete="off"></div>
<div><label class="f">Phone</label><input class="in" type="tel" name="phone" value="{{ old('phone',$client->phone) }}" placeholder="+91 …" autocomplete="off"></div>
</div>
<details class="adv" {{ $client->exists ? 'open' : '' }}><summary>More details (company, address, GSTIN…)</summary>
<div style="margin-top:10px">
<div class="fsec">Company & address</div>
<label class="f">Company</label><input class="in" name="company" value="{{ old('company',$client->company) }}" placeholder="Company / organisation">
<label class="f">Address</label><textarea class="in" name="address" rows="2" placeholder="Billing address">{{ old('address',$client->address) }}</textarea>
<div class="fsec">Tax details</div>
<div class="form-grid">
<div><label class="f">GSTIN</label><input class="in" name="gstin" value="{{ old('gstin',$client->gstin) }}" placeholder="15-char GSTIN" maxlength="15" style="text-transform:uppercase"></div>
<div><label class="f">PAN</label><input class="in" name="pan" value="{{ old('pan',$client->pan) }}" placeholder="Optional" maxlength="10" style="text-transform:uppercase"></div>
<div><label class="f">State</label><select class="in" name="state"><option value="">Select state</option>@foreach(indian_states() as $st)<option value="{{ $st }}" {{ old('state',$client->state)===$st?'selected':'' }}>{{ $st }}</option>@endforeach</select><div class="fhint">Decides CGST+SGST vs IGST on invoices.</div></div>
</div>
<div class="fsec">Account</div>
<label class="f">Status</label><select class="in" name="status" style="max-width:240px"><option value="active" {{ old('status',$client->status ?? 'active')==='active'?'selected':'' }}>Active</option><option value="inactive" {{ old('status',$client->status)==='inactive'?'selected':'' }}>Inactive</option></select>
<div><label class="f">Birthday <span class="hint">(for greetings — yearly reminder)</span></label><input class="in" type="date" name="birthday" style="max-width:240px" value="{{ old('birthday', $client->birthday ? d($client->birthday,'Y-m-d') : '') }}"></div>
<label class="f">Credit limit (₹) <span class="hint">— 0 means unlimited</span></label><input class="in" type="number" step="0.01" min="0" name="credit_limit" style="max-width:240px" value="{{ old('credit_limit',$client->credit_limit ?? 0) }}">
<div><label class="f">Billing currency</label><select class="in" name="currency" style="max-width:240px">@foreach(setting_lines('invoice.currencies',['INR','USD','EUR','GBP','AED']) as $cu)<option value="{{ $cu }}" {{ old('currency',$client->currency ?? 'INR')===$cu?'selected':'' }}>{{ $cu }}</option>@endforeach</select><div class="fhint">New invoices default to this. Books always stay in ₹.</div></div>
<label class="f">Notes</label><textarea class="in" name="notes" rows="2" placeholder="Anything worth remembering…">{{ old('notes',$client->notes ?? '') }}</textarea>
<div class="fsec">Custom fields <span class="hint">— e.g. Birthday, Preferred contact time</span></div>
<div id="cf-rows">
@php $cfs = old('cf_label', []); $cfv = old('cf_value', []); $saved = $client->custom_fields ?? []; @endphp
@if($cfs)
@foreach($cfs as $i=>$l)<div style="display:flex;gap:8px;margin-bottom:8px"><input class="in" name="cf_label[]" value="{{ $l }}" placeholder="Label" maxlength="60" style="margin:0;max-width:200px"><input class="in" name="cf_value[]" value="{{ $cfv[$i] ?? '' }}" placeholder="Value" maxlength="255" style="margin:0"></div>@endforeach
@else
@foreach($saved as $l=>$v)<div style="display:flex;gap:8px;margin-bottom:8px"><input class="in" name="cf_label[]" value="{{ $l }}" placeholder="Label" maxlength="60" style="margin:0;max-width:200px"><input class="in" name="cf_value[]" value="{{ $v }}" placeholder="Value" maxlength="255" style="margin:0"></div>@endforeach
<div style="display:flex;gap:8px;margin-bottom:8px"><input class="in" name="cf_label[]" value="" placeholder="Label" maxlength="60" style="margin:0;max-width:200px"><input class="in" name="cf_value[]" value="" placeholder="Value" maxlength="255" style="margin:0"></div>
@endif
</div>
<button type="button" class="btn sec sm" id="cf-add">+ Add field</button>
<script>(function(){var b=document.getElementById('cf-add'),w=document.getElementById('cf-rows');if(!b||!w)return;b.onclick=function(){var d=document.createElement('div');d.style.cssText='display:flex;gap:8px;margin-bottom:8px';d.innerHTML='<input class="in" name="cf_label[]" placeholder="Label" maxlength="60" style="margin:0;max-width:200px"><input class="in" name="cf_value[]" placeholder="Value" maxlength="255" style="margin:0">';w.appendChild(d);};})();</script>
</div></details>
<div class="form-actions"><button class="btn" type="submit">{{ $client->exists ? 'Save changes' : 'Add client' }}</button><a class="btn sec" href="{{ $client->exists ? route('clients.show',$client) : route('clients.index') }}">Cancel</a></div>
</form></div>
@endsection
