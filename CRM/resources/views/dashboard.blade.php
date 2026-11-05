@extends('layouts.app')
@section('title','Dashboard')
@section('head')<link rel="stylesheet" href="{{ asset('assets/css/dc.css').'?v=4' }}">@endsection
@section('content')
@php
  $hour = (int) date('H');
  $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
  $me = explode(' ', auth()->user()->name)[0] ?? 'there';
  $short = function ($n) { $n = (float) $n; if ($n >= 1000) return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.').'k'; return (string) (int) round($n); };
  $monRange = $ref->format('M j').' - '.$ref->copy()->endOfMonth()->format('M j, Y');
  $chase = $overdue->first();
  $funnelPct = fn ($v) => $funnelTotal > 0 ? round($v / $funnelTotal * 100) : 0;
  $dueSoonCount = $dueSoon->count();
  $topClients = $pipeClients->take(3);
  $xpTop = $pipeClients->first();
  $xpSecond = $pipeClients->skip(1)->first();
  $maxW = max(1, collect($week)->max('value'));
  $maxWon = max(1, collect($dealChart['months'])->max('won'));
  $dmaxIdx = 0;
  foreach ($dealChart['months'] as $di => $dm) { if ($dm['won'] >= collect($dealChart['months'])->max('won')) { $dmaxIdx = $di; break; } }
  $dmaxX = round($dmaxIdx * (300 / 5), 1);
  $dmaxY = round($dealChart['dxy']($maxWon), 1);
  $wonMonthTotal = collect($dealChart['months'])->sum('won');
  $recentInvoiceShown = $recentInvoices->count();
  $calBlanks = ($calStart - 1) % 7;
  $todayKey = $ref->isSameMonth(now()) ? now()->format('Y-m-d') : '';
@endphp
<div class="dc">

  <div class="dc-ch">
    <a class="dc-ib" href="{{ route('clients.create') }}" title="Add client" aria-label="Add client">+</a>
    @foreach($topClients as $tc)<a class="dc-cp" href="{{ $tc->client_id ? route('clients.show', $tc->client_id) : route('clients.index') }}"><span class="dc-av">{{ strtoupper(mb_substr($tc->client ?? '?', 0, 1)) }}</span>{{ \Str::limit($tc->client ?? 'Unassigned', 10) }}</a>@endforeach
    <div style="flex:1"></div>
    <a class="dc-ib" href="{{ route('reports.index') }}" title="Filters and reports" aria-label="Filters"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/></svg></a>
    <a class="dc-ib" href="{{ route('reports.export') }}" title="Download CSV" aria-label="Download"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg></a>
  </div>

  <div class="dc-ttl"><h1>{{ $greet }}, {{ $me }}</h1><div class="dc-tf"><span>{{ now()->format('l, j F Y') }}</span>
    <form method="GET" action="{{ route('dashboard') }}" class="dc-dp-form">
      <select name="month" class="dc-dp" onchange="this.form.submit()" aria-label="Dashboard month">
        @foreach($monthOptions as $mo)
          <option value="{{ $mo['value'] }}" {{ $selMonth === $mo['value'] ? 'selected' : '' }}>{{ $mo['label'] }}</option>
        @endforeach
      </select>
    </form>
  </div></div>

  <div class="dc-hr">
    <div style="flex:1 1 340px;min-width:0"><div class="dc-lb">Outstanding</div><div class="dc-hg">{{ money($outstanding) }}</div>
      @php $odShare = $outstanding > 0 ? round($overdueTotal / $outstanding * 100) : 0; @endphp
      <span class="dc-rd">{{ $odShare }}% overdue</span>
      <span class="dc-rd l">{{ money($overdueTotal) }}</span>
      <div class="dc-mt" style="margin-top:8px">vs billed {{ money($billed) }} · across {{ $stats['unpaid_invoices'] }} open invoices</div>
    </div>
    <div class="dc-kp">
      <a class="dc-k" href="{{ route('tasks.index') }}"><span>Open tasks</span><b>{{ $stats['open_tasks'] }}</b><span class="dc-krow">{{ $dueSoonCount }} due this week<span style="margin-left:auto">›</span></span></a>
      @if($chase)<a class="dc-k dk" href="{{ route('invoices.show', $chase->id) }}"><span>Needs chasing ☆</span><b>{{ money($chase->total) }}</b><span class="dc-krow">{{ $chase->number }}<span class="dc-wa">›</span></span></a>@endif
      <a class="dc-k" href="{{ route('invoices.index') }}"><span>Invoices</span><span class="dc-gp">{{ $stats['unpaid_invoices'] }}</span><span>▾ unpaid</span></a>
      <a class="dc-k rr" href="{{ route('proposals.index') }}"><span>Pipeline</span><span class="dc-rp">{{ $short($openPipe) }}</span><span>▴ {{ $openDeals }} proposals</span></a>
      <a class="dc-k" href="{{ route('reports.index') }}"><span>Collected</span><span class="dc-gp">{{ $collectionRate }}%</span><span>▴ {{ $targetPct }}% of target</span></a>
    </div>
  </div>

  <div class="dc-sg">
    @foreach($funnel as $f)<div class="dc-sp"><span class="dc-dt" style="background:{{ $f['color'] }}"></span>{{ money($f['value']) }}<b>{{ $funnelPct($f['value']) }}%</b></div>@endforeach
    <a class="dc-dk" href="{{ route('reports.index') }}">Details</a>
  </div>

  <div class="dc-g2">
    <div class="dc-gl">
      <div class="dc-c2">
        <div class="dc-gc"><div class="dc-fb"><span class="dc-lb" style="font-size:13px">Collections</span><span class="dc-dd">{{ $stats['unpaid_invoices'] }} open</span></div>
          @foreach([['Collected', $collected, '#1f9d6a', $funnelPct($collected).'%'], ['Overdue', $overdueTotal, '#d63a5c', $funnelPct($overdueTotal).'%'], ['Pending', max(0, $outstanding - (float) $overdueTotal), '#f0b429', $funnelPct(max(0, $outstanding - (float) $overdueTotal)).'%']] as $lr)<div class="dc-ls"><span class="dc-ic" style="background:{{ $lr[2] }}"></span>{{ $lr[0] }}<span class="dc-v">{{ number_format($lr[1], 0) }}</span><span class="dc-pc">{{ $lr[3] }}</span></div>@endforeach
          <div class="dc-ls"><span class="dc-ic" style="background:#111"></span>Pipeline<span class="dc-v">{{ number_format($openPipe, 0) }}</span><span class="dc-pc">{{ $openDeals }}</span></div>
        </div>
        <div class="dc-gc"><div class="dc-fb"><span class="dc-lb" style="font-size:13px">Deals closed by month</span><span class="dc-dd">{{ money($wonMonthTotal) }} won</span></div>
          <div class="dc-bx">@foreach($dealChart['months'] as $i=>$dm)@php $isMax = $dm['won'] >= $maxWon && $maxWon > 0; @endphp<div class="{{ $dm['won'] > 0 ? '' : 'dc-ht' }}{{ $isMax ? ' hot' : '' }}" style="height:{{ max(8, round($dm['won'] / $maxWon * 100)) }}%" title="{{ $dm['label'] }}: {{ money($dm['won']) }} won"><span class="dc-ico">{{ strtoupper(mb_substr($dm['label'], 0, 1)) }}</span></div>@endforeach</div>
        </div>
      </div>

      <div class="dc-gc"><div class="dc-fb"><span class="dc-dd" style="gap:8px"><span class="dc-ic" style="width:18px;height:18px;border-radius:50%;background:var(--dc-accent);display:inline-block"></span>Weekly performance</span><div class="dc-sw"><span class="a" data-wk="rev">Revenue</span><span data-wk="pipe">Pipeline</span><span data-wk="done">Tasks</span></div></div>
        <div class="dc-pf">
          <div class="dc-pr"><div style="font-size:10px;opacity:.85">Average weekly</div><div>Won revenue<b>{{ money($perf['pay']) }}</b></div><div>Pipeline<b>{{ money($deals['pipeW']['t']) }}</b></div><div>Tasks done<b>{{ $perf['done'] }}</b></div></div>
          <div class="dc-ch2"><div class="dc-wbars" id="dcWkBars" role="img" aria-label="Weekly bars"></div>
          <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--dc-faint);padding:0 6px">@foreach($week as $wd)<span>{{ $wd['label'] }}</span>@endforeach</div></div>
        </div>
      </div>
    </div>

    <div class="dc-gx">
      <div class="dc-th"><span style="flex:1.3">Client</span><span style="flex:1.1">Amount</span><span style="flex:1.1">Deals</span><span style="flex:.7">Share</span><span style="flex:1;text-align:right">Close date</span></div>
      @forelse($pipeClients as $pi)
      <div class="dc-rw" data-xp="{{ $loop->first ? '1' : '0' }}" data-client="{{ $pi->client ?? 'Unassigned' }}" data-amount="{{ money($pi->t) }}" data-share="{{ $pi->share }}" data-gap="{{ $pi->gap ?? 'Open' }}" data-gaptone="{{ $pi->gapTone }}" data-valid="{{ $pi->valid_until ? d($pi->valid_until) : 'no expiry' }}" data-number="{{ $pi->number ?? '' }}" data-count="{{ $pi->c }}" data-href="{{ route('proposals.show', $pi->id) }}"><div class="dc-cn"><span class="dc-av">{{ $pi->initial }}</span>{{ \Str::limit($pi->client ?? 'Unassigned', 16) }}</div><div style="flex:1.1">{{ money($pi->t) }}</div><div style="flex:1.1"><span class="dc-pk">{{ $pi->c }}</span><span class="dc-pk l">{{ \Str::limit($pi->number ?? '', 12) }}</span></div><div style="flex:.7">{{ $pi->share }}%</div><div style="flex:1;text-align:right"><span class="dc-pk">{{ $pi->valid_until ? d($pi->valid_until, 'j M') : '—' }}</span><span class="dc-xr">{{ $loop->first ? '⌃' : '⌄' }}</span></div></div>
      @if($loop->first)
      <div class="dc-xp" id="dcXp">
        <div class="dc-tgs"><span class="dc-tg w">Open deal</span><span class="dc-tg r" id="dcXpGap">{{ $pi->gap ?? 'Open' }}</span><span class="dc-tg w" id="dcXpValid">Valid till {{ $pi->valid_until ? d($pi->valid_until) : '—' }}</span></div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px"><span class="dc-lb" style="font-size:13px">Pipeline by client</span><span class="dc-rd">{{ $pipeClients->count() }} &nbsp;{{ money($pipeClients->sum('t')) }}</span></div>
        <div class="dc-wp">
          <div class="dc-ht" style="flex:1.3 1 180px;display:flex;flex-direction:column;justify-content:space-between;min-height:120px"><span class="dc-tg w" style="align-self:flex-start" id="dcXpClient">{{ \Str::limit($pi->client ?? 'Unassigned', 18) }}</span><span class="dc-big"><span id="dcXpShare">{{ $pi->share }}%</span> <i id="dcXpAmount">{{ money($pi->t) }}</i></span></div>
          <div style="flex:1 1 150px;display:flex;flex-direction:column;gap:8px;padding:0;min-height:0">
            @if($xpSecond)<div class="dc-sm" style="flex:1"><span>{{ \Str::limit($xpSecond->client ?? 'Unassigned', 18) }}</span><b style="font-size:15px">{{ $xpSecond->share }}% <span style="color:var(--dc-faint);font-weight:500">{{ money($xpSecond->t) }}</span></b></div>@endif
            <div class="dc-sm" style="flex:1"><span>Closed last month</span><b style="font-size:15px">{{ $deals['wonPM']['c'] }} deal <span style="color:var(--dc-faint);font-weight:500">{{ money($deals['wonPM']['t']) }}</span></b></div>
          </div>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:14px"><span class="dc-lb" style="font-size:12px">Won this week vs last</span><span class="dc-rd">{{ $deals['wonChg'] > 0 ? '▲' : ($deals['wonChg'] < 0 ? '▼' : '●') }} {{ abs($deals['wonChg']) }}%</span></div>
        <svg viewBox="0 0 300 120" width="100%" role="img" aria-label="Won deals trend"><defs><linearGradient id="dcg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#d63a5c" stop-opacity=".16"></stop><stop offset="1" stop-color="#d63a5c" stop-opacity="0"></stop></linearGradient></defs><path d="{{ $dealChart['wonArea'] }}" fill="url(#dcg)"></path><path d="{{ $dealChart['wonLine'] }}" fill="none" stroke="#d63a5c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>@if($maxWon > 0)<circle cx="{{ $dmaxX }}" cy="{{ $dmaxY }}" r="5" fill="#111" stroke="#fff" stroke-width="2"><title>{{ $dealChart['months'][$dmaxIdx]['label'] }}: {{ money($maxWon) }}</title></circle>@endif</svg>
      </div>
      @endif
      @empty
      <div class="dc-em">No open pipeline.<small style="margin-top:4px">Create a proposal to start tracking deals.</small></div>
      @endforelse
      @if($chase)<div class="dc-rw dark"><div class="dc-cn"><span class="dc-av" style="background:var(--dc-accent);color:#fff">!</span>{{ $chase->number }}</div><div style="flex:1.1">{{ money($chase->total) }}</div><div style="flex:1.1"><span class="dc-tg r">Overdue</span></div><div style="flex:.7">{{ $odShare }}%</div><div style="flex:1;text-align:right"><a class="dc-pk" style="background:#fff;color:#111;text-decoration:none" href="{{ route('invoices.show', $chase->id) }}">{{ d($chase->due_date, 'j M') }}</a></div></div>@endif
    </div>
  </div>

  <div class="dc-g2" style="margin-top:16px">
    <section class="dc-gc" style="flex:1 1 440px;padding:16px"><div class="dc-sh2"><h3>Today's focus</h3><a href="{{ route('tasks.index') }}">All tasks →</a></div>
      @php $fiShown = 0; @endphp
      @foreach($overdue->take(2) as $o)<div class="dc-fi"><span class="dc-fd"></span><div><b>Chase {{ $o->number }}</b><small>{{ money($o->total) }} · {{ abs(now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($o->due_date)->startOfDay(), false)) }} days overdue</small></div><span class="dc-go">Overdue</span></div>@php $fiShown++; @endphp @endforeach
      @foreach($dueSoon->take(4 - $fiShown) as $t)<div class="dc-fi"><span class="dc-fd"></span><div><b>{{ \Str::limit($t->title, 42) }}</b><small>{{ ucfirst($t->priority ?? '') }} · due {{ d($t->due_date) }}</small></div><span class="dc-go">{{ ucfirst($t->priority ?? '') }}</span></div>@endforeach
      @if($fiShown === 0 && $dueSoon->isEmpty())<div class="dc-em">Nothing due soon.<small style="margin-top:4px">Overdue work is pinned in Today's focus.</small></div>@endif
    </section>
    <section class="dc-gc" style="flex:1 1 440px;padding:16px"><div class="dc-sh2"><h3>{{ $ref->format('F Y') }}</h3><a href="{{ route('tasks.index') }}">Tasks →</a></div>
      <div class="dc-cg"><div class="h">M</div><div class="h">T</div><div class="h">W</div><div class="h">T</div><div class="h">F</div><div class="h">S</div><div class="h">S</div>@for($b = 0; $b < $calBlanks; $b++)<div></div>@endfor @for($d = 1; $d <= $calDays; $d++)@php $dk = sprintf('%04d-%02d-%02d', $calY, $calM, $d); @endphp<div class="dc-cd{{ $dk === $todayKey ? ' td' : '' }}">{{ $d }}@if(!empty($dueMap[$dk]))<i></i>@endif</div>@endfor</div>
      <small style="margin-top:12px;font-weight:700;letter-spacing:.08em">COLLECTIONS THIS WEEK</small><div style="display:flex;gap:6px">@foreach($week as $wd)<div style="flex:1;text-align:center"><div class="dc-wb" style="@if($wd['value'] > 0)background:var(--dc-accent);@endif"></div><small>{{ $wd['label'] }}</small></div>@endforeach</div>
    </section>
  </div>

  <div class="dc-g2" style="margin-top:16px">
    <section class="dc-gc" style="flex:1 1 440px;padding:16px"><div class="dc-sh2"><h3>Cashflow — last 6 months</h3><a href="{{ route('reports.index') }}">Full reports →</a></div>
      @php $cfMax = max(1, collect($months)->flatMap(fn ($x) => [$x['revenue'], $x['expenses']])->max()); @endphp
      <svg viewBox="0 0 420 110" width="100%" role="img" aria-label="Monthly money in and out"><g stroke="var(--dc-line2)" stroke-dasharray="2 4"><path d="M0 20H420M0 55H420"></path></g><g fill="var(--dc-ink)">@foreach($months as $i=>$cm)<rect x="{{ 14 + $i * 70 }}" y="{{ 84 - max(8, round($cm['revenue'] / $cfMax * 64)) }}" width="20" height="{{ max(8, round($cm['revenue'] / $cfMax * 64)) }}" rx="4"></rect>@endforeach</g><g fill="var(--dc-pale)">@foreach($months as $i=>$cm)<rect x="{{ 38 + $i * 70 }}" y="{{ 84 - max(8, round($cm['expenses'] / $cfMax * 64)) }}" width="20" height="{{ max(8, round($cm['expenses'] / $cfMax * 64)) }}" rx="4"></rect>@endforeach</g><g font-size="10" fill="var(--dc-muted)" text-anchor="middle">@foreach($months as $i=>$cm)<text x="{{ 36 + $i * 70 }}" y="104">{{ $cm['label'] }}</text>@endforeach</g></svg>
      <small><b style="color:var(--dc-ink)">●</b> Money in {{ money(collect($months)->sum('revenue')) }} &nbsp; <b style="color:var(--dc-pale)">●</b> Money out {{ money(collect($months)->sum('expenses')) }}</small>
    </section>
    <section class="dc-gc" style="flex:1 1 440px;padding:16px"><div class="dc-sh2"><h3>Money position</h3><a href="{{ route('invoices.index') }}">Invoices →</a></div>
      @php $ring = round($collectionRate / 100 * 301.6, 1); @endphp
      <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap"><svg viewBox="0 0 120 120" width="116" role="img" aria-label="{{ $collectionRate }} percent collected"><circle cx="60" cy="60" r="48" fill="none" stroke="var(--dc-line2)" stroke-width="12"></circle><circle cx="60" cy="60" r="48" fill="none" stroke="var(--dc-accent)" stroke-width="12" stroke-linecap="round" stroke-dasharray="{{ $ring }} 301.6" transform="rotate(-90 60 60)"></circle><text x="60" y="64" text-anchor="middle" font-size="22" font-weight="700" fill="var(--dc-ink)">{{ $collectionRate }}%</text><text x="60" y="80" text-anchor="middle" font-size="9" fill="var(--dc-muted)">collected</text></svg>
      <div style="flex:1 1 180px;font-size:13px"><div style="display:flex;justify-content:space-between;padding:7px 0"><span>Billed</span><b>{{ money($billed) }}</b></div><div style="display:flex;justify-content:space-between;padding:7px 0;border-top:1px solid var(--dc-line)"><span>Collected</span><b>{{ money($collected) }}</b></div><div style="display:flex;justify-content:space-between;padding:7px 0;border-top:1px solid var(--dc-line)"><span>Outstanding</span><b style="color:var(--dc-accent)">{{ money($outstanding) }}</b></div></div></div>
    </section>
  </div>

  <div class="dc-g2" style="margin-top:16px">
    <section class="dc-gc" style="flex:1 1 440px;padding:16px"><div class="dc-sh2"><h3>Recent invoices</h3><a class="dc-btn" href="{{ route('invoices.create') }}" style="text-decoration:none">+ New</a></div>
      <div class="dc-th"><span style="flex:1.4">Number</span><span style="flex:1.2">Total</span><span style="flex:1;text-align:right">Status</span></div>
      @forelse($recentInvoices->take(4) as $ri)<a class="dc-rw" href="{{ route('invoices.show', $ri->id) }}" style="text-decoration:none"><b style="flex:1.4">{{ $ri->number }}</b><span style="flex:1.2">{{ money($ri->total) }}</span><span style="flex:1;text-align:right"><span class="dc-tg {{ $ri->status === 'overdue' ? 'r' : 'l' }}">{{ ucfirst($ri->status) }}</span></span></a>@empty<div class="dc-em">No invoices yet.</div>@endforelse
      <small style="margin:8px 4px 0">Showing {{ min(4, $recentInvoiceShown) }} of {{ $recentInvoiceShown }} recent invoices</small>
    </section>
    <section class="dc-gc" style="flex:1 1 440px;padding:16px"><div class="dc-sh2"><h3>Recent tasks</h3><a class="dc-btn" href="{{ route('tasks.create') }}" style="text-decoration:none">+ New</a></div>
      <div class="dc-th"><span style="flex:2">Title</span><span style="flex:.8">Status</span><span style="flex:1.4;text-align:right">Due</span></div>
      @forelse($recentTasks as $rt)<div class="dc-rw"><div style="flex:2;font-weight:600">{{ \Str::limit($rt->title, 26) }}<small>{{ \Str::limit($rt->project ?? 'No project', 24) }}</small></div><div style="flex:.8"><span class="dc-tg l">{{ ucfirst(str_replace('_', ' ', $rt->status)) }}</span></div><div style="flex:1.4;text-align:right">{{ d($rt->due_date) ?: '—' }} <span class="dc-tg {{ in_array(strtolower($rt->priority ?? ''), ['urgent', 'high']) ? 'r' : 'l' }}">{{ ucfirst($rt->priority ?? 'normal') }}</span></div></div>@empty<div class="dc-em">No tasks yet.</div>@endforelse
    </section>
  </div>

  <div class="dc-g2" style="margin-top:16px">
    <section class="dc-gc" style="flex:1 1 440px;padding:16px"><div class="dc-sh2"><h3>Overdue invoices</h3><a href="{{ route('invoices.index', ['status' => 'overdue']) }}">View all →</a></div>
      <div class="dc-th"><span style="flex:1.4">Number</span><span style="flex:1.2">Due</span><span style="flex:1;text-align:right">Total</span></div>
      @forelse($overdue as $o)<div class="dc-rw dark"><b style="flex:1.4">{{ $o->number }}</b><span style="flex:1.2">{{ d($o->due_date) ?: '—' }}</span><b style="flex:1;text-align:right">{{ money($o->total) }}</b></div>@empty<div class="dc-em">Nothing overdue. 🎉</div>@endforelse
    </section>
    <section class="dc-gc" style="flex:1 1 440px;padding:16px"><div class="dc-sh2"><h3>Due within 7 days</h3><a href="{{ route('tasks.index') }}">View all →</a></div>
      <div class="dc-th"><span style="flex:2">Task</span><span style="flex:1">Due</span><span style="flex:1;text-align:right">Status</span></div>
      @forelse($dueSoon as $t)<div class="dc-rw"><div style="flex:2;font-weight:600">{{ \Str::limit($t->title, 30) }}</div><div style="flex:1">{{ d($t->due_date) ?: '—' }}</div><div style="flex:1;text-align:right"><span class="dc-tg l">{{ ucfirst(str_replace('_', ' ', $t->status)) }}</span></div></div>@empty<div class="dc-em">Nothing due soon.<small style="margin-top:4px">Overdue work is pinned in Today's focus.</small></div>@endforelse
    </section>
  </div>

  <section class="dc-gc" style="margin-top:16px;padding:16px 18px"><div class="dc-sh2"><h3>Recent activity</h3><a href="{{ route('activity.index') }}">View all →</a></div>@forelse($recentActivity as $a)<div class="dc-ac"><span class="dc-ci">{{ strtoupper(mb_substr($a->user->name ?? 'C', 0, 1)) }}</span><div><b>{{ $a->description }}</b><small>{{ $a->user->name ?? 'Client' }} · {{ d($a->created_at, 'j M, h:i A') }}</small></div></div>@empty<div class="dc-em">No activity yet.</div>@endforelse</section>
  <div class="dc-foot">{{ $brand['company_name'] ?? config('app.name') }} · v{{ app_version() }} · self-hosted</div>

</div>
<script>
(function(){
  // Weekly panel switch: revenue / pipeline / tasks bars.
  var data = {
    rev: @json(array_values($payWeek)),
    pipe: @json($pipeWeek),
    done: @json($doneWeek)
  };
  var labels = @json(array_column($week, 'label'));
  function draw(key){
    var vals = data[key] || [];
    var mx = Math.max.apply(null, vals.concat([0]));
    var host = document.getElementById('dcWkBars');
    if(!host) return;
    host.innerHTML = '';
    vals.forEach(function(v, i){
      var d = document.createElement('div');
      var h = mx > 0 ? Math.max(8, Math.round(v / mx * 100)) : 8;
      d.style.height = h + '%';
      if(v >= mx && mx > 0) d.classList.add('hot');
      var s = document.createElement('span');
      s.className = 'dc-ico';
      s.textContent = (labels[i] || '').charAt(0);
      d.appendChild(s);
      host.appendChild(d);
    });
  }
  document.querySelectorAll('.dc-sw span').forEach(function(el){
    el.addEventListener('click', function(){
      document.querySelectorAll('.dc-sw span').forEach(function(x){ x.classList.remove('a'); });
      el.classList.add('a');
      draw(el.getAttribute('data-wk'));
    });
  });
  draw('rev');
  // Pipeline rows: click to expand a client in the spotlight panel.
  var xp = document.getElementById('dcXp');
  document.querySelectorAll('.dc-rw[data-xp]').forEach(function(row){
    row.style.cursor = 'pointer';
    row.addEventListener('click', function(){
      var d = row.dataset;
      document.getElementById('dcXpClient').textContent = (d.client || '').slice(0, 18);
      document.getElementById('dcXpShare').textContent = d.share + '%';
      document.getElementById('dcXpAmount').textContent = d.amount;
      document.getElementById('dcXpGap').textContent = d.gap;
      document.getElementById('dcXpValid').textContent = 'Valid till ' + d.valid;
      document.querySelectorAll('.dc-rw[data-xp] .dc-xr').forEach(function(x){ x.textContent = '⌄'; });
      var xr = row.querySelector('.dc-xr');
      if(xr) xr.textContent = '⌃';
      if(xp) xp.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  });
})();
</script>
@endsection
