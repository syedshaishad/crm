@extends('layouts.app')
@section('title','Settings')
@section('content')
@php
$cards = [
  ['settings.general', null, 'rgba(10,132,255,.13)', '#0a84ff', '<path d="M4 6h16M4 12h16M4 18h10"/>', 'General', 'Currency, dates, timezone, listings.'],
  ['settings.branding', null, 'rgba(191,90,242,.13)', '#bf5af2', '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>', 'Branding', 'Name, logo, colours, login screen.'],
  ['settings.billing', null, 'rgba(48,209,88,.15)', '#1a7f37', '<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h7"/>', 'Billing', 'Numbering, defaults, declaration, logo.'],
  ['settings.tax', null, 'rgba(255,159,10,.15)', '#b54708', '<path d="M4 5h16v14H4z"/><path d="M4 10h16M8 3v4M16 3v4"/>', 'Tax', 'GST rates and tax behaviour.'],
  ['settings.integrations', null, 'rgba(100,210,255,.15)', '#0a84ff', '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M8 7.5 10.5 16M16 7.5 13.5 16M8.5 6h7"/>', 'Integrations', 'Razorpay keys and SMTP mail.'],
  ['settings.emails', null, 'rgba(255,100,130,.13)', '#ff6482', '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/>', 'Email templates', 'Invoice, receipt, proposal, reset mails.'],
  ['settings.snippets.index', null, 'rgba(191,90,242,.13)', '#bf5af2', '<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h7"/>', 'Content library', 'Reusable proposal scope, results and terms.', 'admin'],
  ['settings.users.index', null, 'rgba(10,132,255,.13)', '#0a84ff', '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.9-3.8 3.7-5.5 7-5.5s6.1 1.7 7 5.5"/>', 'Users', 'Team access, roles, activation.', 'admin'],
  ['settings.modules', null, 'rgba(100,210,255,.15)', '#0a84ff', '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v5"/>', 'Modules', 'Switch workspace areas on or off.', 'admin'],
  ['settings.updates', null, 'rgba(48,209,88,.15)', '#1a7f37', '<path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v3h16v-3"/>', 'Updates', 'Version, backups, update packages.'],
  ['settings.license', null, 'rgba(255,159,10,.15)', '#b54708', '<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>', 'License', 'Plan, limits, license file.'],
  ['settings.account', null, 'rgba(142,142,147,.16)', '#5b5648', '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="11" r="2"/><path d="M13.5 9.5H17M13.5 13H17M6 16.5c.6-1.8 1.5-2.5 2.5-2.5s1.9.7 2.5 2.5"/>', 'My account', 'Profile and password.'],
];
@endphp
<div class="set-grid">
@foreach($cards as $c)
  @php [$route, $module, $bg, $fg, $icon, $title, $desc] = $c; $needAdmin = $c[7] ?? null; @endphp
  @if((! $needAdmin || auth()->user()->isAdmin()) && (! $module || module_visible($module)))
  <a class="set-card" href="{{ route($route) }}"><div class="ic" style="background:{{ $bg }};color:{{ $fg }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">{!! $icon !!}</svg></div><h3>{{ $title }}</h3><p>{{ $desc }}</p></a>
  @endif
@endforeach
</div>
@endsection
