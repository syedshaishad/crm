@extends('layouts.app')
@section('title',($expense->exists ? 'Edit ' : 'New ').'expense')
@section('content')
<div class="card" style="max-width:680px">
<h3>{{ $expense->exists ? 'Edit expense' : 'New expense' }}</h3>
<form method="POST" action="{{ $expense->exists ? route('expenses.update',$expense) : route('expenses.store') }}" enctype="multipart/form-data">@csrf @if($expense->exists)@method('PUT')@endif
<div class="form-grid">
<div><label class="f">What for? <span class="req">*</span></label><input class="in" name="title" value="{{ old('title',$expense->title) }}" required autofocus placeholder="e.g. Domain renewal"></div>
<div><label class="f">Amount (₹) <span class="req">*</span></label><input class="in" type="number" step="0.01" min="0" name="amount" value="{{ old('amount',$expense->amount) }}" required placeholder="0.00"></div>
</div>
<div class="form-grid">
<div><label class="f">Project <span class="hint">(optional — tracks cost vs budget)</span></label><select class="in" name="project_id"><option value="">General (no project)</option>@foreach($projects as $p)<option value="{{ $p->id }}" {{ (string)old('project_id',$expense->project_id)===(string)$p->id?'selected':'' }}>{{ $p->name }}</option>@endforeach</select></div>
<div><label class="f">Spent on <span class="req">*</span></label><input class="in" type="date" name="spent_on" value="{{ old('spent_on', $expense->spent_on ? $expense->spent_on->format('Y-m-d') : ($expense->exists ? '' : date('Y-m-d'))) }}" required></div>
</div>
<label class="f">Category</label><input class="in" name="category" list="exp-cats" value="{{ old('category',$expense->category ?? 'general') }}" placeholder="Pick or type…">
<datalist id="exp-cats">@foreach(setting_lines('expenses.categories',['general','travel','software','hosting','salary','marketing','office']) as $c)<option>{{ $c }}</option>@endforeach</datalist>
<div class="fsec">How was it paid?</div>
<div class="pills">
@foreach(setting_lines('expenses.methods',['cash','bank','upi','card','other']) as $v)
<label><input type="radio" name="method" value="{{ $v }}" {{ old('method',$expense->method ?? 'cash')===$v?'checked':'' }}><span>{{ ucfirst($v) }}</span></label>
@endforeach
</div>
<details class="adv"><summary>Vendor, receipt & notes (optional)</summary>
<div style="margin-top:10px">
<label class="f">Vendor</label><input class="in" name="vendor" value="{{ old('vendor',$expense->vendor) }}" placeholder="Who was paid?">
<label class="f">Vendor bill no / reference</label><input class="in" name="reference" value="{{ old('reference',$expense->reference) }}" placeholder="Bill no, UTR…">
<label class="f">Receipt</label>
@if($expense->receipt_path)<div class="fhint" style="margin-bottom:6px"><a href="{{ asset('storage/'.$expense->receipt_path) }}" target="_blank">View current receipt →</a> (uploading replaces it)</div>@endif
<input class="in" type="file" name="receipt" accept="image/*,.pdf">
<label class="f">Notes</label><textarea class="in" name="notes" rows="2">{{ old('notes',$expense->notes) }}</textarea>
</div></details>
@if(setting('tax.tds_enabled','0')==='1')
@php $etds = $expense->exists ? \Illuminate\Support\Facades\DB::table('tds_entries')->where('expense_id',$expense->id)->where('kind','payable')->first() : null; @endphp
<details class="adv"><summary>TDS deducted on this expense (optional)</summary>
<div style="margin-top:10px" class="form-grid">
<div><label class="f">Section</label><select class="in" name="tds_section" style="margin-bottom:0"><option value="">No TDS</option>@foreach(tds_sections() as $code => $rate)<option value="{{ $code }}" {{ old('tds_section', $etds->section ?? '')===$code?'selected':'' }}>{{ $code }} · {{ $rate }}%</option>@endforeach</select></div>
<div><label class="f">TDS amount (₹)</label><input class="in" type="number" step="0.01" min="0" name="tds_amount" style="margin-bottom:0" value="{{ old('tds_amount', $etds->amount ?? '') }}" placeholder="0.00"></div>
</div></details>
@endif
<div class="form-actions"><button class="btn" type="submit">{{ $expense->exists ? 'Save changes' : 'Add expense' }}</button><a class="btn sec" href="{{ route('expenses.index') }}">Cancel</a></div>
</form></div>
@endsection
