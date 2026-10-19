@extends('layouts.app')
@section('title','Edit '.$proposal->number)
@section('content')
<div class="card" style="max-width:760px">
<h3>Edit {{ $proposal->number }}</h3>
<p class="fhint">Draft only — once sent, the proposal is locked for the client.</p>
@php $dlim = (float) setting('proposal.discount_limit', 20); $dpct = (float) $proposal->subtotal > 0 ? round((float) $proposal->discount / (float) $proposal->subtotal * 100, 1) : 0; @endphp
@if($dlim > 0 && $dpct > $dlim)<div class="alert warn">Discount is {{ $dpct }}% — over the {{ $dlim }}% staff limit. A manager must send this proposal.</div>@endif
<form method="POST" action="{{ route('proposals.update',$proposal) }}" enctype="multipart/form-data">@csrf @method('PUT')
<div class="form-grid">
<div><label class="f">Propose to <span class="req">*</span></label><select class="in" name="client_id">@foreach($clients as $c)<option value="{{ $c->id }}" {{ (string)$proposal->client_id===(string)$c->id?'selected':'' }}>{{ $c->company ? $c->company.' ('.$c->name.')' : $c->name }}</option>@endforeach</select></div>
<div><label class="f">Project</label><select class="in" name="project_id"><option value="">—</option>@foreach($projects as $p)<option value="{{ $p->id }}" {{ (string)$proposal->project_id===(string)$p->id?'selected':'' }}>{{ $p->name }}</option>@endforeach</select></div>
@if(auth()->user()->canManageInvoices())
<div><label class="f">Assigned to</label><select class="in" name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $u)<option value="{{ $u->id }}" {{ (string)old('assigned_to',$proposal->assigned_to)===(string)$u->id?'selected':'' }}>{{ $u->name }} ({{ $u->role }})</option>@endforeach</select></div>
<div><label class="f">Prepared by</label><input class="in" value="{{ $proposal->creator->name ?? '—' }}" disabled></div>
@else
<div><label class="f">Assigned to</label><input class="in" value="{{ $proposal->assignee->name ?? 'Unassigned' }}" disabled></div>
@endif
<div><label class="f">Issue date</label><input class="in" type="date" name="issue_date" value="{{ old('issue_date', $proposal->issue_date ? $proposal->issue_date->format('Y-m-d') : '') }}"></div>
<div><label class="f">Valid until</label><input class="in" type="date" name="valid_until" value="{{ old('valid_until', $proposal->valid_until ? $proposal->valid_until->format('Y-m-d') : '') }}"></div>
</div>
@if(setting('tax.enable_gst','1')==='1')
<div class="fsec">GST rate (default for new lines)</div>
<div class="pills" id="gst-defaults">
@foreach([0,5,12,18,28] as $r)
<label><input type="radio" name="gst_rate" value="{{ $r }}" {{ (string)old('gst_rate',$proposal->gst_rate)===(string)$r?'checked':'' }}><span>{{ $r }}%</span></label>
@endforeach
</div>
@else
<input type="hidden" name="gst_rate" value="0">
<p class="fhint">GST is disabled in Settings → Tax, so lines are zero-rated.</p>
@endif
<div class="form-grid">
<div><label class="f">Place of supply</label><select class="in" name="place_of_supply"><option value="">Select state</option>@foreach(indian_states() as $st)<option value="{{ $st }}" {{ old('place_of_supply',$proposal->place_of_supply)===$st?'selected':'' }}>{{ $st }}</option>@endforeach</select></div>
<div><label class="f">Discount (₹)</label><input class="in" type="number" step="0.01" min="0" name="discount" value="{{ old('discount',$proposal->discount ?? 0) }}"></div>
</div>
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:14px"><input type="checkbox" name="reverse_charge" value="1" {{ old('reverse_charge',$proposal->reverse_charge) ? 'checked' : '' }} style="width:auto;margin:0"> Reverse charge applies</label>
<label class="f">Notes</label><textarea class="in" name="notes" rows="2">{{ old('notes',$proposal->notes) }}</textarea>
<label class="f">Scope of work <span class="hint">(what you will do)</span></label><textarea class="in" name="scope" rows="4" placeholder="One deliverable per line…">{{ old('scope',$proposal->scope) }}</textarea>@include('proposals.snippet-attach',['field'=>'scope','kind'=>'scope','label'=>'Scope'])
<label class="f">Expected results <span class="hint">(what the client gets)</span></label><textarea class="in" name="results" rows="3" placeholder="Measurable outcomes…">{{ old('results',$proposal->results) }}</textarea>@include('proposals.snippet-attach',['field'=>'results','kind'=>'results','label'=>'Results'])
<label class="f">Cover art <span class="hint">(pitch title slide)</span></label>@if($proposal->cover_path)<img src="{{ asset('storage/'.$proposal->cover_path) }}" alt="cover" style="max-height:90px;border-radius:10px;margin-bottom:8px">@endif<input class="in" type="file" name="cover" accept="image/*">
<label class="f">Terms</label><textarea class="in" name="terms" rows="3">{{ old('terms',$proposal->terms) }}</textarea>@include('proposals.snippet-attach',['field'=>'terms','kind'=>'terms','label'=>'Terms'])
<div class="fsec">Services ({{ money($proposal->total) }} total so far) — edit, remove, or add below</div>
@include('products.picker')
<div id="items" data-billing="1">
@foreach($proposal->items as $it)
<div class="item-row">
<input class="in" name="items_existing[{{ $it->id }}][description]" value="{{ old('items_existing.'.$it->id.'.description', $it->description) }}" required>
<div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><div style="flex:0 0 80px"><label class="f">Qty</label><input class="in" name="items_existing[{{ $it->id }}][qty]" type="number" step="0.01" value="{{ old('items_existing.'.$it->id.'.qty', $it->qty) }}" style="margin-bottom:0"></div>
<div style="flex:0 0 80px"><label class="f">Unit</label><input class="in" name="items_existing[{{ $it->id }}][unit]" value="{{ old('items_existing.'.$it->id.'.unit', $it->unit ?? 'NOS') }}" list="unit-list" style="margin-bottom:0"></div>
<div style="flex:0 0 110px"><label class="f">HSN/SAC</label><input class="in" name="items_existing[{{ $it->id }}][hsn_code]" value="{{ old('items_existing.'.$it->id.'.hsn_code', $it->hsn_code) }}" style="margin-bottom:0"></div>
<div style="flex:1;min-width:110px"><label class="f">Rate (Rs.)</label><input class="in" name="items_existing[{{ $it->id }}][rate]" type="number" step="0.01" min="0" value="{{ old('items_existing.'.$it->id.'.rate', $it->rate) }}" style="margin-bottom:0"></div>
<div style="flex:0 0 70px"><label class="f">Disc%</label><input class="in" name="items_existing[{{ $it->id }}][discount_percent]" type="number" step="0.01" min="0" max="100" value="{{ old('items_existing.'.$it->id.'.discount_percent', $it->discount_percent ?? 0) }}" style="margin-bottom:0"></div>
<div style="flex:0 0 90px"><label class="f">GST%</label><select class="in" name="items_existing[{{ $it->id }}][tax_rate]" style="margin-bottom:0">@foreach([0,5,12,18,28] as $r)<option value="{{ $r }}" {{ (string)old('items_existing.'.$it->id.'.tax_rate', $it->tax_rate ?? $proposal->gst_rate)===(string)$r?'selected':'' }}>{{ $r }}%</option>@endforeach</select></div>
<div style="flex:0 0 100px"><label class="f">Bills</label><select class="in" name="items_existing[{{ $it->id }}][billing]" style="margin-bottom:0">@foreach(['once'=>'One-time','monthly'=>'Monthly','yearly'=>'Yearly'] as $bv=>$bl)<option value="{{ $bv }}" {{ old('items_existing.'.$it->id.'.billing', $it->billing ?? 'once')===$bv?'selected':'' }}>{{ $bl }}</option>@endforeach</select></div>
<label class="hint" style="display:flex;gap:6px;align-items:center;margin-bottom:10px;white-space:nowrap"><input type="checkbox" name="remove_items[]" value="{{ $it->id }}" style="width:auto;margin:0"> Remove</label></div>
</div>
@endforeach
</div>
<button type="button" class="btn sec sm" id="add-item">+ Add another service</button>
<datalist id="unit-list">@foreach(setting_lines('invoice.units',['NOS','HRS','DAY','KGS','MTR','LTR','SET','JOB']) as $u)<option>{{ $u }}</option>@endforeach</datalist>
<div class="inv-total" id="inv-total" style="margin-top:12px"></div>
<div class="form-actions"><button class="btn" type="submit">Save changes</button><a class="btn sec" href="{{ route('proposals.show',$proposal) }}">Cancel</a></div>
</form></div>
@endsection
