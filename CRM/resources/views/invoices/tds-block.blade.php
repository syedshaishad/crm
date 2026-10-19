@if(setting('tax.tds_enabled','0')==='1')
<details class="adv" @if($errors->has('tds')) open @endif><summary>TDS on this invoice (optional)</summary>
<div style="margin-top:10px" class="form-grid">
<div><label class="f">Section</label><select class="in" name="tds_section" id="tds-section" style="margin-bottom:0"><option value="">No TDS</option>@foreach(tds_sections() as $code => $rate)<option value="{{ $code }}" data-rate="{{ $rate }}" {{ old('tds_section', $tds->section ?? '')===$code?'selected':'' }}>{{ $code }} · {{ $rate }}%</option>@endforeach</select></div>
<div><label class="f">TDS amount (₹)</label><input class="in" type="number" step="0.01" min="0" name="tds_amount" id="tds-amount" style="margin-bottom:0" value="{{ old('tds_amount', isset($tds->amount) ? $tds->amount : '') }}" placeholder="0.00"></div>
</div>
<input type="hidden" name="tds_rate" id="tds-rate" value="{{ old('tds_rate', $tds->rate ?? '') }}">
<p class="fhint" style="margin:8px 0 0">Claimed TDS settles the invoice like cash and lands in the 26AS register. Payment-time TDS is recorded separately on each payment.</p></details>
<script>
(function(){
var sec = document.getElementById('tds-section');
if (!sec || sec.dataset.bound) return;
sec.dataset.bound = '1';
var box = sec.closest('details');
var form = sec.closest('form');
var tds = document.getElementById('tds-amount'), rate = document.getElementById('tds-rate');
var cash = form ? form.querySelector('[name=amount]') : null;
function fill(){
  var r = parseFloat((sec.selectedOptions[0] || {}).dataset ? sec.selectedOptions[0].dataset.rate || '0' : '0');
  rate.value = r || '';
  var base = cash ? parseFloat(cash.value || '0') : 0;
  if (sec.value && base > 0 && document.activeElement !== tds) tds.value = (base * r / 100).toFixed(2);
  else if (!sec.value) tds.value = '';
}
sec.addEventListener('change', fill);
if (cash) cash.addEventListener('input', fill);
})();
</script>
@endif
