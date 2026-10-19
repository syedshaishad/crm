@extends('layouts.app')
@section('title','Edit '.$invoice->number)
@section('content')
<div class="card" style="max-width:760px">
<h3>Edit {{ $invoice->number }}</h3>
@if(in_array($invoice->status,['paid','cancelled']))<div class="err">This invoice is <b>{{ $invoice->status }}</b> — changing amounts will update its status to match what is paid.</div>@endif
<p class="fhint">Change the header, or append new lines below. Saved lines are kept as-is.</p>
<form method="POST" action="{{ route('invoices.update',$invoice) }}">@csrf @method('PUT')
<div class="form-grid">
<div><label class="f">Bill to <span class="req">*</span></label><select class="in" name="client_id">@foreach($clients as $c)<option value="{{ $c->id }}" {{ (string)$invoice->client_id===(string)$c->id?'selected':'' }}>{{ $c->company ? $c->company.' ('.$c->name.')' : $c->name }}</option>@endforeach</select></div>
<div><label class="f">Project</label><select class="in" name="project_id"><option value="">—</option>@foreach($projects as $p)<option value="{{ $p->id }}" {{ (string)$invoice->project_id===(string)$p->id?'selected':'' }}>{{ $p->name }}</option>@endforeach</select></div>
@if(auth()->user()->canManageInvoices())
<div><label class="f">Assigned to</label><select class="in" name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $u)<option value="{{ $u->id }}" {{ (string)old('assigned_to',$invoice->assigned_to)===(string)$u->id?'selected':'' }}>{{ $u->name }} ({{ $u->role }})</option>@endforeach</select></div>
<div><label class="f">Prepared by</label><input class="in" value="{{ $invoice->creator->name ?? '—' }}" disabled></div>
@else
<div><label class="f">Assigned to</label><input class="in" value="{{ $invoice->assignee->name ?? 'Unassigned' }}" disabled></div>
@endif
<div><label class="f">Issue date <span class="req">*</span></label><input class="in" type="date" name="issue_date" value="{{ old('issue_date', $invoice->issue_date ? $invoice->issue_date->format('Y-m-d') : '') }}" required></div>
<div><label class="f">Due date</label><input class="in" type="date" name="due_date" value="{{ old('due_date', $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '') }}"></div>
</div>
@if(setting('tax.enable_gst','1')==='1')
<div class="fsec">GST rate (default for new lines)</div>
<div class="pills" id="gst-defaults">
@foreach([0,5,12,18,28] as $r)
<label><input type="radio" name="gst_rate" value="{{ $r }}" {{ (string)old('gst_rate',$invoice->gst_rate)===(string)$r?'checked':'' }}><span>{{ $r }}%</span></label>
@endforeach
</div>
@else
<input type="hidden" name="gst_rate" value="0">
<p class="fhint">GST is disabled in Settings → Tax, so lines are zero-rated.</p>
@endif
<label class="f">Status</label>
<select class="in" name="status" style="max-width:240px">@foreach(['draft','sent','partial','paid','overdue','cancelled'] as $s)<option {{ $invoice->status===$s?'selected':'' }}>{{ $s }}</option>@endforeach</select>
<label class="f">Notes</label><textarea class="in" name="notes" rows="2">{{ old('notes',$invoice->notes) }}</textarea>
<div class="form-grid">
<div><label class="f">Currency</label><select class="in" name="currency" id="inv-currency">@foreach($currencies as $cu)<option value="{{ $cu }}" {{ ($invoice->currency ?? 'INR')===$cu?'selected':'' }}>{{ $cu }}</option>@endforeach</select></div>
<div><label class="f">Rate (1 <span id="inv-cur-code">{{ $invoice->currency ?? 'INR' }}</span> = ₹)</label><input class="in" type="number" step="0.000001" min="0.000001" name="exchange_rate" id="inv-fx" value="{{ $invoice->exchange_rate ?? 1 }}"></div>
</div>
<div class="form-grid">
<div><label class="f">Place of supply</label><select class="in" name="place_of_supply" id="inv-pos"><option value="">Select state</option>@foreach(indian_states() as $st)<option value="{{ $st }}" {{ old('place_of_supply',$invoice->place_of_supply)===$st?'selected':'' }}>{{ $st }}</option>@endforeach</select><div class="fhint" id="inv-tax-note" role="status"></div></div>
<div><label class="f">Discount (<span class="rate-cur">{{ $invoice->currency ?? 'Rs.' }}</span>)</label><input class="in" type="number" step="0.01" min="0" name="discount" value="{{ old('discount', round(($invoice->discount ?? 0) / max(0.000001, (float) ($invoice->exchange_rate ?? 1)), 2)) }}"></div>
<div><label class="f">E-way bill no <span class="hint">(12-digit, if goods moved)</span></label><input class="in" name="eway_bill_no" value="{{ old('eway_bill_no',$invoice->eway_bill_no) }}" maxlength="20"></div>
</div>
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:14px"><input type="checkbox" name="reverse_charge" value="1" {{ old('reverse_charge',$invoice->reverse_charge) ? 'checked' : '' }} style="width:auto;margin:0"> Reverse charge applies</label>
<script>(function(){var s=document.getElementById('inv-pos'),n=document.getElementById('inv-tax-note');if(!s||!n)return;var home=@json(setting('company.state',''));function paint(){var v=s.value;if(!v){n.textContent=home?'Select the supply state to see the GST split.':'Set your registered state in Settings → Branding for auto split.';return;}n.textContent=(home&&v===home)?'Intra-state supply: CGST + SGST will apply.':'Inter-state supply: IGST will apply.';}s.addEventListener('change',paint);paint();})();</script>
<div class="fsec">Items ({{ money($invoice->total) }} total so far) — edit, remove, or add below</div>
@include('products.picker')
<div id="items">
@foreach($invoice->items as $it)
<div class="item-row">
<input class="in" name="items_existing[{{ $it->id }}][description]" value="{{ old('items_existing.'.$it->id.'.description', $it->description) }}" required>
<div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><div style="flex:0 0 80px"><label class="f">Qty</label><input class="in" name="items_existing[{{ $it->id }}][qty]" type="number" step="0.01" value="{{ old('items_existing.'.$it->id.'.qty', $it->qty) }}" style="margin-bottom:0"></div>
<div style="flex:0 0 80px"><label class="f">Unit</label><input class="in" name="items_existing[{{ $it->id }}][unit]" value="{{ old('items_existing.'.$it->id.'.unit', $it->unit ?? 'NOS') }}" list="unit-list" style="margin-bottom:0"></div>
<div style="flex:0 0 110px"><label class="f">HSN/SAC</label><input class="in" name="items_existing[{{ $it->id }}][hsn_code]" value="{{ old('items_existing.'.$it->id.'.hsn_code', $it->hsn_code) }}" style="margin-bottom:0"></div>
<div style="flex:1;min-width:110px"><label class="f">Rate (<span class="rate-cur">{{ $invoice->currency ?? 'Rs.' }}</span>)</label><input class="in" name="items_existing[{{ $it->id }}][rate]" type="number" step="0.01" min="0" value="{{ old('items_existing.'.$it->id.'.rate', round($it->rate / max(0.000001, (float) ($invoice->exchange_rate ?? 1)), 2)) }}" style="margin-bottom:0"></div>
<div style="flex:0 0 70px"><label class="f">Disc%</label><input class="in" name="items_existing[{{ $it->id }}][discount_percent]" type="number" step="0.01" min="0" max="100" value="{{ old('items_existing.'.$it->id.'.discount_percent', $it->discount_percent ?? 0) }}" style="margin-bottom:0"></div>
<div style="flex:0 0 90px"><label class="f">GST%</label><select class="in" name="items_existing[{{ $it->id }}][tax_rate]" style="margin-bottom:0">@foreach([0,5,12,18,28] as $r)<option value="{{ $r }}" {{ (string)old('items_existing.'.$it->id.'.tax_rate', $it->tax_rate ?? $invoice->gst_rate)===(string)$r?'selected':'' }}>{{ $r }}%</option>@endforeach</select></div>
<label class="hint" style="display:flex;gap:6px;align-items:center;margin-bottom:10px;white-space:nowrap"><input type="checkbox" name="remove_items[]" value="{{ $it->id }}" style="width:auto;margin:0"> Remove</label></div>
</div>
@endforeach
</div>
<button type="button" class="btn sec sm" id="add-item">+ Add another item</button>
<datalist id="unit-list">@foreach(setting_lines('invoice.units',['NOS','HRS','DAY','KGS','MTR','LTR','SET','JOB']) as $u)<option>{{ $u }}</option>@endforeach</datalist>
<div class="inv-total" id="inv-total" style="margin-top:12px"></div>
@include('invoices.tds-block', ['tds' => $tds ?? null])
<div class="form-actions"><button class="btn" type="submit">Save changes</button><a class="btn sec" href="{{ route('invoices.show',$invoice) }}">Cancel</a></div>
</form></div>
@endsection
