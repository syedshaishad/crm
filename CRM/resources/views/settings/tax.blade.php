@extends('layouts.app')
@section('title','Tax')
@section('content')
<div class="card" style="max-width:640px"><h3>GST / tax settings</h3>
<p class="fhint">Applies to new invoices. Existing invoices keep their own rates.</p>
<form method="POST" action="{{ route('settings.tax.store') }}">@csrf
<div class="fsec">Default GST rate</div>
<div class="pills">
@foreach([0,5,12,18,28] as $r)
<label><input type="radio" name="tax_default_gst_rate" value="{{ $r }}" {{ (string)old('tax_default_gst_rate',$s->get('tax.default_gst_rate',18))===(string)$r?'checked':'' }}><span>{{ $r }}%</span></label>
@endforeach
</div>
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:16px"><input type="checkbox" name="tax_enable_gst" value="1" {{ $s->get('tax.enable_gst','1')==='1'?'checked':'' }} style="width:auto;margin:0"> Enable GST on invoices (off = zero-rated)</label>
<div class="fsec">TDS (tax deducted at source)</div>
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:12px"><input type="checkbox" name="tax_tds_enabled" value="1" {{ $s->get('tax.tds_enabled','0')==='1'?'checked':'' }} style="width:auto;margin:0"> Enable TDS — record client deductions on payments and vendor deductions on expenses</label>
<label class="f">Sections & default rates <span class="hint">one per line as CODE:RATE</span></label><textarea class="in" name="tax_tds_sections" rows="3" placeholder="194C:2&#10;194J:10">{{ old('tax_tds_sections', $s->get('tax.tds_sections', "194C:2\n194J:10\n194I:2\n194H:2\n194A:10")) }}</textarea>
<div class="form-actions"><button class="btn" type="submit">Save tax settings</button><a class="btn sec" href="{{ route('settings.index') }}">Cancel</a></div></form></div>
@endsection
