<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">@php $b = isset($brand) && is_array($brand) ? $brand : []; @endphp<title>License required{{ !empty($b['company_name']) ? ' — '.$b['company_name'] : '' }}</title>@if(!empty($b['favicon_path']))<link rel="icon" href="{{ asset('storage/'.$b['favicon_path']) }}">@else<link rel="icon" href="{{ asset('favicon.ico') }}">@endif
<link rel="stylesheet" href="{{ asset('assets/css/app.css').'?v=107' }}"></head>
<body><div class="inst"><div class="inst-box">
@if(!empty($b['logo_path']) || !empty($b['logo_dark_path']))@include('brand.logo', ['brand' => $b])@endif
<h1 style="margin-top:0">License required (402)</h1>
@if(!empty($b['company_name']))
<p class="hint" style="margin-top:0">{{ $b['company_name'] }}</p>
@endif
@if(!empty($license['reason']))
<div class="err">{{ $license['reason'] }}</div>
@endif
@if(!empty($license['request_id']))
<p class="hint">Server reference <code>{{ $license['request_id'] }}</code>.</p>
@endif
<p class="hint">This copy is not licensed to run.
@auth
If you are the administrator, open <a href="{{ route('settings.license') }}">Settings → License</a> and activate a key.
@else
<a class="btn sm" href="{{ route('login') }}">Sign in</a> <span>— an administrator can then activate a license in Settings → License.</span>
@endauth
Contact your software provider for a key.</p>
</div></div>
<script>(function(){try{var s=localStorage.getItem('theme');if(s==='dark'||s==='light')document.documentElement.setAttribute('data-theme',s);}catch(e){}})();</script>
</body></html>
