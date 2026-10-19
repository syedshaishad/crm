<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pitch {{ $proposal->number }} — {{ $brand['company_name'] ?? config('app.name') }}</title>
@if(!empty($brand['favicon_path']))<link rel="icon" href="{{ asset('storage/'.$brand['favicon_path']) }}">@else<link rel="icon" href="{{ asset('favicon.ico') }}">@endif
<link rel="stylesheet" href="{{ asset('assets/css/app.css').'?v=107' }}">
@include('brand.theme')
<style>
.pitch{position:fixed;inset:0;background:var(--bg);display:flex;flex-direction:column;z-index:50}
.pitch-bar{height:4px;background:var(--surface-2);flex:none}.pitch-bar div{height:100%;width:0;background:var(--dc-accent);transition:width .3s}
.pitch-top{flex:none;display:flex;justify-content:space-between;align-items:center;padding:10px 18px;color:var(--muted);font-size:13px}
.pitch-top .brand-pill{pointer-events:none}
.slide{flex:1;display:none;align-items:center;justify-content:center;padding:24px;overflow:auto}
.slide.on{display:flex}
.slide-inner{max-width:820px;width:100%;text-align:center}
.slide-inner.left{text-align:left}
.slide h1{font-size:clamp(30px,5vw,54px);margin:0 0 8px;letter-spacing:-.02em}
.slide .big-total{font-size:clamp(40px,7vw,76px);font-weight:800;margin:8px 0}
.slide .lede{font-size:clamp(15px,2.2vw,21px);color:var(--muted)}
.pitch-nav{flex:none;display:flex;justify-content:center;align-items:center;gap:14px;padding:12px}
.pdot{width:10px;height:10px;border-radius:99px;background:var(--surface-2);border:1px solid var(--line);padding:0;cursor:pointer}
.pdot.on{background:var(--primary);border-color:var(--primary)}
.pitch-keys{color:var(--muted);font-size:12px}
.pitch-qr{background:#fff;border-radius:14px;padding:10px;display:inline-block;margin-top:12px}
.pitch-qr svg{display:block;width:150px;height:150px}
@media print{.pitch-top,.pitch-nav,.pitch-bar{display:none}.pitch{position:static}.slide{display:flex;page-break-inside:avoid}}
</style>
</head>
<body>
@php
  $intra = $proposal->isIntraState();
  $halfRate = $proposal->gst_rate / 2;
  $clientName = $proposal->client->company ?: ($proposal->client->name ?? '');
  $signed = (bool) $proposal->signed_at;
  $split = $proposal->billingSplit();
  $hasRecur = $split['monthly'] > 0 || $split['yearly'] > 0;
@endphp
<div class="pitch" id="pitch">
<div class="pitch-bar"><div id="pfill"></div></div>
<div class="pitch-top"><span>{{ $brand['company_name'] ?? '' }} · {{ $proposal->number }}</span><span><button class="btn sec sm" onclick="window.print()">Save PDF</button> <button class="btn sec sm" id="pfull">Fullscreen</button> <a class="btn sec sm" id="pexit" href="{{ route('proposals.show',$proposal) }}">Exit (Esc)</a></span></div>

<div class="slide on"><div class="slide-inner">
  @if(!empty($brand['logo_path']) || !empty($brand['logo_dark_path']))@include('brand.logo', ['style' => 'max-height:64px;margin-bottom:14px'])@endif
  @if($proposal->cover_path)<img src="{{ asset('storage/'.$proposal->cover_path) }}" alt="cover" style="max-width:100%;max-height:38vh;border-radius:18px;margin-bottom:14px;object-fit:cover">@endif
  <div class="lede">Proposal for {{ $clientName }}</div>
  <h1>{{ $proposal->number }}</h1>
  <div class="big-total">{{ money($proposal->total) }}</div>
  <div class="lede">@if($proposal->valid_until)Valid till {{ d($proposal->valid_until, 'j F Y') }} · @endif Prepared by {{ $proposal->creator->name ?? 'us' }}</div>
</div></div>

<div class="slide"><div class="slide-inner left">
  <div class="lede">What you get</div>
  <h1>Scope</h1>
  <div class="tbl-scroll"><table class="tbl"><tr><th>#</th><th>Description</th><th style="text-align:right">Qty</th><th style="text-align:right">Rate</th><th style="text-align:right">Amount</th></tr>
  @foreach($proposal->items as $n => $it)<tr><td>{{ $n + 1 }}</td><td>{{ $it->description }}@if(($it->billing ?? 'once') !== 'once') <span class="hint">· {{ $it->billing === 'monthly' ? '/mo' : '/yr' }}</span>@endif</td><td class="nowrap" style="text-align:right">{{ $it->qty }}</td><td class="nowrap" style="text-align:right">{{ money($it->rate) }}</td><td class="nowrap" style="text-align:right"><b>{{ money($it->amount) }}</b></td></tr>@endforeach</table></div>
</div></div>

<div class="slide"><div class="slide-inner">
  <div class="lede">Investment</div>
  <div class="big-total">{{ money($proposal->total) }}</div>
  @if($hasRecur)
  <div class="lede" style="margin-bottom:10px">@if($split['once'] > 0){{ money($split['once']) }} one-time @endif @if($split['monthly'] > 0)+ {{ money($split['monthly']) }}/mo @endif @if($split['yearly'] > 0)+ {{ money($split['yearly']) }}/yr @endif <span class="hint">+ GST · = {{ money($split['monthly'] * 12 + $split['yearly']) }}/yr recurring</span></div>
  @endif
  <div class="lede">Taxable {{ money($proposal->taxable_value) }} @if($proposal->discount > 0)· after {{ money($proposal->discount) }} discount @endif<br>
  @if($intra)CGST {{ money($proposal->cgst_amount) }} + SGST {{ money($proposal->sgst_amount) }}@else IGST {{ money($proposal->igst_amount) }}@endif ({{ $proposal->gst_rate }}%)</div>
  <div class="words" style="margin-top:14px">{{ amount_words($proposal->total) }} only.</div>
</div></div>

@if($proposal->terms)
<div class="slide"><div class="slide-inner left">
  <div class="lede">The fine print</div>
  <h1>Terms</h1>
  <div class="sub" style="white-space:pre-line;font-size:17px">{{ $proposal->terms }}</div>
</div></div>
@endif
@if($proposal->scope || $proposal->results)
<div class="slide"><div class="slide-inner left">
  <div class="lede">What happens next</div>
  <h1>Scope & results</h1>
  @if($proposal->scope)<div class="cap" style="margin-top:10px">Scope of work</div><div class="sub" style="white-space:pre-line;font-size:17px">{{ $proposal->scope }}</div>@endif
  @if($proposal->results)<div class="cap" style="margin-top:10px">Expected results</div><div class="sub" style="white-space:pre-line;font-size:17px">{{ $proposal->results }}</div>@endif
</div></div>
@endif

<div class="slide"><div class="slide-inner">
  @if($signed)
  <div class="lede">Signed off</div>
  <h1>Accepted</h1>
  <div class="lede">{{ $proposal->signed_by }} · {{ d($proposal->signed_at, 'j M Y, h:i A') }}</div>
  @elseif($signUrl)
  <div class="lede">Ready when you are</div>
  <h1>Accept it live</h1>
  <div class="lede">Scan with your phone — opens the signing page, no login.</div>
  <div class="pitch-qr">{!! upi_qr_svg($signUrl, 150) !!}</div>
  <div><button class="btn sec sm" style="margin-top:12px" id="pcopy" data-link="{{ $signUrl }}">Copy signing link</button></div>
  @else
  <div class="lede">Next step</div>
  <h1>Talk to us</h1>
  <div class="lede">This proposal has no signing link yet — ask {{ $brand['company_name'] ?? 'us' }} to send it.</div>
  @endif
</div></div>

<div class="pitch-nav"><button class="btn sec sm" id="pprev">← Back</button><span id="pdots" style="display:flex;gap:8px"></span><button class="btn sec sm" id="pnext">Next →</button></div>
<div class="pitch-nav pitch-keys" style="padding-top:0">← → or Space to move · Esc to exit</div>
</div>
<script>
(function(){
var slides=[...document.querySelectorAll('.slide')],dots=document.getElementById('pdots'),fill=document.getElementById('pfill'),i=0;
slides.forEach((_,n)=>{var b=document.createElement('button');b.className='pdot';b.setAttribute('aria-label','Slide '+(n+1));b.onclick=()=>go(n);dots.appendChild(b);});
function go(n){i=Math.max(0,Math.min(slides.length-1,n));slides.forEach((s,k)=>s.classList.toggle('on',k===i));[...dots.children].forEach((d,k)=>d.classList.toggle('on',k===i));fill.style.width=((i+1)/slides.length*100)+'%';}
document.getElementById('pprev').onclick=()=>go(i-1);
document.getElementById('pnext').onclick=()=>go(i+1);
document.addEventListener('keydown',e=>{if(e.key==='ArrowRight'||e.key===' '){e.preventDefault();go(i+1);}else if(e.key==='ArrowLeft'){go(i-1);}else if(e.key==='Escape'){location.href=document.getElementById('pexit').href;}});
document.getElementById('pfull').onclick=()=>{if(document.fullscreenElement)document.exitFullscreen();else document.documentElement.requestFullscreen().catch(()=>{});};
var cp=document.getElementById('pcopy');if(cp)cp.onclick=()=>{navigator.clipboard.writeText(cp.dataset.link);cp.textContent='Copied!';setTimeout(()=>cp.textContent='Copy signing link',1500);};
go(0);
})();
</script>
<script>(function(){try{var s=localStorage.getItem('theme');if(s==='dark'||s==='light')document.documentElement.setAttribute('data-theme',s);}catch(e){}})();</script>
</body>
</html>
