<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Proposal {{ $proposal->number }} — {{ $brand['company_name'] ?? config('app.name') }}</title>
@if(!empty($brand['favicon_path']))<link rel="icon" href="{{ asset('storage/'.$brand['favicon_path']) }}">@else<link rel="icon" href="{{ asset('favicon.ico') }}">@endif
<link rel="stylesheet" href="{{ asset('assets/css/app.css').'?v=130' }}">
@include('brand.theme')
<style>
.sig-wrap{max-width:720px;margin:28px auto;padding:0 14px}
.sig-pad{width:100%;height:220px;border:2px dashed var(--line);border-radius:16px;background:#fff;touch-action:none;cursor:crosshair}
.sig-pad.signed{border-style:solid;border-color:var(--primary)}
</style>
</head>
<body style="background:var(--bg)">
<div class="sig-wrap">
  <div class="topnav noprint" style="padding:0 0 12px">
    <span class="brand-pill" title="{{ $brand['company_name'] ?? config('app.name') }}">@include('brand.logo')</span>
  </div>
  @php $st = $proposal->displayStatus(); @endphp
  <div class="card">
    <div class="doc-head">
      <div><h2 style="margin:0">Proposal {{ $proposal->number }}</h2><div class="hint">Prepared for <b>{{ $proposal->client->company ?: $proposal->client->name }}</b>@if($proposal->valid_until) · valid till {{ d($proposal->valid_until, 'j M Y') }}@endif</div>@if($proposal->valid_until && $st!=='accepted')<div class="hint" id="countdown" data-until="{{ $proposal->valid_until->format('Y-m-d') }}" style="color:var(--warn,orange);font-weight:700"></div>@endif</div>
      <div class="doc-meta"><h3>{{ money($proposal->total) }}</h3></div>
    </div>
    <div class="tbl-scroll"><table class="tbl"><tr><th>#</th><th>Service</th><th>Qty</th><th style="text-align:right">Rate</th><th style="text-align:right">Amount</th></tr>
    @foreach($proposal->items as $n => $it)<tr><td>{{ $n + 1 }}</td><td>{{ $it->description }}@if(($it->billing ?? 'once') !== 'once') <span class="hint">· {{ $it->billing === 'monthly' ? 'billed monthly' : 'billed yearly' }}</span>@endif</td><td class="nowrap">{{ $it->qty }} {{ $it->unit }}</td><td class="nowrap" style="text-align:right">{{ money($it->rate) }}</td><td class="nowrap" style="text-align:right">{{ money($it->amount) }}</td></tr>@endforeach</table></div>
    <div class="tot-band"><div class="tot-row"><span>Subtotal</span><span>{{ money($proposal->subtotal) }}</span></div>@if($proposal->discount > 0)<div class="tot-row"><span>Discount</span><span>- {{ money($proposal->discount) }}</span></div>@endif<div class="tot-row"><span>GST</span><span>{{ money($proposal->gst_amount) }}</span></div><div class="tot-row grand"><span>Total</span><span>{{ money($proposal->total) }}</span></div></div>
    @if($proposal->scope)<div class="bill-box" style="margin-top:14px"><div class="cap">Scope of work</div><div class="sub" style="white-space:pre-line">{{ $proposal->scope }}</div></div>@endif
    @if($proposal->results)<div class="bill-box" style="margin-top:14px"><div class="cap">Expected results</div><div class="sub" style="white-space:pre-line">{{ $proposal->results }}</div></div>@endif
    @if($proposal->terms)<div class="bill-box" style="margin-top:14px"><div class="cap">Terms</div><div class="sub" style="white-space:pre-line">{{ $proposal->terms }}</div></div>@endif
  </div>

  @if(session('ok'))<div class="alert">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif

  <div class="card"><div class="card-head"><h3>Questions? Talk to us here</h3><span class="hint">we reply on this page</span></div>
  @forelse($replies ?? [] as $rep)<div style="margin-bottom:10px"><b>{{ $rep->side === 'staff' ? ($brand['company_name'] ?? 'Us') : ($rep->author ?? 'Client') }}</b> <span class="hint">{{ d($rep->created_at, 'j M, h:i A') }}</span><div class="sub" style="white-space:pre-line">{{ $rep->body }}</div></div>@empty<p class="hint" style="margin:0">No messages yet — ask about scope, timelines, anything.</p>@endforelse
  @if(!in_array($st,['accepted','declined','expired','invoiced'],true))
  <form method="POST" action="{{ route('proposals.sign.reply', $proposal->sign_token) }}" style="margin-top:10px">@csrf
  <label class="f">Your name</label><input class="in" name="author" maxlength="120" placeholder="{{ $proposal->client->name }}">
  <label class="f">Message</label><textarea class="in" name="body" rows="2" required maxlength="2000" placeholder="e.g. Can you start next Monday?"></textarea>
  <div class="form-actions"><button class="btn sec sm" type="submit">Send message</button></div></form>
  @endif</div>

  @if($st==='accepted')
    <div class="card" style="text-align:center"><h3>Signed ✓</h3>
      @if($proposal->signature_path)<img src="{{ asset('storage/'.$proposal->signature_path) }}" alt="signature" style="max-height:120px;max-width:300px;object-fit:contain;background:#fff;border:1px solid var(--line);border-radius:12px;padding:6px">@endif
      <p class="hint">Signed by <b>{{ $proposal->signed_by }}</b>@if($proposal->signed_at) on {{ d($proposal->signed_at, 'j M Y, h:i A') }}@endif. This proposal is now closed — thank you.</p></div>
  @elseif($st==='declined')
    <div class="card" style="text-align:center"><h3>Declined</h3><p class="hint">This proposal was declined and is now closed.</p></div>
  @elseif($st==='expired' || $st==='invoiced')
    <div class="card" style="text-align:center"><h3>{{ ucfirst($st) }}</h3><p class="hint">This proposal is no longer open for signing. Please contact us for a fresh one.</p></div>
  @else
    <div class="card"><h3>Sign to accept</h3>
      <p class="fhint">Draw your signature below (finger or mouse), type your name, and hit <b>Sign & accept</b>. This closes the proposal as accepted.</p>
      <form method="POST" action="{{ route('proposals.sign.store', $proposal->sign_token) }}" id="sign-form">@csrf
        <label class="f">Your full name <span class="req">*</span></label>
        <input class="in" name="signed_by" required placeholder="e.g. {{ $proposal->client->name }}" autocomplete="name">
        <label class="f">Signature <span class="req">*</span></label>
        <canvas class="sig-pad" id="sig-pad"></canvas>
        <div class="btnrow" style="margin-top:8px"><button type="button" class="btn sec sm" id="sig-clear">Clear</button></div>
        <input type="hidden" name="signature" id="sig-data">
        <div class="form-actions" style="margin-top:14px"><button class="btn brand" type="submit">Sign & accept {{ money($proposal->total) }}</button></div>
      </form>
      <hr><p class="fhint">Not interested? Tell us why (optional) — it helps us quote better.</p>
      <form method="POST" action="{{ route('proposals.decline', $proposal->sign_token) }}" data-confirm="Decline this proposal? This closes it.">@csrf<label class="f">Why not? (optional)</label><select class="in" name="lost_reason"><option value="">— pick one —</option>@foreach(\Modules\Proposals\Proposal::lostReasons() as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select><input class="in" name="competitor" maxlength="120" placeholder="If you chose someone else, who? (optional)" style="margin-top:8px"><input class="in" name="close_reason" maxlength="255" placeholder="Anything else we should know? (optional)" style="margin-top:8px"><div style="margin-top:8px"><button class="btn sec sm" type="submit">Decline proposal</button></div></form>
    </div>
  @endif
  <p class="footer-note">Sent by {{ $brand['company_name'] ?? config('app.name') }}{{ !empty($brand['phone']) ? ' · '.$brand['phone'] : '' }}</p>
</div>
<div class="modal" id="sign-confirm" role="alertdialog" aria-modal="true">
  <div class="modal-bg" data-close></div>
  <div class="modal-card confirm-card tone-brand">
    <div class="confirm-ic" style="background:var(--info-bg);color:var(--info)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></div>
    <h3>Decline proposal?</h3>
    <p class="hint" style="margin:0 0 18px">This closes the proposal — we cannot accept it afterwards.</p>
    <div class="form-actions"><button class="btn brand" id="decline-ok">Decline</button><button class="btn sec" data-close>Keep it</button></div>
  </div>
</div>
<script>
(function () {
  // Offer countdown: ticks down to end of the valid-until day.
  var cd = document.getElementById('countdown');
  if (cd) {
    var end = new Date(cd.dataset.until + 'T23:59:59');
    function tick() {
      var ms = end - new Date();
      if (ms <= 0) { cd.textContent = 'This offer has expired — ask us for a fresh one.'; return; }
      var d = Math.floor(ms / 864e5), h = Math.floor(ms % 864e5 / 36e5), m = Math.floor(ms % 36e5 / 6e4);
      cd.textContent = 'Offer ends in ' + (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm — sign before it lapses.';
    }
    tick(); setInterval(tick, 30000);
  }
  var canvas = document.getElementById('sig-pad');
  if (!canvas) return;
  var ctx = canvas.getContext('2d');
  var drawing = false, hasInk = false, last = null;
  function size() {
    var ratio = window.devicePixelRatio || 1;
    var w = canvas.clientWidth, h = canvas.clientHeight;
    canvas.width = w * ratio; canvas.height = h * ratio;
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#1c1a15';
  }
  size(); window.addEventListener('resize', size);
  function pos(e) {
    var r = canvas.getBoundingClientRect();
    var t = (e.touches && e.touches[0]) || (e.changedTouches && e.changedTouches[0]) || e;
    return { x: t.clientX - r.left, y: t.clientY - r.top };
  }
  function start(e) { e.preventDefault(); drawing = true; last = pos(e); }
  function move(e) {
    if (!drawing) return;
    e.preventDefault();
    var p = pos(e);
    ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
    last = p; hasInk = true; canvas.classList.add('signed');
  }
  function stop() { drawing = false; }
  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', stop);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove', move, { passive: false });
  canvas.addEventListener('touchend', stop);
  document.getElementById('sig-clear').addEventListener('click', function () {
    ctx.clearRect(0, 0, canvas.clientWidth, canvas.clientHeight);
    hasInk = false; canvas.classList.remove('signed');
  });
  document.getElementById('sign-form').addEventListener('submit', function (e) {
    if (!hasInk) {
      e.preventDefault();
      var warn = document.getElementById('sig-warn');
      if (!warn) {
        warn = document.createElement('div');
        warn.id = 'sig-warn';
        warn.className = 'err';
        warn.style.marginTop = '10px';
        canvas.after(warn);
      }
      warn.textContent = 'Please draw your signature first.';
      canvas.scrollIntoView({block: 'nearest'});
      return;
    }
    document.getElementById('sig-data').value = canvas.toDataURL('image/png');
  });
  // Designed decline confirm (no app.js on this standalone page).
  var declineForm = document.querySelector('form[action*="/decline"]');
  var signModal = document.getElementById('sign-confirm');
  if (declineForm && signModal) {
    declineForm.addEventListener('submit', function (e) {
      if (declineForm.dataset.armed === '1') { delete declineForm.dataset.armed; return; }
      e.preventDefault();
      signModal.classList.add('open');
    });
    signModal.querySelectorAll('[data-close]').forEach(function (b) {
      b.addEventListener('click', function () { signModal.classList.remove('open'); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') signModal.classList.remove('open');
      if (e.key === 'Enter' && signModal.classList.contains('open') && !(e.target.closest && e.target.closest('[data-close]'))) {
        e.preventDefault();
        document.getElementById('decline-ok').click();
      }
    });
    document.getElementById('decline-ok').addEventListener('click', function () {
      signModal.classList.remove('open');
      declineForm.dataset.armed = '1';
      declineForm.submit();
    });
  }
})();
</script>
<script>(function(){try{var s=localStorage.getItem('theme');if(s==='dark'||s==='light')document.documentElement.setAttribute('data-theme',s);}catch(e){}})();</script>
</body>
</html>
