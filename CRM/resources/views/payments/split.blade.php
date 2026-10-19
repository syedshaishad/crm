@extends('layouts.app')
@section('title','Split payment')
@section('content')
<div class="card" style="max-width:760px">
<h3>Split one receipt across invoices</h3>
<p class="fhint">One cheque / transfer paying several bills? Allocate it here — oldest due first. Each line becomes its own receipt with the same reference.</p>
<form method="GET" action="{{ route('payments.split') }}" style="display:flex;gap:8px;align-items:end;margin-bottom:12px">
<div style="flex:1"><label class="f">Client</label><select class="in" name="client_id" onchange="this.form.submit()" style="margin:0">@foreach($clients as $c)<option value="{{ $c->id }}" {{ ($client->id ?? null)===$c->id?'selected':'' }}>{{ $c->company ?: $c->name }}</option>@endforeach</select></div>
</form>
@if($client)
<form method="POST" action="{{ route('payments.splitStore') }}">@csrf
<input type="hidden" name="client_id" value="{{ $client->id }}">
@forelse($open as $i)
<div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
<div style="flex:2"><b>{{ $i->number }}</b><div class="hint">due {{ $i->due_date ? d($i->due_date) : '—' }} · balance {{ money($i->balance()) }}</div></div>
<div style="flex:1"><input class="in alloc" style="margin:0" type="number" step="0.01" min="0" max="{{ $i->balance() }}" name="alloc[{{ $i->id }}]" data-balance="{{ $i->balance() }}" placeholder="0.00"></div>
</div>
@empty
<p class="hint">No open invoices for this client — nothing to split.</p>
@endforelse
@if($open->isNotEmpty())
<div class="form-grid">
<div><label class="f">Total received</label><input class="in" type="number" step="0.01" min="0.01" id="split-total" placeholder="0.00"></div>
<div><label class="f">Paid on <span class="req">*</span></label><input class="in" type="date" name="paid_on" value="{{ date('Y-m-d') }}" required></div>
</div>
<div style="margin-bottom:10px"><button type="button" class="btn sec sm" id="split-auto">Auto-fill oldest first</button> <span class="hint" id="split-left"></span></div>
<div class="fsec">How was it paid?</div>
<div class="pills">
@foreach(setting_lines('payments.methods',['cash','bank','upi','razorpay','other']) as $v)
<label><input type="radio" name="method" value="{{ $v }}" {{ $v===setting('payments.default_method','upi')?'checked':'' }}><span>{{ ucfirst($v) }}</span></label>
@endforeach
</div>
<label class="f">Reference</label><input class="in" name="reference" placeholder="Cheque no / UTR…">
<div class="form-actions"><button class="btn" type="submit">Save split payment</button><a class="btn sec" href="{{ route('payments.index') }}">Cancel</a></div>
@endif
</form>
@endif
</div>
<script>
(function(){
var total = document.getElementById('split-total'), btn = document.getElementById('split-auto'), left = document.getElementById('split-left');
if (!btn) return;
function sum(){ var s = 0; document.querySelectorAll('.alloc').forEach(function(i){ s += parseFloat(i.value || '0'); }); return s; }
function paint(){ var t = parseFloat(total.value || '0'); left.textContent = t > 0 ? 'Allocated ' + sum().toFixed(2) + ' of ' + t.toFixed(2) : ''; }
btn.addEventListener('click', function(){
  var t = parseFloat(total.value || '0'), rows = Array.prototype.slice.call(document.querySelectorAll('.alloc'));
  rows.forEach(function(i){ i.value = ''; });
  rows.forEach(function(i){
    if (t <= 0) return;
    var b = parseFloat(i.dataset.balance || '0'), give = Math.min(b, t);
    i.value = give.toFixed(2); t = Math.round((t - give) * 100) / 100;
  });
  paint();
});
document.querySelectorAll('.alloc').forEach(function(i){ i.addEventListener('input', paint); });
if (total) total.addEventListener('input', paint);
})();
</script>
@endsection
