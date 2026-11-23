<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title','Client portal') — {{ $brand['company_name'] ?? config('app.name') }}</title>
@if(!empty($brand['favicon_path']))<link rel="icon" href="{{ asset('storage/'.$brand['favicon_path']) }}">@else<link rel="icon" href="{{ asset('favicon.ico') }}">@endif
<link rel="stylesheet" href="{{ asset('assets/css/app.css').'?v=168' }}">
@include('brand.theme')
<script>(function(){var t=null;try{t=localStorage.getItem('theme');}catch(e){}if(t!=='dark'&&t!=='light'){try{t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}catch(e){t='light';}}document.documentElement.setAttribute('data-theme',t);})();</script>
<style>
@media(max-width:1180px){
  .topnav{flex-wrap:wrap;gap:8px}
  .top-actions{flex:1 1 100%;width:100%;margin-left:0;gap:6px;min-width:0;flex-wrap:nowrap;overflow-x:auto;overscroll-behavior-x:contain;scrollbar-width:none;padding-bottom:2px}
  .top-actions::-webkit-scrollbar{display:none}
  .top-actions .icon-pill,.top-actions form{flex:0 0 auto}
  .top-actions .icon-pill{padding:6px 10px;font-size:12px;white-space:nowrap}
  /* let portal tables size columns to their content instead of equal fixed widths */
  table.tbl{table-layout:auto;font-size:12.5px}
  table.tbl td,table.tbl th{padding:9px 7px}
  table.tbl td:first-child{white-space:nowrap}
}
</style>
</head>
<body style="background:var(--bg)">
<div class="topnav">
  <span class="brand-pill" title="{{ $brand['company_name'] ?? config('app.name') }}">@include('brand.logo') <small>client portal</small></span>
  <div class="top-actions">
    <button type="button" class="icon-pill" data-theme-toggle title="Toggle light / dark" aria-label="Toggle theme" style="border:0;cursor:pointer">◐</button>
    @auth('client')
    <a class="icon-pill" href="{{ route('portal.dashboard') }}" title="Home">Home</a>
    <a class="icon-pill" href="{{ route('portal.invoices') }}" title="Invoices">Invoices</a>
    <a class="icon-pill" href="{{ route('portal.proposals') }}" title="Proposals">Proposals</a>
    <a class="icon-pill" href="{{ route('portal.receipts') }}" title="Receipts">Receipts</a>
    <a class="icon-pill" href="{{ route('portal.statement') }}" title="Statement">Statement</a>
    <a class="icon-pill" href="{{ route('portal.work') }}" title="Work">Work</a>
    <a class="icon-pill" href="{{ route('portal.requests') }}" title="Requests">Requests</a>
    <a class="icon-pill" href="{{ route('portal.documents') }}" title="Documents">Documents</a>
    <a class="icon-pill" href="{{ route('portal.password') }}" title="Change password">{{ auth('client')->user()->name }}</a>
    <form method="POST" action="{{ route('portal.logout') }}" style="display:inline">@csrf<button class="icon-pill" type="submit" style="border:0;cursor:pointer">Logout</button></form>
    @endauth
  </div>
</div>
<div class="sheet"><div class="page">
  @if(session('ok'))<div class="alert">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
  @yield('content')
  <p class="footer-note noprint">{{ $brand['company_name'] ?? '' }} · client portal</p>
</div></div>
<script>(function(){document.addEventListener('click',function(e){if(!e.target.closest('[data-theme-toggle]'))return;var h=document.documentElement;var c=h.getAttribute('data-theme')||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');var n=c==='dark'?'light':'dark';h.setAttribute('data-theme',n);try{localStorage.setItem('theme',n);}catch(x){}});})();</script>
</body></html>
