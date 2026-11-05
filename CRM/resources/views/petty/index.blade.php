@extends('layouts.app')
@section('title','Petty cash')
@section('content')
@php
  $net = (float) $in - (float) $out;
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="12" rx="2"/><circle cx="12" cy="13" r="2.4"/><path d="M6.5 10V7.5A1.5 1.5 0 0 1 8 6h8a1.5 1.5 0 0 1 1.5 1.5V10"/></svg>
    </span>
    <div>
      <h1>Petty cash</h1>
      <div class="crumb">{{ money($balance) }} in hand · {{ money($in) }} added · {{ money($out) }} spent in {{ d($from, 'M Y') }}</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('petty.index', ['month' => $prev]) }}">← {{ d($prev.'-01', 'M Y') }}</a>
    @if($next)<a class="btn sm sec" href="{{ route('petty.index', ['month' => $next]) }}">{{ d($next.'-01', 'M Y') }} →</a>@endif
  </div>
</div>

<div data-ajax-region="petty-list">
  <div class="ix-metrics">
    <div class="ix-metric"><span class="ix-metric-lbl">In hand</span><b class="ix-metric-val is-money">{{ money($balance) }}</b><span class="ix-metric-sub">right now</span></div>
    <div class="ix-metric"><span class="ix-metric-lbl">Added · {{ d($from, 'M Y') }}</span><b class="ix-metric-val is-money">{{ money($in) }}</b><span class="ix-metric-sub">cash in</span></div>
    <div class="ix-metric"><span class="ix-metric-lbl">Spent · {{ d($from, 'M Y') }}</span><b class="ix-metric-val is-money">{{ money($out) }}</b><span class="ix-metric-sub">cash out</span></div>
    <div class="ix-metric"><span class="ix-metric-lbl">Net movement</span><b class="ix-metric-val is-money">{{ money($net) }}</b><span class="ix-metric-sub">this month</span></div>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Cash book · {{ d($from, 'M Y') }}</h3><span class="hint">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('entry', $rows->count()) }} · opening {{ money($opening) }}</span></div>
    </div>

    <form method="POST" action="{{ route('petty.store') }}" data-ajax-add data-region="petty-list" class="ix-quick pc-quick noprint">@csrf
      <span class="ix-quick-lbl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>Quick entry</span>
      <label class="pc-f"><span>Date</span><input class="in" type="date" name="entry_date" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required></label>
      <label class="pc-f"><span>Type</span><select class="in" name="kind"><option value="out">Spend</option><option value="in">Add cash</option></select></label>
      <label class="pc-f is-amount"><span>Amount (₹)</span><input class="in" type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"></label>
      <label class="pc-f is-wide"><span>Note</span><input class="in" name="note" maxlength="255" placeholder="e.g. Auto fare, office keys"></label>
      <button class="btn sm pc-save">Add entry</button>
    </form>

    <div class="tbl-scroll roomy ix-table-scroll">
      <table class="tbl ix-table pc-table">
        <thead>
          <tr><th class="ix-c-main">Entry</th><th class="ix-c-type">Type</th><th class="ix-c-in">In</th><th class="ix-c-out">Out</th><th class="ix-c-balance">Balance</th><th class="ix-c-actions" aria-label="Actions"></th></tr>
        </thead>
        <tbody>
          <tr class="pc-opening">
            <td class="ix-c-main">
              <div class="ix-main-txt">
                <span class="ix-client">Opening balance</span>
                <span class="ix-sub">{{ d($from, 'j M Y') }} · carried forward</span>
              </div>
            </td>
            <td class="ix-c-type"><span class="ix-sub">—</span></td>
            <td class="ix-c-in"><span class="ix-sub">—</span></td>
            <td class="ix-c-out"><span class="ix-sub">—</span></td>
            <td class="ix-c-balance"><span class="ix-money">{{ money($opening) }}</span></td>
            <td class="ix-c-actions"></td>
          </tr>
          @forelse($rows as $e)
            <tr>
              <td class="ix-c-main">
                <div class="ix-main-txt">
                  <span class="ix-client">{{ $e->note ?: ($e->kind === 'in' ? 'Cash added' : 'Cash spent') }}</span>
                  <span class="ix-sub">{{ d($e->entry_date, 'j M Y') }} · {{ $e->creator->name ?? 'unknown' }}</span>
                </div>
              </td>
              <td class="ix-c-type"><span class="ix-tag">{{ $e->kind === 'in' ? 'In' : 'Out' }}</span></td>
              <td class="ix-c-in"><span class="ix-money">{{ $e->kind === 'in' ? money($e->amount) : '—' }}</span></td>
              <td class="ix-c-out"><span class="ix-money">{{ $e->kind === 'out' ? money($e->amount) : '—' }}</span></td>
              <td class="ix-c-balance"><span class="ix-money"><b>{{ money($e->running) }}</b></span></td>
              <td class="ix-c-actions">
                <div class="ix-actions noprint">
                  <form method="POST" action="{{ route('petty.destroy', $e) }}" data-confirm="Delete this {{ $e->kind === 'in' ? 'cash addition' : 'spend' }} of {{ money($e->amount) }}?" data-refresh='[data-ajax-region="petty-list"]'>@csrf @method('DELETE')<button class="ix-act is-danger" title="Delete entry"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="12" rx="2"/><circle cx="12" cy="13" r="2.4"/><path d="M6.5 10V7.5A1.5 1.5 0 0 1 8 6h8a1.5 1.5 0 0 1 1.5 1.5V10"/></svg></span><strong>No entries in {{ d($from, 'M Y') }}</strong><p>Add cash with the quick entry above to open the book for this month.</p></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
