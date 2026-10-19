@extends('layouts.app')
@section('title','New proposal')
@section('content')
<div class="card" style="max-width:780px">
<h3>New proposal</h3>
<form method="POST" action="{{ route('proposals.store') }}" enctype="multipart/form-data">@csrf
<label class="f">Propose to <span class="req">*</span></label>
<select class="in" name="client_id" required autofocus>@foreach($clients as $c)<option value="{{ $c->id }}" {{ (string)($default_client ?? '')===(string)$c->id?'selected':'' }}>{{ $c->company ? $c->company.' ('.$c->name.')' : $c->name }}{{ $c->gstin ? ' · '.$c->gstin : '' }}</option>@endforeach</select>
<label class="f">Project <span class="hint">(optional — links costs & files)</span></label>
<select class="in" name="project_id"><option value="">No project</option>@foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
@if(auth()->user()->canManageInvoices())
<label class="f">Assign to <span class="hint">(managers assign staff)</span></label>
<select class="in" name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>@endforeach</select>
@endif
<div class="form-grid">
<div><label class="f">Issue date</label><input class="in" type="date" name="issue_date" value="{{ date('Y-m-d') }}"></div>
<div><label class="f">Valid until</label><input class="in" type="date" name="valid_until" value="{{ date('Y-m-d', strtotime('+'.(int) setting('proposal.valid_days', 15).' days')) }}"><div class="fhint">Defaults to {{ setting('proposal.valid_days', 15) }} days (Settings → Billing). Client must sign before this date.</div></div>
</div>
<div class="form-grid">
<div><label class="f">Place of supply</label><select class="in" name="place_of_supply"><option value="">Select state</option>@foreach(indian_states() as $st)<option value="{{ $st }}" {{ old('place_of_supply', $proposal->place_of_supply ?? '')===$st?'selected':'' }}>{{ $st }}</option>@endforeach</select><div class="fhint">Same state as yours: CGST+SGST. Different: IGST.@if(!setting('company.state')) Set your registered state in Settings → Branding for auto split.@endif</div></div>
<div><label class="f">Discount (₹)</label><input class="in" type="number" step="0.01" min="0" name="discount" value="{{ old('discount', 0) }}" placeholder="0.00"></div>
</div>
@if(setting('tax.enable_gst','1')==='1')
<div class="fsec">Default GST for new lines (each line can differ)</div>
<div class="pills" id="gst-defaults">
@foreach([0,5,12,18,28] as $r)
<label><input type="radio" name="gst_rate" value="{{ $r }}" {{ (string)$proposal->gst_rate===(string)$r?'checked':'' }}><span>{{ $r }}%</span></label>
@endforeach
</div>
@else
<input type="hidden" name="gst_rate" value="0">
<p class="fhint">GST is disabled in Settings → Tax, so this proposal is zero-rated.</p>
@endif
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:14px"><input type="checkbox" name="reverse_charge" value="1" style="width:auto;margin:0"> Reverse charge applies (recipient pays GST)</label>
<div class="fsec">What are you pitching?</div>
@include('products.picker')
<div id="items" data-billing="1"><div class="item-row"><input class="in" name="items[0][description]" placeholder="Service description" required autofocus>
<div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><div style="flex:0 0 80px"><label class="f">Qty</label><input class="in" name="items[0][qty]" type="number" step="0.01" value="1" style="margin-bottom:0"></div>
<div style="flex:0 0 80px"><label class="f">Unit</label><input class="in" name="items[0][unit]" value="NOS" list="unit-list" style="margin-bottom:0"></div>
<div style="flex:0 0 110px"><label class="f">HSN/SAC</label><input class="in" name="items[0][hsn_code]" placeholder="9983…" style="margin-bottom:0"></div>
<div style="flex:1;min-width:110px"><label class="f">Rate (Rs.)</label><input class="in" name="items[0][rate]" type="number" step="0.01" min="0" value="0" style="margin-bottom:0"></div>
<div style="flex:0 0 70px"><label class="f">Disc%</label><input class="in" name="items[0][discount_percent]" type="number" step="0.01" min="0" max="100" value="0" style="margin-bottom:0"></div>
<div style="flex:0 0 90px"><label class="f">GST%</label><select class="in" name="items[0][tax_rate]" style="margin-bottom:0">@foreach([0,5,12,18,28] as $r)<option value="{{ $r }}" {{ (string)($proposal->gst_rate ?? 18)===(string)$r?'selected':'' }}>{{ $r }}%</option>@endforeach</select></div>
<div style="flex:0 0 100px"><label class="f">Bills</label><select class="in" name="items[0][billing]" style="margin-bottom:0"><option value="once">One-time</option><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></div></div></div></div>
<datalist id="unit-list">@foreach(setting_lines('invoice.units',['NOS','HRS','DAY','KGS','MTR','LTR','SET','JOB']) as $u)<option>{{ $u }}</option>@endforeach</datalist>
<button type="button" class="btn sec sm" id="add-item">+ Add another service</button>
<div class="inv-total" id="inv-total" style="margin-top:12px"></div>
<details class="adv"><summary>Notes (optional — shown on the proposal)</summary>
<div style="margin-top:10px"><textarea class="in" name="notes" rows="2" style="margin-bottom:0" placeholder="Cover note for the client…">{{ old('notes') }}</textarea></div></details>
<details class="adv" open><summary>Scope of work (what you will do)</summary>
<div style="margin-top:10px"><textarea class="in" name="scope" rows="4" style="margin-bottom:0" placeholder="One deliverable per line, e.g. Site survey and drawings…">{{ old('scope') }}</textarea>@include('proposals.snippet-attach',['field'=>'scope','kind'=>'scope','label'=>'Scope'])</div></details>
<details class="adv" open><summary>Expected results (what the client gets)</summary>
<div style="margin-top:10px"><textarea class="in" name="results" rows="3" style="margin-bottom:0" placeholder="Measurable outcomes, e.g. 30% lower power bills…">{{ old('results') }}</textarea>@include('proposals.snippet-attach',['field'=>'results','kind'=>'results','label'=>'Results'])</div></details>
<details class="adv"><summary>Terms (printed under the signature block)</summary>
<div style="margin-top:10px"><textarea class="in" name="terms" rows="3" style="margin-bottom:0" placeholder="Payment terms, validity, scope notes…">{{ old('terms', $proposal->terms ?? '') }}</textarea>@include('proposals.snippet-attach',['field'=>'terms','kind'=>'terms','label'=>'Terms'])</div></details>
<details class="adv"><summary>Cover art (pitch title slide)</summary>
<div style="margin-top:10px"><input class="in" type="file" name="cover" accept="image/*" style="margin-bottom:0"></div></details>
<div class="form-actions"><button class="btn" type="submit">Create proposal ({{ $prefix }}…)</button><a class="btn sec" href="{{ route('proposals.index') }}">Cancel</a></div>
</form></div>
@endsection
