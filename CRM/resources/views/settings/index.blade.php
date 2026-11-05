@extends('layouts.app')
@section('title','Settings')
@section('content')
@php
  $isAdmin = auth()->user()->isAdmin();
  $groups = [
    [
      'title' => 'Workspace',
      'sub' => 'Everyday configuration for this installation.',
      'links' => [
        ['settings.general', 'General', 'Currency, dates, timezone, listings.', '<path d="M4 6h16M4 12h16M4 18h10"/>', null],
        ['settings.branding', 'Branding', 'Name, logo, colours, login screen.', '<path d="M12 3a9 9 0 1 0 0 18h1.4a2 2 0 0 0 1.4-3.4 2 2 0 0 1 1.4-3.4H18a3 3 0 0 0 3-3A9 9 0 0 0 12 3Z"/><circle cx="7.5" cy="10.5" r="1"/>', null],
        ['settings.billing', 'Billing', 'Numbering, defaults, declaration, logo.', '<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h7"/>', null],
        ['settings.tax', 'Tax', 'GST rates and tax behaviour.', '<rect x="4" y="5" width="16" height="14" rx="2"/><path d="M4 10h16M8 3v4M16 3v4"/>', null],
        ['settings.modules', 'Modules', 'Switch workspace areas on or off.', '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v5"/>', 'admin'],
      ],
    ],
    [
      'title' => 'Communication',
      'sub' => 'Client-facing mail, payments and content.',
      'links' => [
        ['settings.emails', 'Email templates', 'Invoice, receipt, proposal and reminder mails.', '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/>', null],
        ['settings.integrations', 'Integrations', 'Razorpay, SMTP mail and WhatsApp.', '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M8 7.5 10.5 16M16 7.5 13.5 16M8.5 6h7"/>', null],
        ['settings.snippets.index', 'Content library', 'Reusable proposal scope, results and terms.', '<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h7"/>', 'admin'],
      ],
    ],
    [
      'title' => 'Access & system',
      'sub' => 'People, automation and installation health.',
      'links' => [
        ['settings.users.index', 'Users', 'Team access, roles, activation.', '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.9-3.8 3.7-5.5 7-5.5s6.1 1.7 7 5.5"/>', 'admin'],
        ['settings.api', 'API & webhooks', 'Tokens, endpoints and event delivery.', '<path d="m8 7-5 5 5 5M16 7l5 5-5 5M13 4l-2 16"/>', null],
        ['settings.updates', 'Updates', 'Version, backups and update packages.', '<path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v3h16v-3"/>', null],
        ['settings.license', 'License', 'Plan, limits and activation state.', '<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>', null],
        ['settings.account', 'My account', 'Profile picture, password and 2FA.', '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="11" r="2"/><path d="M13.5 9.5H17M13.5 13H17M6 16.5c.6-1.8 1.5-2.5 2.5-2.5s1.9.7 2.5 2.5"/>', null],
      ],
    ],
  ];

  $visible = [];
  foreach ($groups as $gi => $group) {
      $links = array_values(array_filter($group['links'], fn ($l) => (! $l[4] || $isAdmin)));
      if ($links) { $visible[$gi] = ['group' => $group, 'links' => $links]; }
  }
  $totalLinks = array_sum(array_map(fn ($g) => count($g['links']), $visible));
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/></svg>
    </span>
    <div>
      <h1>Settings</h1>
      <div class="crumb">{{ $totalLinks }} configuration {{ Str::plural('area', $totalLinks) }} · {{ $isAdmin ? 'administrator access' : 'staff access' }}</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('dashboard') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Dashboard
    </a>
  </div>
</div>

<div class="st-hub">
  @foreach($visible as $entry)
    @php $group = $entry['group']; $links = $entry['links']; @endphp
    <div class="card ix-card">
      <div class="card-head ix-card-head">
        <div><h3>{{ $group['title'] }}</h3><span class="hint">{{ $group['sub'] }}</span></div>
        <span class="ix-tag">{{ count($links) }}</span>
      </div>
      @foreach($links as $l)
        <a class="st-link" href="{{ route($l[0]) }}">
          <span class="st-link-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{{ $l[3] }}</svg></span>
          <span class="st-link-main"><b>{{ $l[1] }}</b><span>{{ $l[2] }}</span></span>
          <svg class="st-link-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        </a>
      @endforeach
    </div>
  @endforeach
</div>
@endsection
