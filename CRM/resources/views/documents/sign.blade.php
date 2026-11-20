<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign {{ $doc->name }} — {{ config('app.name') }}</title>
<link rel="stylesheet" href="{{ asset('assets/css/app.css').'?v=139' }}">
@include('brand.theme')
<style>
  .sign-wrap{max-width:620px;margin:36px auto;padding:0 14px}
  #sig-pad{width:100%;height:180px;border:1px solid var(--dc-line);border-radius:14px;background:var(--dc-panel);touch-action:none;cursor:crosshair;display:block}
  .sign-wrap .form-actions{margin-top:14px}
</style>
</head>
<body>
<div class="sign-wrap">
  <div class="card" style="padding:22px">
    <div class="crumb">Document signature</div>
    <h1 style="margin:6px 0 4px">{{ $doc->name }}</h1>
    <p class="hint" style="margin:0 0 16px">{{ $brand['company_name'] ?? config('app.name') }} has asked you to review and sign this document.</p>

    @if($doc->signed_at)
      <div class="alert"><b>Signed by {{ $doc->signed_by }}</b><br>{{ $doc->signed_at->format('j M Y, H:i') }}</div>
      @if($doc->signature_path)
      <div style="margin-top:14px"><span class="hint">Signature</span><br><img src="{{ asset('storage/'.$doc->signature_path) }}" alt="Signature of {{ $doc->signed_by }}" style="max-width:100%;max-height:120px;border:1px solid var(--dc-line);border-radius:10px;margin-top:6px"></div>
      @endif
    @else
      <p><a class="btn sm sec" href="{{ url('storage/'.$doc->path) }}" target="_blank" rel="noopener">View document ↗</a></p>
      @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
      @if(session('ok'))<div class="alert">{{ session('ok') }}</div>@endif
      <form method="POST" action="{{ route('documents.sign.store', $doc->sign_token) }}">@csrf
        <label for="signed-by">Your full name</label>
        <input id="signed-by" name="signed_by" required maxlength="255" autocomplete="name" placeholder="Name">
        <label for="sig-pad">Draw your signature</label>
        <canvas id="sig-pad" aria-label="Signature pad"></canvas>
        <input type="hidden" name="signature" id="sig-data">
        <div style="margin-top:8px"><button class="btn sm sec" type="button" id="sig-clear">Clear</button></div>
        <div class="form-actions"><button class="btn" type="submit">Sign document</button></div>
      </form>
    @endif
  </div>
</div>
<script>
(function(){
  var pad=document.getElementById('sig-pad'); if(!pad)return;
  var ctx=pad.getContext('2d'), drawing=false, inked=false;
  function size(){
    var rect=pad.getBoundingClientRect(), ratio=window.devicePixelRatio||1;
    pad.width=Math.round(rect.width*ratio); pad.height=Math.round(rect.height*ratio);
    ctx.scale(ratio,ratio); ctx.lineWidth=2.2; ctx.lineCap='round'; ctx.lineJoin='round';
    ctx.strokeStyle=getComputedStyle(document.documentElement).getPropertyValue('--dc-ink').trim()||'#222';
  }
  function pos(e){ var r=pad.getBoundingClientRect(); var t=e.touches?e.touches[0]:e; return {x:t.clientX-r.left,y:t.clientY-r.top}; }
  function start(e){ e.preventDefault(); var p=pos(e); drawing=true; inked=true; ctx.beginPath(); ctx.moveTo(p.x,p.y); }
  function move(e){ if(!drawing)return; e.preventDefault(); var p=pos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); }
  function end(){ drawing=false; }
  pad.addEventListener('mousedown',start); pad.addEventListener('mousemove',move);
  window.addEventListener('mouseup',end);
  pad.addEventListener('touchstart',start,{passive:false}); pad.addEventListener('touchmove',move,{passive:false});
  window.addEventListener('touchend',end);
  document.getElementById('sig-clear').addEventListener('click',function(){ctx.clearRect(0,0,pad.width,pad.height);inked=false;});
  pad.closest('form').addEventListener('submit',function(e){
    if(!inked){ e.preventDefault(); alert('Please draw your signature first.'); return; }
    document.getElementById('sig-data').value=pad.toDataURL('image/png');
  });
  size();
})();
</script>
</body>
</html>
