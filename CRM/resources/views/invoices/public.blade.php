<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice {{ $invoice->number }} — {{ $brand['company_name'] ?? config('app.name') }}</title>
@if(!empty($brand['favicon_path']))<link rel="icon" href="{{ asset('storage/'.$brand['favicon_path']) }}">@else<link rel="icon" href="{{ asset('favicon.ico') }}">@endif
<link rel="stylesheet" href="{{ asset('assets/css/app.css').'?v=130' }}">
@include('brand.theme')
</head>
<body style="background:var(--bg)">
@php
  $invLogo = setting('invoice.logo_path') ?: ($brand['logo_path'] ?? '');
  $invDark = setting('invoice.logo_path') ? (setting('invoice.logo_dark_path') ?: ($brand['logo_dark_path'] ?? '')) : ($brand['logo_dark_path'] ?? '');
  $balance = $invoice->balance();
  $isPaid = $balance <= 0 && $invoice->total > 0;
  $isOver = $invoice->status === 'overdue' && $balance > 0;
  $companyState = setting('company.state', '');
  $buyerState = $invoice->client->state ?? '';
  $intra = $invoice->isIntraState();
  $halfRate = $invoice->gst_rate / 2;
@endphp
<div class="sheet" style="max-width:860px;margin-left:auto;margin-right:auto">
  <div class="topnav noprint" style="padding:0 0 12px">
    <span class="brand-pill" title="{{ $brand['company_name'] ?? config('app.name') }}">@include('brand.logo')</span>
    <span class="top-actions"><button class="btn sm" onclick="window.print()">Download / PDF</button></span>
  </div>
  @if($isPaid)
    <div class="due-banner paid">Paid in full: {{ money($invoice->paid_amount) }} received. Thank you.</div>
  @elseif($isOver)
    <div class="due-banner over">Overdue: {{ money($balance) }} was due {{ d($invoice->due_date, 'j F Y') }}.</div>
  @elseif($balance > 0)
    @if($invoice->due_date)
    <div class="due-banner due">{{ money($balance) }} due by {{ d($invoice->due_date, 'j F Y') }}.</div>
    @else
    <div class="due-banner due">{{ money($balance) }} due.</div>
    @endif
  @endif
  @if(config('razorpay.enabled') && $balance > 0 && $invoice->status !== 'cancelled')
  <div class="card noprint" id="pay-box">
    <div class="card-head"><h3>Pay online</h3><span class="hint"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:13px;height:13px;vertical-align:-2px"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg> Secure UPI / cards via Razorpay</span></div>
    <p class="hint" style="margin:0 0 10px">{{ $invoice->number }} · {{ money($balance) }} due{{ $invoice->due_date ? ' by '.d($invoice->due_date, 'j F Y') : '' }} · partial payments welcome</p>
    <div class="form-grid">
      <div><label class="f">Amount (₹)</label><input class="in" style="margin:0" id="pay-amt" type="number" step="0.01" min="1" max="{{ $balance }}" value="{{ $balance }}"></div>
      <div style="display:flex;align-items:end"><button class="btn brand" id="pay-now" style="width:100%;justify-content:center">Pay {{ money($balance) }}</button></div>
    </div>
    <div class="btnrow noprint" style="gap:6px;margin-top:8px"><button class="btn sec sm" data-pay-frac="0.25">25%</button><button class="btn sec sm" data-pay-frac="0.5">50%</button><button class="btn sec sm" data-pay-frac="1">Full</button><span class="hint">UPI apps, cards & netbanking accepted</span></div>
    <div class="err" id="pay-err" style="display:none;margin:12px 0 0"></div>
  </div>
  <div class="card noprint" id="pay-done" style="display:none;text-align:center">
    <h3>Payment received ✓</h3>
    <p class="hint" id="pay-done-sub"></p>
    <a class="btn sm" id="pay-receipt" href="#">View receipt</a>
  </div>
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <script>
  (function () {
    var btn = document.getElementById('pay-now');
    var amt = document.getElementById('pay-amt');
    var err = document.getElementById('pay-err');
    if (!btn) return;
    function fail(m) { err.textContent = m; err.style.display = 'block'; err.scrollIntoView({block: 'nearest'}); }
    document.querySelectorAll('[data-pay-frac]').forEach(function (c) {
      c.addEventListener('click', function () {
        amt.value = Math.max(1, Math.round({{ $balance }} * parseFloat(c.dataset.payFrac) * 100) / 100);
        err.style.display = 'none';
      });
    });
    btn.addEventListener('click', async function () {
      err.style.display = 'none';
      var a = Math.min(parseFloat(amt.value || '0'), {{ $balance }});
      if (!a || a < 1) { fail('Enter an amount of at least ₹1.'); return; }
      btn.disabled = true;
      btn.textContent = 'Starting secure checkout…';
      try {
        var or = await fetch("{{ route('invoices.pay.order', $invoice->public_token) }}", {
          method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({amount: a})
        });
        if (!or.ok) throw new Error('setup');
        var o = await or.json();
        btn.disabled = false;
        btn.textContent = 'Pay {{ money($balance) }}';
        if (typeof Razorpay === 'undefined') { fail('Payment window blocked. Check your connection or ad-blocker and retry.'); return; }
        new Razorpay({
          key: o.key, amount: Math.round(o.amount * 100), currency: '{{ setting('app.currency', 'INR') }}',
          order_id: o.order_id, name: "{{ $brand['company_name'] ?? config('app.name') }}",
          description: 'Invoice {{ $invoice->number }}',
          handler: async function (rp) {
            btn.disabled = true;
            btn.textContent = 'Confirming with your bank…';
            try {
              var vr = await fetch("{{ route('invoices.pay.verify', $invoice->public_token) }}", {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({amount: o.amount, razorpay_payment_id: rp.razorpay_payment_id, razorpay_order_id: rp.razorpay_order_id, razorpay_signature: rp.razorpay_signature})
              });
              var j = await vr.json();
              if (!vr.ok) { fail(j.error || 'Verification failed. If money left your account, contact us with the payment ID.'); return; }
              document.getElementById('pay-box').style.display = 'none';
              var d = document.getElementById('pay-done');
              d.style.display = 'block';
              document.getElementById('pay-done-sub').textContent = j.receipt_number + ' recorded.' + (j.balance > 0 ? ' Balance left: ' + j.balance.toLocaleString('en-IN', {minimumFractionDigits: 2}) : ' Invoice paid in full. Thank you!');
              document.getElementById('pay-receipt').href = j.receipt_url;
              d.scrollIntoView({block: 'nearest'});
            } catch (e) { fail('Could not confirm the payment. If money left your account, contact us with the payment ID.'); }
          }
        }).open();
      } catch (e) {
        btn.disabled = false;
        btn.textContent = 'Pay {{ money($balance) }}';
        fail('Could not start checkout. Please try again.');
      }
    });
  })();
  </script>
  @endif
  <div class="card">
  @include('invoices.doc-'.invoice_theme())
  </div>
  <p class="footer-note noprint">Shared by {{ $brand['company_name'] ?? config('app.name') }}</p>
</div>
<script>(function(){try{var s=localStorage.getItem('theme');if(s==='dark'||s==='light')document.documentElement.setAttribute('data-theme',s);}catch(e){}})();</script>
</body>
</html>
