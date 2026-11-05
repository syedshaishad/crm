@extends('layouts.app')
@section('title','Leads')
@section('content')
@php
  $stageLabels = \Modules\Leads\Lead::stages();
  $stageOrder = array_keys($stageLabels);
  $stageColors = ['new'=>'var(--dc-muted)','contacted'=>'var(--dc-blue)','qualified'=>'var(--dc-amber)','proposal'=>'#8b5cf6','won'=>'var(--dc-green)','lost'=>'var(--dc-red)'];
  $fTotal = max(1, $funnel->sum());
  $sourceLabels = ['website'=>'Website','indiamart'=>'IndiaMART','justdial'=>'JustDial','google'=>'Google','referral'=>'Referral','walkin'=>'Walk-in','instagram'=>'Instagram','facebook'=>'Facebook','whatsapp'=>'WhatsApp','coldcall'=>'Cold call','other'=>'Other'];
  $srcLabel = function ($s) use ($sourceLabels) { return $sourceLabels[strtolower((string) $s)] ?? ucwords(str_replace(['_','-'], ' ', (string) $s)); };
  $isClosed = function ($l) { return in_array($l->stage, ['won','lost'], true); };
  $wa = function ($phone, $name) {
    $d = preg_replace('/\D+/', '', (string) $phone);
    if (strlen($d) === 10) $d = '91'.$d;
    if ($d === '') return '';
    $msg = 'Hello '.($name ?: 'there').', this is '.(setting('brand.company_name', $brand['company_name'] ?? 'us')).'. Following up on your enquiry — is this a good time to talk?';
    return 'https://wa.me/'.$d.'?text='.urlencode($msg);
  };
  $pipelineLabel = setting('app.currency', 'INR').' '.number_format((float) $stats['pipeline'], 0);
  $hasFilters = ($q ?? '') !== '' || ($stage ?? '') !== '' || ($source ?? '') !== '' || ($stale ?? false);
  $isBoard = ($view ?? 'list') === 'board';
  $clearUrl = route('leads.index', $isBoard ? ['view' => 'board'] : []);
  $staleUrl = route('leads.index', array_merge(request()->except('page'), ['stale' => ($stale ?? false) ? null : 1]));
@endphp

<div class="page-head ld-head">
  <div class="ld-head-main">
    <span class="ld-head-ic" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h18l-7 8v5.2L10 20v-7z"/></svg>
    </span>
    <div>
      <h1>Leads</h1>
      <div class="crumb">{{ $stats['open'] }} open · {{ $stats['followups'] }} follow-ups due · {{ $stats['stale'] }} need attention</div>
    </div>
  </div>
  <div class="ld-head-actions noprint">
    <button class="btn sm sec" data-modal-open="import-modal">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m-4-4 4 4 4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
      Import
    </button>
    <a class="btn sm" href="{{ route('leads.create') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
      New lead
    </a>
  </div>
</div>

<div class="ld-metrics">
  <div class="ld-metric">
    <span class="ld-metric-lbl">Open leads</span>
    <b class="ld-metric-val">{{ $stats['open'] }}</b>
    <span class="ld-metric-sub">Active pipeline</span>
  </div>
  <div class="ld-metric">
    <span class="ld-metric-lbl">Won this month</span>
    <b class="ld-metric-val">{{ $stats['won_month'] }}</b>
    <span class="ld-metric-sub">Closed-won leads</span>
  </div>
  <div class="ld-metric">
    <span class="ld-metric-lbl">Pipeline value</span>
    <b class="ld-metric-val is-money">{{ $pipelineLabel }}</b>
    <span class="ld-metric-sub">Across open leads</span>
  </div>
  <div class="ld-metric">
    <span class="ld-metric-lbl">Follow-ups due</span>
    <b class="ld-metric-val">{{ $stats['followups'] }}</b>
    <span class="ld-metric-sub">Overdue + next 7 days</span>
  </div>
  <a class="ld-metric ld-metric-link {{ ($stale ?? false) ? 'is-active' : '' }}" href="{{ $staleUrl }}">
    <span class="ld-metric-lbl">Gone stale</span>
    <b class="ld-metric-val">{{ $stats['stale'] }}</b>
    <span class="ld-metric-sub">{{ ($stale ?? false) ? 'Showing stale only — click to clear' : 'Quiet '.\Modules\Leads\Lead::staleDays().'+ days — click to filter' }}</span>
  </a>
</div>

<div class="card ld-card">
  <div class="card-head ld-card-head">
    <div>
      <h3>{{ $isBoard ? 'Pipeline board' : 'Lead list' }}</h3>
      <span class="hint">{{ $isBoard ? 'Drag a card to change stage' : $leads->total().' leads' }}</span>
    </div>
    <div class="seg noprint" role="tablist" aria-label="Views">
      <a class="{{ ! $isBoard ? 'on' : '' }}" href="{{ route('leads.index', array_merge(request()->except(['page','view']), ['view' => 'list'])) }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>Table
      </a>
      <a class="{{ $isBoard ? 'on' : '' }}" href="{{ route('leads.index', array_merge(request()->except(['page','view']), ['view' => 'board'])) }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M15 4v16"/></svg>Kanban
      </a>
    </div>
  </div>

  <div class="funnel ld-funnel-data" aria-hidden="true">
    @foreach($stageOrder as $fs)
      <div data-stage="{{ $fs }}" style="width:{{ max(($funnel[$fs] ?? 0) > 0 ? 9 : 0, round(($funnel[$fs] ?? 0) / $fTotal * 100, 1)) }}%">{{ $funnel[$fs] ?? 0 }}</div>
    @endforeach
  </div>

  <div class="funnel-legend ld-stages" aria-label="Leads by stage">
    @foreach($stageLabels as $fs => $fl)
      @php $stageActive = ($stage ?? '') === $fs; @endphp
      <a data-stage="{{ $fs }}" class="ld-stage-chip {{ $stageActive ? 'on' : '' }}" href="{{ route('leads.index', array_merge(request()->except(['page','stage']), ['stage' => $fs])) }}" @if($stageActive) aria-current="page" @endif title="Filter by {{ $fl }}"><i style="background:{{ $stageColors[$fs] }}"></i>{{ $fl }} ({{ $funnel[$fs] ?? 0 }})</a>
    @endforeach
  </div>

  <form method="GET" data-ajax-filter data-region="leads-list" class="ld-filters noprint">
    @if($isBoard)<input type="hidden" name="view" value="board">@endif
    <label class="ld-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.4-3.4"/></svg>
      <input class="in" name="q" value="{{ $q ?? '' }}" placeholder="Search name, company, phone, email…" aria-label="Search leads" autocomplete="off">
    </label>
    <select class="in ld-filter" name="stage" aria-label="Filter by stage">
      <option value="">All stages</option>
      @foreach($stageLabels as $sv => $sl)<option value="{{ $sv }}" {{ ($stage ?? '') === $sv ? 'selected' : '' }}>{{ $sl }}</option>@endforeach
    </select>
    <select class="in ld-filter" name="source" aria-label="Filter by source">
      <option value="">All sources</option>
      @foreach($sources as $src)<option value="{{ $src }}" {{ ($source ?? '') === $src ? 'selected' : '' }}>{{ $srcLabel($src) }}</option>@endforeach
    </select>
    <button class="btn sec sm ld-filter-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h18l-7 8v5.2L10 20v-7z"/></svg>
      Filter
    </button>
    @if($hasFilters)<a class="btn sm sec ld-clear-btn" href="{{ $clearUrl }}">Clear</a>@endif
  </form>

  <form method="POST" action="{{ route('leads.store') }}" data-ajax-add data-region="leads-list" class="ld-quick noprint">
    @csrf
    <span class="ld-quick-lbl">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
      Quick add
    </span>
    <input class="in" name="name" required placeholder="Lead name" aria-label="New lead name" autocomplete="off">
    <input class="in" name="phone" placeholder="Phone (10-digit)" aria-label="New lead phone" autocomplete="off">
    <select class="in" name="source" aria-label="New lead source">
      @foreach($sources as $src)<option value="{{ $src }}">{{ $srcLabel($src) }}</option>@endforeach
    </select>
    <button class="btn sm">Add lead</button>
  </form>

  @if($isBoard)
    <div data-ajax-region="leads-list">
      <div class="ld-board">
        <div class="kanban">
          @foreach($stageOrder as $fi => $fs)
            @php
              $prevStage = $fi > 0 ? $stageOrder[$fi - 1] : null;
              $nextStage = $fi < count($stageOrder) - 1 ? $stageOrder[$fi + 1] : null;
            @endphp
            <div class="kan-col ld-kan-col" data-stage="{{ $fs }}" style="--ld-stage:{{ $stageColors[$fs] }}">
              <div class="kan-head">
                <span class="dot" style="background:{{ $stageColors[$fs] }}"></span>
                <b>{{ $stageLabels[$fs] }}</b>
                <span class="n">{{ count($board[$fs] ?? []) }}</span>
              </div>
              <div class="kan-list" data-drop="{{ $fs }}">
                @forelse($board[$fs] ?? [] as $l)
                  @php
                    $closed = $isClosed($l);
                    $overdue = $l->follow_up_on && $l->follow_up_on->isPast() && ! $closed;
                  @endphp
                  <div class="kan-card ld-kan-card" draggable="true" data-id="{{ $l->id }}">
                    <b><a href="{{ route('leads.edit', $l) }}">{{ $l->company ?: $l->name }}</a></b>
                    @if($l->company)<div class="ld-kan-sub">{{ $l->name }}</div>@endif
                    @if($l->value > 0)<div class="ld-kan-value-row"><b>{{ money($l->value) }}</b></div>@endif
                    <div class="ld-kan-meta">
                      @if($l->follow_up_on)<span class="ld-kan-due {{ $overdue ? 'is-over' : '' }}"><i></i>{{ d($l->follow_up_on) }}</span>@endif
                      @if(! $closed)<span class="ld-kan-score" title="Nurture score: stage, deal size and silence">★ {{ $l->score() }}</span>@endif
                      @if(! $closed && $l->isStale())<span class="ld-kan-stale">Stale</span>@endif
                      @if($l->converted_client_id)<span class="ld-kan-client">Client</span>@endif
                    </div>
                    <div class="kan-foot">
                      <form method="POST" action="{{ route('leads.move', $l) }}" class="kan-move">@csrf
                        <select name="stage" onchange="this.form.submit()" title="Move to stage" aria-label="Move {{ $l->name }} to stage">
                          @foreach($stageLabels as $sv => $sl)<option value="{{ $sv }}" {{ $l->stage === $sv ? 'selected' : '' }}>{{ $sl }}</option>@endforeach
                        </select>
                      </form>
                      <div class="ld-kan-foot-bottom">
                        <div class="kan-nav noprint">
                          @if($prevStage)<form method="POST" action="{{ route('leads.move', $l) }}">@csrf<input type="hidden" name="stage" value="{{ $prevStage }}"><button class="kan-arrow" title="Move to {{ $stageLabels[$prevStage] }}" aria-label="Move to {{ $stageLabels[$prevStage] }}">‹</button></form>@endif
                          @if($nextStage)<form method="POST" action="{{ route('leads.move', $l) }}">@csrf<input type="hidden" name="stage" value="{{ $nextStage }}"><button class="kan-arrow" title="Move to {{ $stageLabels[$nextStage] }}" aria-label="Move to {{ $stageLabels[$nextStage] }}">›</button></form>@endif
                        </div>
                        <span class="kan-links"><a href="{{ route('leads.edit', $l) }}">Edit</a>@if(! $closed) · <form method="POST" action="{{ route('leads.convert', $l) }}" data-confirm="Convert {{ $l->name }} to client?" data-confirm-tone="brand" data-confirm-title="Convert lead?" data-confirm-ok="Convert">@csrf<button class="link-btn" type="submit">Convert</button></form>@endif</span>
                      </div>
                    </div>
                  </div>
                @empty
                  <div class="kan-empty">Drop leads here</div>
                @endforelse
              </div>
            </div>
          @endforeach
        </div>
        <p class="hint ld-board-note noprint">Showing up to 500 filtered leads.</p>
      </div>
    </div>
  @else
    <div class="bulkbar noprint" data-bulk-bar data-bulk-url="{{ route('leads.bulk') }}" data-region="leads-list" hidden>
      <span class="sel"><b data-bulk-count>0</b> selected</span>
      <select data-bulk-value name="stage"><option value="">Set stage…</option>@foreach($stageLabels as $sv => $sl)<option value="{{ $sv }}">{{ $sl }}</option>@endforeach</select>
      <button class="btn sm sec" data-bulk-apply="stage">Apply</button>
      <span class="spacer"></span>
      <button class="btn sm danger" data-bulk-apply="delete">Delete</button>
    </div>
    <div data-ajax-region="leads-list">
      <div class="tbl-scroll roomy ld-table-scroll">
        <table class="tbl ld-table">
          <thead>
            <tr>
              <th class="ld-c-check"><input type="checkbox" data-bulk-all title="Select all" aria-label="Select all leads"></th>
              <th class="ld-c-lead">Lead</th>
              <th class="ld-c-contact">Contact</th>
              <th class="ld-c-source">Source</th>
              <th class="ld-c-due">Follow-up</th>
              <th class="ld-c-stage">Stage</th>
              <th class="ld-c-value" style="text-align:right">Value</th>
              <th class="ld-c-actions" aria-label="Actions"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($leads as $l)
              @php
                $closed = $isClosed($l);
                $overdue = $l->follow_up_on && $l->follow_up_on->isPast() && ! $closed;
                $initials = strtoupper(mb_substr(trim($l->company ?: $l->name), 0, 1));
              @endphp
              <tr>
                <td class="ld-c-check"><input type="checkbox" class="bulk-check" value="{{ $l->id }}" title="Select {{ $l->name }}" aria-label="Select {{ $l->name }}"></td>
                <td class="ld-c-lead">
                  <div class="ld-lead">
                    <span class="ld-avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
                    <div class="ld-lead-txt">
                      <a class="ld-lead-name" href="{{ route('leads.edit', $l) }}">{{ $l->company ?: $l->name }}</a>
                      @if($l->company)<span class="ld-sub">{{ $l->name }}</span>@endif
                      @if($l->converted_client_id)<a class="ld-client" href="{{ route('clients.show', $l->converted_client_id) }}">Client</a>@endif
                    </div>
                  </div>
                </td>
                <td class="ld-c-contact">
                  <div class="ld-contact">
                    @if($l->phone)<a class="ld-phone" href="tel:{{ preg_replace('/\D+/', '', $l->phone) }}">{{ $l->phone }}</a>@endif
                    @if($l->email)<a class="ld-email" href="mailto:{{ $l->email }}">{{ $l->email }}</a>@endif
                    @if($l->city || $l->state)<span class="ld-place">{{ trim(($l->city ?? '').(($l->city && $l->state) ? ', ' : '').($l->state ?? '')) }}</span>@endif
                    @if(! $l->phone && ! $l->email && ! $l->city && ! $l->state)<span class="ld-dash">—</span>@endif
                  </div>
                </td>
                <td class="ld-c-source"><span class="ld-source">{{ $l->source ? $srcLabel($l->source) : '—' }}</span></td>
                <td class="ld-c-due">
                  @if($l->follow_up_on)
                    <span class="ld-due {{ $overdue ? 'is-over' : '' }}"><i></i>{{ d($l->follow_up_on) }}</span>
                    @if($overdue)<span class="ld-due-note">Overdue</span>@endif
                  @else
                    <span class="ld-dash">—</span>
                  @endif
                </td>
                <td class="ld-c-stage">
                  <span class="ld-stage"><i style="background:{{ $stageColors[$l->stage] ?? 'var(--dc-muted)' }}"></i>{{ $stageLabels[$l->stage] ?? $l->stage }}</span>
                  @if(! $closed)
                    <span class="ld-stage-note">
                      <span title="Nurture score: stage, deal size and silence">★ {{ $l->score() }}</span>
                      @if($l->isStale())<span class="is-stale"> · Stale</span>@endif
                    </span>
                  @endif
                </td>
                <td class="ld-c-value" style="text-align:right">@if($l->value > 0)<b class="ld-money">{{ money($l->value) }}</b>@else<span class="ld-dash">—</span>@endif</td>
                <td class="ld-c-actions">
                  <div class="ld-actions">
                    @if($l->phone && ($w = $wa($l->phone, $l->name)))
                      <a class="ld-act" href="{{ $w }}" target="_blank" rel="noopener" title="WhatsApp {{ $l->name }}" aria-label="WhatsApp {{ $l->name }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 21l2-5.4A8.5 8.5 0 1 1 21 11.5z"/></svg>
                      </a>
                    @endif
                    @if(! $closed)
                      <form method="POST" action="{{ route('leads.bump', $l) }}" title="Push follow-up out 7 days">@csrf<button class="ld-act" aria-label="Push follow-up for {{ $l->name }} out 7 days">+7d</button></form>
                      <form method="POST" action="{{ route('leads.convert', $l) }}" data-confirm="Convert {{ $l->name }} to client?" data-confirm-tone="brand" data-confirm-title="Convert lead?" data-confirm-ok="Convert">@csrf<button class="ld-act" title="Convert to client" aria-label="Convert {{ $l->name }} to client"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></button></form>
                    @endif
                    <a class="ld-act" href="{{ route('leads.edit', $l) }}" title="Edit lead" aria-label="Edit {{ $l->name }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
                    <form method="POST" action="{{ route('leads.destroy', $l) }}" data-confirm="Delete lead {{ $l->name }}?">@csrf @method('DELETE')<button class="ld-act is-danger" title="Delete lead" aria-label="Delete {{ $l->name }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8">
                  <div class="ld-empty">
                    <span class="ld-empty-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h18l-7 8v5.2L10 20v-7z"/></svg></span>
                    <strong>No leads found</strong>
                    <p>{{ $hasFilters ? 'Try a different search or clear the filters.' : 'Add your first lead to start building the pipeline.' }}</p>
                    <div class="ld-empty-actions">
                      @if($hasFilters)<a class="btn sm sec" href="{{ $clearUrl }}">Clear filters</a>@endif
                      <a class="btn sm" href="{{ route('leads.create') }}">Add lead</a>
                    </div>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      {{ $leads->links() }}
    </div>
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
