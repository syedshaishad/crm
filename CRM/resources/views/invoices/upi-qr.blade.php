@php
$__upiId = $bank['upi_id'] ?? '';
$__upiBal = (float) ($balance ?? 0);
$__upiSvg = ($__upiId && $__upiBal > 0) ? upi_qr_svg(upi_intent($__upiId, $brand['company_name'] ?? '', $__upiBal, $invoice->number ?? '')) : '';
@endphp
@if($__upiSvg)
<div class="upi-box">
  <div class="upi-qr">{!! $__upiSvg !!}</div>
  <div class="upi-meta"><div class="cap">Scan to pay</div>
    <div class="sub">GPay · PhonePe · Paytm · BHIM</div>
    <div class="upi-amt">{{ money($__upiBal) }}</div>
    <div class="sub">to <b>{{ $__upiId }}</b></div>
  </div>
</div>
@endif
