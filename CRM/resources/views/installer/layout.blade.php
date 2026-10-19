<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Installation — {{ config('app.name') }}</title>
@if(!empty($brand['favicon_path']))<link rel="icon" href="{{ asset('storage/'.$brand['favicon_path']) }}">@else<link rel="icon" href="{{ asset('favicon.ico') }}">@endif
<link rel="stylesheet" href="{{ asset('assets/css/app.css').'?v=107' }}">
</head>
<body>
<div class="inst">
  <div class="inst-box">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:38px;height:38px;border-radius:50%;background:var(--dc-dark);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800">C</div>
      <div><b>CRM Setup</b><div class="hint">Self-hosted install · your data stays on your server</div></div>
    </div>
    @php $step = (int) trim($__env->yieldContent('step', '0')); $labels = ['Start','Requirements','Database','Admin','Company','Mail','Integrations','License','Finish']; @endphp
    @if($step <= 8)
    <div class="hint" style="margin-top:14px">Step {{ min($step+1,9) }} of 9 — {{ $labels[min($step,8)] ?? '' }}</div>
    <div class="steps">@for($i=0;$i<9;$i++)<span class="{{ $i <= $step ? 'done' : '' }}"></span>@endfor</div>
    @endif
    @if($errors->any())<div class="err"><ul style="margin:0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @yield('content')
  </div>
  <p class="hint" style="text-align:center;margin-top:16px">No accounts on any vendor server · no shared database · v{{ app_version() }}</p>
</div>
<script>(function(){try{var s=localStorage.getItem('theme');if(s==='dark'||s==='light')document.documentElement.setAttribute('data-theme',s);}catch(e){}})();</script>
</body>
</html>
