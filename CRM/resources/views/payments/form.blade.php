@extends('layouts.app')
@section('title','Record payment')
@section('content')
<div class="card" style="max-width:680px">
<h3>Record payment</h3>
<p class="fhint">Pick an invoice and the client + outstanding amount fill in automatically.</p>
<form method="POST" action="{{ route('payments.store') }}">@csrf
<label class="f">Invoice</label>
<select class="in" name="invoice_id" id="pay-invoice"><option value="">No invoice (advance payment)</option>@foreach($invoices as $i)<option value="{{ $i->id }}" data-client="{{ $i->client_id }}" data-balance="{{ $i->balance() }}" {{ (string)($payment->invoice_id??request('invoice_id'))===(string)$i->id?'selected':'' }}>{{ $i->number }} — {{ money($i->total) }} (due {{ money($i->balance()) }})</option>@endforeach</select>
<label class="f">Client <span class="req">*</span></label>
<select class="in" name="client_id" id="pay-client" required>@foreach($clients as $c)<option value="{{ $c->id }}" {{ (string)($payment->client_id??request('client_id'))===(string)$c->id?'selected':'' }}>{{ $c->name }}</option>@endforeach</select>
<div class="form-grid">
<div><label class="f">Amount (₹) <span class="req">*</span></label><input class="in" type="number" step="0.01" min="0.01" name="amount" id="pay-amount" required autofocus placeholder="0.00"></div>
<div><label class="f">Paid on <span class="req">*</span></label><input class="in" type="date" name="paid_on" value="{{ date('Y-m-d') }}" required></div>
</div>
<div class="fsec">How was it paid?</div>
<div class="pills">
@foreach(array_unique(array_merge(setting_lines('payments.methods',['cash','bank','upi','razorpay','loyalty','other']),['loyalty'])) as $v)
<label><input type="radio" name="method" value="{{ $v }}" {{ $v===setting('payments.default_method','upi')?'checked':'' }}><span>{{ ucfirst($v) }}</span></label>
@endforeach
</div>
<p class="fhint">Loyalty redeems the client's earned balance — capped automatically. Earn rate lives in Settings → Billing.</p>
<details class="adv"><summary>Reference / notes (optional)</summary>
<div style="margin-top:10px"><label class="f">Reference</label><input class="in" name="reference" style="margin-bottom:0" placeholder="UTR, cheque no…"></div>
<div style="margin-top:10px"><label class="f">Notes</label><textarea class="in" name="notes" rows="2" style="margin-bottom:0" placeholder="Shown on the receipt…"></textarea></div></details>
@if(setting('tax.tds_enabled','0')==='1')
<details class="adv"><summary>TDS deducted by client (optional)</summary>
<div style="margin-top:10px" class="form-grid">
<div><label class="f">Section</label><select class="in" name="tds_section" id="tds-section" style="margin-bottom:0"><option value="">No TDS</option>@foreach(tds_sections() as $code => $rate)<option value="{{ $code }}" data-rate="{{ $rate }}">{{ $code }} · {{ $rate }}%</option>@endforeach</select></div>
<div><label class="f">TDS amount (₹)</label><input class="in" type="number" step="0.01" min="0" name="tds_amount" id="tds-amount" style="margin-bottom:0" placeholder="0.00"></div>
</div>
<input type="hidden" name="tds_rate" id="tds-rate" value="">
<p class="fhint" id="tds-hint" style="margin:8px 0 0">Cash + TDS settles the invoice together — TDS is tracked for 26AS.</p></details>
<script>
(function(){
var sec = document.getElementById('tds-section');
if (!sec) return;
var amt = document.getElementById('pay-amount'), tds = document.getElementById('tds-amount'), rate = document.getElementById('tds-rate'), hint = document.getElementById('tds-hint');
function fill(){
  var r = parseFloat((sec.selectedOptions[0] || {}).dataset ? sec.selectedOptions[0].dataset.rate || '0' : '0');
  rate.value = r || '';
  var cash = parseFloat(amt.value || '0');
  if (sec.value && cash > 0) tds.value = (cash * r / 100).toFixed(2);
  else if (!sec.value) tds.value = '';
}
sec.addEventListener('change', fill); amt.addEventListener('input', fill);
})();
</script>
@endif
<div class="form-actions"><button class="btn" type="submit">Save payment</button><a class="btn sec" href="{{ route('payments.index') }}">Cancel</a></div></form>
@if($razorpay_enabled)<hr><h4>…or collect online via Razorpay</h4>
<p class="hint">Creates a secure order, then opens Razorpay checkout. The successful payment fills this form for you.</p>
<button type="button" class="btn" id="rzp-pay">Pay with Razorpay</button>
<div class="err" id="rzp-err" style="display:none;margin-top:10px"></div>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function(){
var btn = document.getElementById('rzp-pay');
if (!btn || typeof Razorpay === 'undefined') return;
btn.addEventListener('click', async () => {
  const amt = parseFloat(document.querySelector('[name=amount]').value || '0');
  const inv = document.querySelector('[name=invoice_id]').value || null;
  const errBox = document.getElementById('rzp-err');
  const fail = function (msg) { errBox.textContent = msg; errBox.style.display = 'block'; errBox.scrollIntoView({block: 'nearest'}); };
  if (!amt || amt < 1) { fail('Enter an amount of at least ₹1.'); return; }
  errBox.style.display = 'none';
  const res = await fetch("{{ route('payments.razorpay.order') }}", {
    method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': "{{ csrf_token() }}"},
    body: JSON.stringify({invoice_id: inv, amount: amt})
  });
  if (!res.ok) { fail('Could not create Razorpay order. Check your keys in Settings → Integrations.'); return; }
  const o = await res.json();
  new Razorpay({key: o.key, amount: amt * 100, currency: "{{ setting('app.currency', 'INR') }}", order_id: o.order_id, name: "{{ $brand['company_name'] ?? 'Invoice payment' }}",
    handler: function (r) {
      var method = document.querySelector('[name=method][value=razorpay]');
      if (method) method.checked = true;
      const form = document.querySelector('.card form');
      [['razorpay_payment_id', r.razorpay_payment_id], ['razorpay_order_id', r.razorpay_order_id], ['razorpay_signature', r.razorpay_signature]].forEach(function ([k, v]) {
        const f = document.createElement('input'); f.type='hidden'; f.name=k; f.value=v || ''; form.appendChild(f);
      });
      form.submit();
    }}).open();
});
})();
</script>
@endif
</div>
@endsection
