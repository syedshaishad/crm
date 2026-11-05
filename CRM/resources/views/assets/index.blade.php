@extends('layouts.app')
@section('title','Assets')
@section('content')
@php
  $count = $assets->count();
  $depreciation = max(0, (float) $total['cost'] - (float) $total['book']);
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
    </span>
    <div>
      <h1>Fixed assets</h1>
      <div class="crumb">{{ $count }} {{ \Illuminate\Support\Str::plural('asset', $count) }} · {{ money($total['cost']) }} original cost · {{ money($total['book']) }} book value</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <span class="hint">straight-line depreciation</span>
  </div>
</div>

<div data-ajax-region="assets-list">
  <div class="ix-metrics">
    <div class="ix-metric"><span class="ix-metric-lbl">Assets</span><b class="ix-metric-val">{{ $count }}</b><span class="ix-metric-sub">{{ $assets->whereNotNull('assigned_to')->count() }} in staff custody</span></div>
    <div class="ix-metric"><span class="ix-metric-lbl">Original cost</span><b class="ix-metric-val is-money">{{ money($total['cost']) }}</b><span class="ix-metric-sub">at purchase</span></div>
    <div class="ix-metric"><span class="ix-metric-lbl">Book value today</span><b class="ix-metric-val is-money">{{ money($total['book']) }}</b><span class="ix-metric-sub">after depreciation</span></div>
    <div class="ix-metric"><span class="ix-metric-lbl">Depreciation</span><b class="ix-metric-val is-money">{{ money($depreciation) }}</b><span class="ix-metric-sub">written down so far</span></div>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Asset register</h3><span class="hint">{{ $count }} {{ \Illuminate\Support\Str::plural('asset', $count) }} · assign custody from the row</span></div>
    </div>

    <form method="POST" action="{{ route('assets.store') }}" data-ajax-add data-region="assets-list" class="ix-quick as-quick noprint">@csrf
      <span class="ix-quick-lbl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>Register asset</span>
      <label class="as-f is-name"><span>Name</span><input class="in" name="name" required maxlength="160" placeholder="e.g. Office laptop"></label>
      <label class="as-f"><span>Bought on</span><input class="in" type="date" name="bought_on" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required></label>
      <label class="as-f"><span>Cost (₹)</span><input class="in" type="number" step="0.01" min="0" name="cost" required placeholder="0.00"></label>
      <label class="as-f"><span>Life (yrs)</span><input class="in" type="number" min="1" max="30" name="life_years" value="5" required></label>
      <label class="as-f is-loc"><span>Location</span><input class="in" name="location" maxlength="160" placeholder="e.g. Front office"></label>
      <label class="as-f is-notes"><span>Notes</span><input class="in" name="notes" placeholder="Serial no, bill ref…"></label>
      <button class="btn sm as-save">Register</button>
    </form>

    <div class="tbl-scroll roomy ix-table-scroll">
      <table class="tbl ix-table as-table">
        <thead>
          <tr><th class="ix-c-main">Asset</th><th class="ix-c-bought">Bought</th><th class="ix-c-life">Life</th><th class="ix-c-custody">Custody</th><th class="ix-c-cost">Cost</th><th class="ix-c-book">Book value</th><th class="ix-c-actions" aria-label="Actions"></th></tr>
        </thead>
        <tbody>
        @forelse($assets as $a)
          <tr>
            <td class="ix-c-main">
              <div class="ix-main">
                <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg></span>
                <div class="ix-main-txt">
                  <span class="ix-name">{{ $a->name }}</span>
                  <span class="ix-sub">{{ collect([$a->location, $a->notes])->filter()->implode(' · ') ?: 'no location recorded' }}</span>
                </div>
              </div>
            </td>
            <td class="ix-c-bought"><span class="ix-sub">{{ $a->bought_on ? d($a->bought_on, 'j M Y') : '—' }}</span></td>
            <td class="ix-c-life"><span class="ix-client">{{ $a->life_years }} yr</span></td>
            <td class="ix-c-custody">
              <form method="POST" action="{{ route('assets.assign', $a) }}" class="as-custody-form">@csrf<select class="in as-custody" name="assigned_to" onchange="this.form.submit()" title="Custody"><option value="">Office</option>@foreach($users as $u)<option value="{{ $u->id }}" {{ (string) ($a->assigned_to ?? '') === (string) $u->id ? 'selected' : '' }}>→ {{ $u->name }}</option>@endforeach</select></form>
            </td>
            <td class="ix-c-cost"><span class="ix-money">{{ money($a->cost) }}</span></td>
            <td class="ix-c-book"><span class="ix-money"><b>{{ money($a->bookValue()) }}</b></span></td>
            <td class="ix-c-actions">
              <div class="ix-actions noprint">
                <form method="POST" action="{{ route('assets.destroy', $a) }}" data-confirm="Move {{ $a->name }} to Trash?" data-refresh='[data-ajax-region="assets-list"]'>@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete asset"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="7"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg></span><strong>No assets registered yet</strong><p>Use the register form above to add laptops, printers and furniture.</p></div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
