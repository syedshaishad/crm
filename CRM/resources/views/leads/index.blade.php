@extends('layouts.app')
@section('title','Leads')
@section('content')
@php
  $stages = \Modules\Leads\Lead::stages();
  $fTotal = max(1, $funnel->sum());
  $fColors = ['new'=>'#8e8e93','contacted'=>'#0a84ff','qualified'=>'#ff9f0a','proposal'=>'#bf5af2','won'=>'#30d158','lost'=>'#ff453a'];
  $bColor = ['new'=>'b-grey','contacted'=>'b-blue','qualified'=>'b-amber','proposal'=>'b-blue','won'=>'b-green','lost'=>'b-red'];
  $wa = function ($phone, $name) {
    $d = preg_replace('/\D+/', '', (string) $phone);
    if (strlen($d) === 10) $d = '91'.$d;
    if ($d === '') return '';
    $msg = 'Hello '.($name ?: 'there').', this is '.(setting('brand.company_name', $brand['company_name'] ?? 'us')).'. Following up on your enquiry — is this a good time to talk?';
    return 'https://wa.me/'.$d.'?text='.urlencode($msg);
  };
@endphp
<div class="grid stats cols-5">
  <div class="stat"><div class="lbl">Open leads</div><b>{{ $stats['open'] }}</b><div class="sub">not won / lost</div></div>
  <div class="stat"><div class="lbl">Won this month</div><b>{{ $stats['won_month'] }}</b><div class="sub">converted + direct</div></div>
  <div class="stat"><div class="lbl">Pipeline value</div><b>{{ money($stats['pipeline']) }}</b><div class="sub">open leads</div></div>
  <div class="stat"><div class="lbl">Follow-ups due</div><b>{{ $stats['followups'] }}</b><div class="sub">next 7 days</div></div>
  <a class="stat" href="{{ route('leads.index', array_merge(request()->except('page'), ['stale' => ($stale ?? false) ? null : 1])) }}" style="text-decoration:none"><div class="lbl">Gone stale</div><b>{{ $stats['stale'] }}</b><div class="sub">{{ ($stale ?? false) ? 'showing stale only — click to clear' : 'quiet '.\Modules\Leads\Lead::staleDays().'+ days — click to filter' }}</div></a>
</div>

<div class="card" style="margin-top:22px">
<div class="card-head"><h3>Leads</h3><div class="btnrow" style="gap:8px"><div class="seg" role="tablist" aria-label="Views"><a class="{{ ($view ?? 'list')==='list' ? 'on' : '' }}" href="{{ route('leads.index', array_merge(request()->except('page'), ['view' => 'list'])) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>Table</a><a class="{{ ($view ?? '')==='board' ? 'on' : '' }}" href="{{ route('leads.index', array_merge(request()->except('page'), ['view' => 'board'])) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M15 4v16"/></svg>Kanban</a></div><button class="btn sm sec" data-modal-open="import-modal">Import</button><a class="btn sm" href="{{ route('leads.create') }}">+ New</a></div></div>
<div class="funnel" style="margin-bottom:6px">
@foreach(['new','contacted','qualified','proposal','won','lost'] as $fs)<div data-stage="{{ $fs }}" style="width:{{ max(($funnel[$fs] ?? 0) > 0 ? 9 : 0, round(($funnel[$fs] ?? 0) / $fTotal * 100, 1)) }}%;background:{{ $fColors[$fs] }}" title="{{ $stages[$fs] }}: {{ $funnel[$fs] ?? 0 }}">{{ $funnel[$fs] ?? 0 }}</div>@endforeach
</div>
<div class="funnel-legend" style="margin-bottom:12px">@foreach($stages as $fs=>$fl)<span data-stage="{{ $fs }}"><span class="dot" style="background:{{ $fColors[$fs] }}"></span>{{ $fl }} ({{ $funnel[$fs] ?? 0 }})</span>@endforeach</div>
<form method="GET" data-ajax-filter data-region="leads-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">@if(($view ?? 'list')==='board')<input type="hidden" name="view" value="board">@endif<input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search name, company, phone, email…"><select class="in" style="margin:0;flex:1;min-width:130px" name="stage"><option value="">All stages</option>@foreach($stages as $sv=>$sl)<option value="{{ $sv }}" {{ ($stage ?? '')===$sv?'selected':'' }}>{{ $sl }}</option>@endforeach</select><select class="in" style="margin:0;flex:1;min-width:130px" name="source"><option value="">All sources</option>@foreach($sources as $src)<option value="{{ $src }}" {{ ($source ?? '')===$src?'selected':'' }}>{{ ucfirst($src) }}</option>@endforeach</select><button class="btn sec sm">Filter</button>@if(($q ?? '') !== '' || ($stage ?? '') !== '' || ($source ?? '') !== '')<a class="btn sm sec" href="{{ route('leads.index', ($view ?? 'list')==='board' ? ['view' => 'board'] : []) }}">Clear</a>@endif</form>
<form method="POST" action="{{ route('leads.store') }}" data-ajax-add data-region="leads-list" class="noprint quickadd" style="display:flex;gap:8px;margin-bottom:6px;flex-wrap:wrap;align-items:center">@csrf
<div class="cap" style="flex-basis:100%">Quick add — name + phone is enough</div>
<input class="in" style="margin:0;flex:2;min-width:160px" name="name" required placeholder="Quick add: lead name ↵" autocomplete="off">
<input class="in" style="margin:0;flex:1;min-width:140px" name="phone" placeholder="Phone (10-digit)" autocomplete="off">
<select class="in" style="margin:0;flex:1;min-width:130px" name="source">@foreach($sources as $src)<option value="{{ $src }}">{{ ucfirst($src) }}</option>@endforeach</select>
<button class="btn sm">Add</button></form>
@if(($view ?? 'list')==='board')
<div data-ajax-region="leads-list">
<div class="kanban">
@foreach(['new','contacted','qualified','proposal','won','lost'] as $fi => $fs)
@php $prevStage = $fi > 0 ? ['new','contacted','qualified','proposal','won','lost'][$fi - 1] : null; $nextStage = $fi < 5 ? ['new','contacted','qualified','proposal','won','lost'][$fi + 1] : null; @endphp
<div class="kan-col" data-stage="{{ $fs }}">
<div class="kan-head"><span class="dot" style="background:{{ $fColors[$fs] }}"></span><b>{{ $stages[$fs] }}</b><span class="n">{{ count($board[$fs] ?? []) }}</span></div>
<div class="kan-list" data-drop="{{ $fs }}">
@forelse($board[$fs] ?? [] as $l)
<div class="kan-card" draggable="true" data-id="{{ $l->id }}">
<b><a href="{{ route('leads.edit',$l) }}">{{ $l->company ?: $l->name }}</a></b>
@if($l->company)<div class="hint">{{ $l->name }}</div>@endif
<div class="kan-meta">@if($l->value > 0)<b>{{ money($l->value) }}</b>@endif @if($l->phone)<a href="tel:{{ preg_replace('/\D+/','',$l->phone) }}">{{ $l->phone }}</a>@endif @if(!in_array($l->stage,['won','lost'],true))<span class="chip">★{{ $l->score() }}</span>@if($l->isStale())<span class="badge b-red">stale</span>@endif @endif</div>
@if($l->city || $l->email)<div class="hint">{{ trim(($l->city ?? '').(($l->city && $l->email) ? ' · ' : '').($l->email ?? '')) }}</div>@endif
@if($l->follow_up_on)<div class="hint">Follow-up: {{ d($l->follow_up_on) }}</div>@endif
<div class="kan-foot">
<div class="kan-nav noprint">@if($prevStage)<form method="POST" action="{{ route('leads.move',$l) }}" style="display:inline">@csrf<input type="hidden" name="stage" value="{{ $prevStage }}"><button class="kan-arrow" title="Move to {{ $stages[$prevStage] }}">‹</button></form>@endif @if($nextStage)<form method="POST" action="{{ route('leads.move',$l) }}" style="display:inline">@csrf<input type="hidden" name="stage" value="{{ $nextStage }}"><button class="kan-arrow" title="Move to {{ $stages[$nextStage] }}">›</button></form>@endif</div>
<form method="POST" action="{{ route('leads.move',$l) }}" class="kan-move">@csrf<select name="stage" onchange="this.form.submit()" title="Move to stage">@foreach($stages as $sv=>$sl)<option value="{{ $sv }}" {{ $l->stage===$sv?'selected':'' }}>{{ $sl }}</option>@endforeach</select></form>
<span class="kan-links"><a href="{{ route('leads.edit',$l) }}" title="Edit">Edit</a>@if(!in_array($l->stage,['won','lost'])) · <form method="POST" action="{{ route('leads.convert',$l) }}" style="display:inline" data-confirm="Convert {{ $l->name }} to client?" data-confirm-tone="brand" data-confirm-title="Convert lead?" data-confirm-ok="Convert">@csrf<button class="link-btn" type="submit">Convert</button></form>@endif</span>
</div>
</div>
@empty<div class="kan-empty">Drop leads here</div>@endforelse
</div>
</div>
@endforeach
</div>
<p class="hint noprint" style="margin-top:10px">Tip: drag a card to another column to move it. Showing up to 500 filtered leads.</p>
</div>
@else
<div class="bulkbar noprint" data-bulk-bar data-bulk-url="{{ route('leads.bulk') }}" data-region="leads-list" hidden>
<span class="sel"><b data-bulk-count>0</b> selected</span>
<select data-bulk-value name="stage"><option value="">Set stage…</option>@foreach($stages as $sv=>$sl)<option value="{{ $sv }}">{{ $sl }}</option>@endforeach</select>
<button class="btn sm sec" data-bulk-apply="stage">Apply</button>
<span class="spacer"></span>
<button class="btn sm danger" data-bulk-apply="delete">Delete</button>
</div>
<div data-ajax-region="leads-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th style="width:34px"><input type="checkbox" data-bulk-all title="Select all"></th><th>Lead</th><th>Contact</th><th>Source</th><th style="text-align:right">Value</th><th>Follow-up</th><th>Stage</th><th></th></tr>
@forelse($leads as $l)<tr>
<td><input type="checkbox" class="bulk-check" value="{{ $l->id }}" title="Select"></td>
<td><b><a href="{{ route('leads.edit',$l) }}">{{ $l->company ?: $l->name }}</a></b>@if($l->company)<div class="hint">{{ $l->name }}</div>@endif @if($l->converted_client_id)<div class="hint">→ <a href="{{ route('clients.show',$l->converted_client_id) }}">client</a></div>@endif</td>
<td>@if($l->phone)<a href="tel:{{ preg_replace('/\D+/','',$l->phone) }}">{{ $l->phone }}</a>@endif @if($l->email)<div class="hint">{{ $l->email }}</div>@endif @if($l->city)<div class="hint">{{ $l->city }}@if($l->state), {{ $l->state }}@endif</div>@endif</td>
<td><span class="badge b-grey">{{ ucfirst($l->source) }}</span></td>
<td class="nowrap" style="text-align:right">@if($l->value > 0)<b>{{ money($l->value) }}</b>@else <span class="hint">—</span>@endif</td>
<td class="nowrap">@if($l->follow_up_on)<span class="{{ $l->follow_up_on->isPast() && !in_array($l->stage,['won','lost']) ? 'badge b-red' : '' }}">{{ d($l->follow_up_on) }}</span>@else <span class="hint">—</span>@endif</td>
<td><span class="badge {{ $bColor[$l->stage] ?? 'b-grey' }}">{{ $stages[$l->stage] ?? $l->stage }}</span>@if(!in_array($l->stage,['won','lost'],true)) <span class="chip" title="Nurture score: stage + deal size − silence">★{{ $l->score() }}</span>@if($l->isStale()) <span class="badge b-red" title="No touch for {{ \Modules\Leads\Lead::staleDays() }}+ days">stale</span>@endif @endif</td>
<td><div class="row-actions">
@if($l->phone && ($w = $wa($l->phone, $l->name)))<a class="icon-btn" href="{{ $w }}" target="_blank" title="Chat on WhatsApp"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.4A8.5 8.5 0 1 1 21 11.5z"/></svg>WhatsApp</a>@endif
@if(!in_array($l->stage,['won','lost']))<form method="POST" action="{{ route('leads.bump',$l) }}" style="display:inline" title="Push follow-up out 7 days">@csrf<button class="icon-btn">+7d</button></form>@endif
@if(!in_array($l->stage,['won','lost']))<form method="POST" action="{{ route('leads.convert',$l) }}" style="display:inline">@csrf<button class="icon-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>Convert</button></form>@endif
<a class="icon-btn" href="{{ route('leads.edit',$l) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Edit</a>
<form method="POST" action="{{ route('leads.destroy',$l) }}" style="display:inline" data-confirm="Delete lead {{ $l->name }}?">@csrf @method('DELETE')<button class="icon-btn danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>Delete</button></form></div></td></tr>
@empty<tr><td colspan="8"><div class="empty">No leads found.<br><br><a class="btn sm" href="{{ route('leads.create') }}">Add your first lead</a></div></td></tr>@endforelse</table></div>
{{ $leads->links() }}</div>
@endif
</div>
<div class="modal" id="import-modal" role="dialog" aria-label="Import leads">
  <div class="modal-bg" data-modal-close></div>
  <div class="modal-card">
    <h3>Import leads</h3>
    <p class="hint" style="margin:0 0 12px">CSV with a header row. Required: <code>name</code>. Optional: <code>company, phone, email, city, state, source, stage, value, follow_up_on, notes</code>. Duplicates skipped, max 1000 rows. <a href="{{ route('export.csv','leads') }}">Download sample</a> · <a href="{{ route('import.index') }}">Full import page</a></p>
    <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data">@csrf<input type="hidden" name="type" value="leads">
    <label class="f">CSV file</label><input class="in" type="file" name="file" accept=".csv,.txt" required>
    <div class="form-actions" style="margin-top:16px"><button class="btn" type="submit">Import</button><button class="btn sec" data-modal-close type="button">Cancel</button></div></form>
  </div>
</div>
@endsection
