@extends('layouts.app')
@section('title','New invoice')
@section('content')
<div class="card" style="max-width:780px">
<h3>New invoice</h3>
<form method="POST" action="{{ route('invoices.store') }}">@csrf
<div class="fsec" style="margin-top:0">1 · Client & project</div>
<label class="f">Bill to <span class="req">*</span></label>
<select class="in" name="client_id" required autofocus>@foreach($clients as $c)<option value="{{ $c->id }}" {{ (string)old('client_id', $default_client ?? '')===(string)$c->id?'selected':'' }}>{{ $c->company ? $c->company.' ('.$c->name.')' : $c->name }}{{ $c->gstin ? ' · '.$c->gstin : '' }}</option>@endforeach</select>
@php $dc = ($default_client ?? '') ? $clients->firstWhere('id', (int) $default_client) : null; @endphp
@if($dc && ($cw = credit_warn($dc)))<div class="alert warn" style="margin-top:10px">{{ $cw }} Collect payment before billing more.</div>@endif
<label class="f">Project <span class="hint">(optional — links costs & files)</span></label>
<select class="in" name="project_id"><option value="">No project</option>@foreach($projects as $p)<option value="{{ $p->id }}" {{ (string)old('project_id')===(string)$p->id?'selected':'' }}>{{ $p->name }}</option>@endforeach</select>
@if(auth()->user()->canManageInvoices())
<label class="f">Assign to <span class="hint">(managers assign staff)</span></label>
<select class="in" name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $u)<option value="{{ $u->id }}" {{ (string)old('assigned_to')===(string)$u->id?'selected':'' }}>{{ $u->name }} ({{ $u->role }})</option>@endforeach</select>
@endif
<div class="fsec">2 · Dates & currency</div>
<div class="form-grid">
<div><label class="f">Issue date <span class="req">*</span></label><input class="in" type="date" name="issue_date" value="{{ old('issue_date', date('Y-m-d')) }}" required></div>
<div><label class="f">Due date</label><input class="in" type="date" name="due_date" value="{{ old('due_date', date('Y-m-d', strtotime('+'.(int) setting('invoice.due_days', 7).' days'))) }}"><div class="fhint">Defaults to {{ setting('invoice.due_days', 7) }} days (Settings → Billing).</div></div>
</div>
<div class="form-grid">
<div><label class="f">Currency</label><select class="in" name="currency" id="inv-currency">@foreach($currencies as $cu)<option value="{{ $cu }}" {{ old('currency', $invoice->currency ?? 'INR')===$cu?'selected':'' }}>{{ $cu }}</option>@endforeach</select></div>
<div><label class="f">Rate (1 <span id="inv-cur-code">{{ old('currency', $invoice->currency ?? 'INR') }}</span> = ₹)</label><input class="in" type="number" step="0.000001" min="0.000001" name="exchange_rate" id="inv-fx" value="{{ old('exchange_rate', 1) }}"><div class="fhint">Books stay in ₹ — line rates convert at this snapshot rate.</div></div>
</div>
<div class="fsec">3 · Tax treatment</div>
<div class="form-grid">
<div><label class="f">Place of supply</label><select class="in" name="place_of_supply" id="inv-pos"><option value="">Select state</option>@foreach(indian_states() as $st)<option value="{{ $st }}" {{ old('place_of_supply', $invoice->place_of_supply ?? '')===$st?'selected':'' }}>{{ $st }}</option>@endforeach</select><div class="fhint" id="inv-tax-note" role="status">Same state as yours: CGST+SGST. Different: IGST.@if(!setting('company.state')) Set your registered state in Settings → Branding for auto split.@endif</div></div>
<div><label class="f">Discount (<span class="rate-cur">Rs.</span>)</label><input class="in" type="number" step="0.01" min="0" name="discount" value="{{ old('discount', 0) }}" placeholder="0.00"></div>
<div><label class="f">E-way bill no <span class="hint">(12-digit, if goods moved)</span></label><input class="in" name="eway_bill_no" value="{{ old('eway_bill_no') }}" maxlength="20" placeholder="Optional"></div>
</div>
@if(setting('tax.enable_gst','1')==='1')
<div class="fsec">Default GST for new lines (each line can differ)</div>
<div class="pills" id="gst-defaults">
@foreach([0,5,12,18,28] as $r)
<label><input type="radio" name="gst_rate" value="{{ $r }}" {{ (string)old('gst_rate', $invoice->gst_rate)===(string)$r?'checked':'' }}><span>{{ $r }}%</span></label>
@endforeach
</div>
@else
<input type="hidden" name="gst_rate" value="0">
<p class="fhint">GST is disabled in Settings → Tax, so this invoice is zero-rated.</p>
@endif
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:14px"><input type="checkbox" name="reverse_charge" value="1" {{ old('reverse_charge')?'checked':'' }} style="width:auto;margin:0"> Reverse charge applies (recipient pays GST)</label>
<div class="fsec">4 · Line items — what are you billing for?</div>
@include('products.picker')
@php $oldItems = old('items'); $oldItems = is_array($oldItems) ? $oldItems : []; $rows = max(1, count($oldItems)); @endphp
<div id="items">
@if($oldItems)
@foreach($oldItems as $k => $it)@include('invoices.item-row', ['k' => $k, 'it' => $it, 'rows' => $rows])@endforeach
@else
@include('invoices.item-row', ['k' => 0, 'it' => null, 'rows' => 1])
@endif
</div>
<datalist id="unit-list">@foreach(setting_lines('invoice.units',['NOS','HRS','DAY','KGS','MTR','LTR','SET','JOB']) as $u)<option>{{ $u }}</option>@endforeach</datalist>
<button type="button" class="btn sec sm" id="add-item">+ Add another item</button>
<div class="inv-total" id="inv-total" style="margin-top:12px"></div>
<details class="adv"><summary>5 · Notes (optional — shown on the invoice)</summary>
<div style="margin-top:10px"><textarea class="in" name="notes" rows="2" style="margin-bottom:0" placeholder="Payment terms, UPI ID, thank-you note…">{{ old('notes', setting('invoice.default_notes', '')) }}</textarea></div></details>
<script>(function(){var s=document.getElementById('inv-pos'),n=document.getElementById('inv-tax-note');if(!s||!n)return;var home=@json(setting('company.state',''));function paint(){var v=s.value;if(!v){n.textContent=home?'Select the supply state to see the GST split.':'Set your registered state in Settings → Branding for auto split.';return;}n.textContent=(home&&v===home)?'Intra-state supply: CGST + SGST will apply.':'Inter-state supply: IGST will apply.';}s.addEventListener('change',paint);paint();})();</script>
@include('invoices.tds-block', ['tds' => null])
<div class="form-actions"><button class="btn" type="submit">Create invoice ({{ $prefix }}…)</button><a class="btn sec" href="{{ route('invoices.index') }}">Cancel</a></div>
</form></div>
@endsection
