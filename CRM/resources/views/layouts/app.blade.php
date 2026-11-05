<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') — {{ $brand['company_name'] ?? $brand['app_name'] ?? config('app.name') }}</title>
@if(!empty($brand['favicon_path']))<link rel="icon" href="{{ asset('storage/'.$brand['favicon_path']) }}">@else<link rel="icon" href="{{ asset('favicon.ico') }}">@endif
<link rel="stylesheet" href="{{ asset('assets/css/app.css').'?v=130' }}">
@include('brand.theme')
@yield('head')
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
@php
$nav = [
  ['Dashboard','dashboard',[],'<path d="M3 10.5 12 3l9 7.5V21H3z"/><path d="M9 21v-6h6v6"/>',[]],
  ['Sales',null,['leads.*','clients.*','proposals.*','products.*','amc.*'],'<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5 6.5-5s5.7 1.5 6.5 5"/><circle cx="17" cy="9" r="2.6"/><path d="M15.5 14.6c2.9.2 5 1.6 5.7 4.4"/>',[
    ['Leads','leads.index',['leads.*'],'<path d="M12 2a7 7 0 0 1 7 7c0 2.4-1.2 4.2-2.6 5.6-.9 1-1.4 2.1-1.4 3.4H9c0-1.3-.5-2.4-1.4-3.4C6.2 13.2 5 11.4 5 9a7 7 0 0 1 7-7z"/><path d="M9 21h6"/>'],
    ['Clients','clients.index',['clients.*'],'<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.9-3.8 3.7-5.5 7-5.5s6.1 1.7 7 5.5"/>'],
    ['Proposals','proposals.index',['proposals.*'],'<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6"/><path d="m9.5 14.5 2 2 3.5-4"/>'],
    ['Delivery Challans','challans.index',['challans.*'],'<path d="M1 8h13v8H1z"/><path d="M14 11h4l3 3v2h-7z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>'],
    ['Delivery routes','routes.index',['routes.*'],'<path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>'],
    ['Products','products.index',['products.*'],'<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>'],
    ['AMC & Warranties','amc.index',['amc.*'],'<path d="M12 22s8-3.5 8-10V5l-8-3-8 3v7c0 6.5 8 10 8 10z"/><path d="m9 11.5 2 2 4-4.5"/>'],
  ]],
  ['Work',null,['projects.*','tasks.*','meetings.*','knowledge.*','quality.*','automations.*'],'<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v5"/>',[
    ['Projects','projects.index',['projects.*'],'<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>'],
    ['Tasks','tasks.index',['tasks.index'],'<rect x="4" y="4" width="16" height="16" rx="2"/><path d="m8.5 12.5 2.5 2.5 5-5.5"/>'],
    ['Templates','tasks.templates',['tasks.templates'],'<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a3 3 0 0 1 6 0"/><path d="m9.5 13.5 2 2 3.5-4"/>'],
    ['Meetings','meetings.index',['meetings.*'],'<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/>'],
    ['Knowledge','knowledge.index',['knowledge.*'],'<path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/>'],
    ['Quality','quality.index',['quality.*'],'<path d="M12 22s8-3.5 8-10V5l-8-3-8 3v7c0 6.5 8 10 8 10z"/><path d="m9 11.5 2 2 4-4.5"/>'],
    ['Automations','automations.index',['automations.*'],'<path d="M13 2 4.5 13.5H11L10 22l8.5-11.5H12z"/>'],
  ]],
  ['Accounting',null,['invoices.*','payments.*','expenses.*','credits.*'],'<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>',[
    ['Invoices','invoices.index',['invoices.*'],'<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h7M9 17h7"/>'],
    ['Payments','payments.index',['payments.*'],'<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 12h.01M18 12h.01"/>'],
    ['Cheques','cheques.index',['cheques.*'],'<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/>'],
    ['Credit notes','credits.index',['credits.*'],'<path d="M12 2v20M17 6.5c0-2-2.2-3-5-3s-5 1-5 3 2.2 2.6 5 3.2 5 1.3 5 3.3-2.2 3-5 3-5-1-5-3"/>'],
    ['Expenses','expenses.index',['expenses.*'],'<path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/>'],
    ['Petty cash','petty.index',['petty.*'],'<rect x="3" y="7" width="18" height="12" rx="2"/><circle cx="12" cy="13" r="2.4"/><path d="M6.5 10V7.5A1.5 1.5 0 0 1 8 6h8a1.5 1.5 0 0 1 1.5 1.5V10"/>'],
    ['Day-end Close','dayclose.index',['dayclose.*'],'<path d="M5 13l4 4L19 7"/>'],
    ['Assets','assets.index',['assets.*'],'<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>'],
    ['Purchase orders','purchase-orders.index',['purchase-orders.*'],'<circle cx="9" cy="20" r="1.6"/><circle cx="17" cy="20" r="1.6"/><path d="M3 4h2l2.4 11h10.4l2-8H7"/>'],
    ['Payment Reminders','reminders.index',['reminders.*'],'<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>'],
  ]],
  ['Reports',null,['reports.*'],'<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>',[
    ['Overview','reports.index',['reports.index'],'<rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="8" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/><rect x="13" y="13" width="8" height="8" rx="2"/>'],
    ['GSTR-1 outward supplies','reports.gstr1',['reports.gstr1'],'<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a3 3 0 0 1 6 0"/><path d="m9.5 13.5 2 2 3.5-4"/>'],
    ['Collections','reports.breakdown',['reports.breakdown'],'<path d="M12 3v9h9"/><path d="M12 3a9 9 0 1 0 9 9"/>'],
    ['Proposals','reports.proposals',['reports.proposals'],'<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6"/><path d="m9.5 14.5 2 2 3.5-4"/>'],
    ['Clients','reports.clients',['reports.clients'],'<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.9-3.8 3.7-5.5 7-5.5s6.1 1.7 7 5.5"/>'],
    ['Receivables','reports.receivables',['reports.receivables'],'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>'],
    ['Profit & Loss','reports.pnl',['reports.pnl'],'<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>'],
    ['TDS register','reports.tds',['reports.tds'],'<path d="M3 10l9-6 9 6"/><path d="M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 20h18"/>'],
    ['Performance','reports.staff',['reports.staff'],'<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5 6.5-5s5.7 1.5 6.5 5"/><path d="m16 8 2 2 4-4"/>'],
    ['Data Exports','reports.exports',['reports.exports'],'<path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v3h16v-3"/>'],
    ['Scheduled','reports.schedules.index',['reports.schedules.*'],'<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/>'],
  ]],
  ['Documents','documents.index',['documents.*'],'<path d="M5 3h11l3 3v15H5z"/><path d="M15 3v6h4"/>'],
  ['Statutory Compliance','expiry.index',['expiry.*'],'<rect x="4" y="4" width="16" height="16" rx="2"/><path d="m8.5 12.5 2.5 2.5 5-5.5"/><path d="M8 2v4M16 2v4"/>'],
  ['Data Import & Export','import.index',['import.*','export.*'],'<path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v3h16v-3"/>',[]],
  ['HR',null,['attendance.*','leaves.*','shifts.*','holidays.*','overtime.*','reimbursements.*','appraisals.*','exits.*','salary.*','payroll.*','structures.*','loans.*','bonuses.*','team.*','skills.*','field.*','voice.*'],'<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5 6.5-5s5.7 1.5 6.5 5"/><path d="m16 8 2 2 4-4"/>',[
    ['Workload','team.load',['team.*'],'<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>'],
    ['Skills','skills.index',['skills.*'],'<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.9-3.8 3.7-5.5 7-5.5s6.1 1.7 7 5.5"/>'],
    ['Site Visits','field.index',['field.*'],'<path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>'],
    ['Employee Voice','voice.index',['voice.*'],'<path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.4A8.5 8.5 0 1 1 21 11.5z"/>'],
    ['Attendance','attendance.index',['attendance.*'],'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>'],
    ['Leave','leaves.index',['leaves.*'],'<path d="M12 21c-5 0-8-3-8-8 0-6 5-9 14-10-.5 9-1.5 18-6 18z"/><path d="M12 21c0-6 2-10 6-13"/>'],
    ['Shifts','shifts.index',['shifts.*'],'<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/>'],
    ['Holidays','holidays.index',['holidays.*'],'<path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>'],
    ['Overtime','overtime.index',['overtime.*'],'<circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2M9 2h6"/>'],
    ['Reimbursements','reimbursements.index',['reimbursements.*'],'<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/>'],
    ['Appraisals','appraisals.index',['appraisals.*'],'<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>'],
    ['Employee Exits','exits.index',['exits.*'],'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>'],
    ['Advances','salary.index',['salary.*'],'<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 12h.01M18 12h.01"/>'],
    ['Payroll','payroll.index',['payroll.*'],'<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v5"/>'],
    ['Pay Structures','structures.index',['structures.*'],'<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>'],
    ['Loans','loans.index',['loans.*'],'<path d="M12 2v20M17 6.5c0-2-2.2-3-5-3s-5 1-5 3 2.2 2.6 5 3.2 5 1.3 5 3.3-2.2 3-5 3-5-1-5-3"/>'],
    ['Bonuses','bonuses.index',['bonuses.*'],'<circle cx="12" cy="9" r="6"/><path d="m8.5 14-2 8 5.5-3 5.5 3-2-8"/>'],
  ]],
];
$admin = [
  ['General','settings.general',null,'<path d="M4 6h16M4 12h16M4 18h10"/>'],
  ['Branding','settings.branding',null,'<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>'],
  ['Billing','settings.billing',null,'<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h7"/>'],
  ['Tax','settings.tax',null,'<path d="M4 5h16v14H4z"/><path d="M4 10h16M8 3v4M16 3v4"/>'],
  ['Integrations','settings.integrations',null,'<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M8 7.5 10.5 16M16 7.5 13.5 16M8.5 6h7"/>'],
  ['Emails','settings.emails',null,'<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/>'],
  ['Users','settings.users.index','admin','<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.9-3.8 3.7-5.5 7-5.5s6.1 1.7 7 5.5"/>'],
  ['Updates','settings.updates',null,'<path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v3h16v-3"/>'],
  ['License','settings.license',null,'<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>'],
  ['API','settings.api',null,'<path d="M4 7V5a2 2 0 0 1 2-2h2"/><path d="M16 3h2a2 2 0 0 0 2 2v2"/><path d="M4 17v2a2 2 0 0 0 2 2h2"/><path d="M16 21h2a2 2 0 0 0 2-2v-2"/><path d="M8 12h8"/>'],
];
@endphp
@php
try { $lm = app(\Modules\Licensing\LicenseManager::class); } catch (\Throwable $e) { $lm = null; }
$canSeeCache = [];
$canSee = function ($route) use ($lm, &$canSeeCache) {
  if (! $route) return false;
  if (array_key_exists($route, $canSeeCache)) return $canSeeCache[$route];
  $mod = $lm ? $lm->moduleForRoute($route) : null;
  if ($mod && function_exists('module_enabled') && ! module_enabled($mod)) return $canSeeCache[$route] = false;
  if ($mod && $lm && ! $lm->moduleAllowed($mod)) return $canSeeCache[$route] = false;
  if (function_exists('user_can') && ($pm = perm_module_for_route($route)) && ! user_can($pm, 'view')) return $canSeeCache[$route] = false;
  return $canSeeCache[$route] = true;
};
$sideItems = [];
foreach ($nav as $item) {
  $label = $item[0];
  $route = $item[1];
  $match = $item[2];
  $kids = $item[4] ?? [];
  if ($route === 'dashboard') continue;
  if (empty($kids)) {
    if ($canSee($route)) $sideItems[] = ['link', [$label, $route, $match, $item[3]]];
    continue;
  }
  $kids = array_values(array_filter($kids, function ($k) use ($canSee) {
    return $canSee($k[1] ?? null);
  }));
  if (count($kids)) $sideItems[] = ['group', [$label, $match, $item[3], $kids]];
}
@endphp
<div class="dc-app">
  <div class="dc-shell">
    <div class="dc-tb noprint">
      <a class="dc-logo" href="{{ route('dashboard') }}" title="{{ $brand['company_name'] ?? config('app.name') }}" aria-label="{{ $brand['company_name'] ?? $brand['app_name'] ?? config('app.name') }}">
        @if(!empty($brand['logo_path']) || !empty($brand['logo_dark_path']))
          @include('brand.logo', ['cls' => 'dc-brand-img'])
        @else
          <span class="dc-mark" aria-hidden="true">{{ strtoupper(mb_substr($brand['company_name'] ?? $brand['app_name'] ?? config('app.name'), 0, 1)) }}</span>
          <span class="dc-ws">{{ $brand['company_name'] ?? $brand['app_name'] ?? config('app.name') }}</span>
        @endif
      </a>
      @auth
      <button type="button" class="dc-search" data-spot-open title="Search (Ctrl/⌘ K)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:14px;height:14px"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><span class="dc-search-txt">Try searching “invoices”</span><kbd>⌘K</kbd></button>
      @endauth
      <div style="flex:1"></div>
      <div class="dc-tb-actions">
        @auth
        @php $canCreate = function ($mod) { return function_exists('user_can') ? user_can($mod, 'create') : true; }; @endphp
        <div class="has-drop">
          <button type="button" class="dc-new" data-drop-toggle aria-haspopup="menu" title="Quick create"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" style="width:14px;height:14px"><path d="M12 5v14M5 12h14"/></svg><span>New</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px;opacity:.85" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
          <div class="drop" role="menu" aria-label="Quick create">
            @if($canSee('leads.index') && $canCreate('sales'))<a href="{{ route('leads.create') }}" role="menuitem">New lead</a>@endif
            @if($canSee('clients.index') && $canCreate('sales'))<a href="{{ route('clients.create') }}" role="menuitem">New client</a>@endif
            @if($canSee('proposals.index') && $canCreate('sales'))<a href="{{ route('proposals.create') }}" role="menuitem">New proposal</a>@endif
            @if($canSee('projects.index') && $canCreate('work'))<a href="{{ route('projects.create') }}" role="menuitem">New project</a>@endif
            @if($canSee('tasks.index') && $canCreate('work'))<a href="{{ route('tasks.create') }}" role="menuitem">New task</a>@endif
            @if($canSee('meetings.index') && $canCreate('work'))<a href="{{ route('meetings.index') }}" role="menuitem">Schedule meeting</a>@endif
            @if($canSee('invoices.index') && $canCreate('billing'))<a href="{{ route('invoices.create') }}" role="menuitem">New invoice</a>@endif
            @if($canSee('payments.index') && $canCreate('billing'))<a href="{{ route('payments.create') }}" role="menuitem">Record payment</a>@endif
          </div>
        </div>
        @php
          try {
            $recentNotes = auth()->user()->notifications()->limit(5)->get();
            $unreadCount = auth()->user()->unreadNotifications()->count();
          } catch (\Throwable $e) { $recentNotes = collect(); $unreadCount = 0; }
        @endphp
        <div class="has-drop">
          <button type="button" class="dc-ibtn" data-drop-toggle aria-label="Notifications">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            @if($unreadCount > 0)<span class="bell-badge">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>@endif
          </button>
          <div class="drop drop-notes">
            <div class="notes-head"><span>Notifications</span>@if($unreadCount > 0)<form method="POST" data-notes-read data-notes-all action="{{ route('notifications.readAll') }}">@csrf<button class="link-btn">Mark all read</button></form>@endif</div>
            @forelse($recentNotes as $n)
              @php $nd = $n->data; @endphp
              <a class="note-item {{ is_null($n->read_at) ? 'unread' : '' }}" href="{{ !empty($nd['url']) ? $nd['url'] : route('notifications.index') }}">
                <div class="note-top"><b>{{ $nd['title'] ?? '' }}</b><time>{{ d($n->created_at, 'j M') }}</time></div>
                <span>{{ $nd['body'] ?? '' }}</span>
              </a>
            @empty
              <div class="note-item empty">No notifications yet.</div>
            @endforelse
            <a class="note-all" href="{{ route('notifications.index') }}">View all</a>
          </div>
        </div>
        <div class="has-drop acct-drop">
          <button type="button" class="dc-ibtn" data-drop-toggle aria-haspopup="menu" aria-label="Account menu" title="{{ auth()->user()->name }}">@include('partials.avatar', ['user' => auth()->user(), 'class' => 'avatar sm'])@if(auth()->user()->isAdmin() && ($unreadCount ?? 0) > 0)<span class="bell-badge">•</span>@endif</button>
          <div class="drop" role="menu" aria-label="Account">
            <div class="acct-head"><div class="acct-id">@include('partials.avatar', ['user' => auth()->user(), 'class' => 'avatar'])<div><b>{{ auth()->user()->name }}</b><span>{{ auth()->user()->email }}</span></div></div></div>
            <a href="{{ route('settings.account') }}" role="menuitem">My account</a>
            @if(auth()->user()->isAdmin())<a href="{{ route('settings.index') }}" role="menuitem">Settings</a>@endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" role="menuitem" style="width:100%;text-align:left">Log out</button></form>
          </div>
        </div>
        @endauth
        <button type="button" class="dc-ibtn" data-theme-toggle title="Toggle light / dark" aria-label="Toggle theme"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></button>
        <button type="button" class="dc-ibtn dc-burger" data-nav-toggle title="Menu" aria-label="Toggle navigation"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:16px;height:16px"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
      </div>
    </div>
    <div class="dc-bd">
      <div class="dc-rl noprint" aria-label="Sections">
        @php
        $rail = [
          ['Dashboard', 'dashboard', ['dashboard', 'dashboard.*'], '<rect x="4" y="4" width="7" height="7" rx="2"/><rect x="13" y="4" width="7" height="7" rx="2"/><rect x="4" y="13" width="7" height="7" rx="2"/><path d="M16.5 13v7M13 16.5h7"/>'],
          ['Activity', 'activity.index', ['activity.*'], '<circle cx="12" cy="12" r="8.5"/><path d="M8 13l3-3 2 2 3-4"/>'],
        ];
        @endphp
        @foreach($rail as $ri)
          @if($canSee($ri[1]))
          <a class="dc-rb{{ request()->routeIs(...$ri[2]) ? ' on' : '' }}" href="{{ route($ri[1]) }}" title="{{ $ri[0] }}" aria-label="{{ $ri[0] }}" @if(request()->routeIs(...$ri[2])) aria-current="page" @endif><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $ri[3] !!}</svg></a>
          @endif
        @endforeach
        <div class="dc-rx">
          @if($canSee('notifications.index'))
          <a class="dc-rb{{ request()->routeIs('notifications.*') ? ' on' : '' }}" href="{{ route('notifications.index') }}" title="Notifications" aria-label="Notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>@if(($unreadCount ?? 0) > 0)<span class="dc-dot" aria-hidden="true"></span>@endif</a>
          @endif
          @auth
          @if(auth()->user()->isAdmin())
          <a class="dc-rb{{ request()->routeIs('settings.*') ? ' on' : '' }}" href="{{ route('settings.index') }}" title="Settings" aria-label="Settings"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></a>
          @endif
          @endauth
        </div>
      </div>
      <nav class="dc-tr noprint" aria-label="Main">
        <div class="dc-tsec open" data-side-group="Dashboards">
          <button type="button" class="dc-t0" data-side-toggle aria-expanded="true"><span>Dashboards</span><span class="dc-pm" aria-hidden="true"></span></button>
          <div class="dc-tkids">
            <a class="dc-t1{{ request()->routeIs('dashboard.money') ? ' on' : '' }}" href="{{ route('dashboard.money') }}" @if(request()->routeIs('dashboard.money')) aria-current="page" @endif>Money</a>
            <a class="dc-t1{{ request()->routeIs('dashboard.work') ? ' on' : '' }}" href="{{ route('dashboard.work') }}" @if(request()->routeIs('dashboard.work')) aria-current="page" @endif>Work</a>
          </div>
        </div>
        @foreach($sideItems as $si)
        @if($si[0] === 'group')
        @php $g = $si[1]; @endphp
        <div class="dc-tsec {{ request()->routeIs(...$g[1]) ? 'open' : '' }}" data-side-group="{{ $g[0] }}">
          <button type="button" class="dc-t0 b" data-side-toggle aria-expanded="{{ request()->routeIs(...$g[1]) ? 'true' : 'false' }}"><span>{{ $g[0] }}</span><span class="dc-pm" aria-hidden="true"></span></button>
          <div class="dc-tkids">
          @foreach($g[3] as $k)
            <a class="dc-t1{{ request()->routeIs($k[1], ...$k[2]) ? ' on' : '' }}" href="{{ route($k[1]) }}" title="{{ $k[0] }}" @if(request()->routeIs($k[1], ...$k[2])) aria-current="page" @endif>{{ $k[0] }}</a>
          @endforeach
          </div>
        </div>
        @else
        @php $s = $si[1]; @endphp
        <a class="dc-t0 link{{ request()->routeIs($s[1], ...$s[2]) ? ' on' : '' }}" href="{{ route($s[1]) }}" @if(request()->routeIs($s[1], ...$s[2])) aria-current="page" @endif>{{ $s[0] }}</a>
        @endif
        @endforeach
      </nav>
      <div class="side-backdrop noprint" data-nav-toggle aria-hidden="true"></div>
      <div class="dc-main">
        <main class="content" id="main-content" tabindex="-1">
      @if(request()->routeIs('settings.*'))
      <nav class="subtabs noprint">
        @foreach($admin as $a)
          @if($a[2] === 'admin' && ! auth()->user()->isAdmin()) @continue @endif
          <a href="{{ route($a[1]) }}" class="{{ request()->routeIs($a[1]) || ($a[1] === 'settings.users.index' && request()->routeIs('settings.users.*')) ? 'active' : '' }}">{{ $a[0] }}</a>
        @endforeach
        <a href="{{ route('settings.account') }}" class="{{ request()->routeIs('settings.account*') ? 'active' : '' }}">My account</a>
      </nav>
      @endif
      @if(session('ok'))<div class="alert" data-flash>{{ session('ok') }}</div>@endif
      @if(session('warn'))<div class="alert warn" data-flash>{{ session('warn') }}</div>@endif
      @if(session('error'))<div class="err">{{ session('error') }}</div>@endif
      @if($errors->any())<div class="err"><ul style="margin:0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
      @if(auth()->check() && auth()->user()->isAdmin() && ! storage_link_ok())
      <div class="err">Uploaded files (logos, favicons, receipts) will not display — the <code>public/storage</code> link is missing on this server. Create it with <code>php artisan storage:link</code>.</div>
      @endif
      @yield('content')
      <p class="footer-note noprint">{{ $brand['company_name'] ?? '' }} · v{{ app_version() }} · self-hosted</p>
        </main>
      </div>
    </div>
  </div>
</div>
<div class="toasts" id="toasts" aria-live="polite"></div>
<div class="modal" id="confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-msg">
  <div class="modal-bg" data-modal-close></div>
  <div class="modal-card confirm-card">
    <div class="confirm-ic" id="confirm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></div>
    <h3 id="confirm-title">Are you sure?</h3>
    <p class="hint" id="confirm-msg" style="margin:0 0 18px"></p>
    <div class="form-actions"><button class="btn danger" id="confirm-ok">Delete</button><button class="btn sec" data-modal-close>Cancel</button></div>
  </div>
</div>
<div class="spot" id="spot" role="dialog" aria-label="Search" data-search-url="{{ route('search') }}">
  <div class="spot-bg" data-spot-close></div>
  <div class="spot-panel">
    <div class="spot-bar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input id="spot-input" placeholder="Search clients, invoices, tasks…" autocomplete="off" role="combobox" aria-expanded="false"></div>
    <div class="spot-results" id="spot-results"></div>
    <div class="spot-foot"><span><kbd>↑↓</kbd> navigate</span><span><kbd>↵</kbd> open</span><span><kbd>esc</kbd> close</span></div>
  </div>
</div>
<script src="{{ asset('assets/js/app.js').'?v=79' }}"></script>
</body>
</html>
