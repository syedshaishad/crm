@extends('layouts.app')
@section('title','Day-end close')
@section('content')
@php
  $checklist = \Modules\Petty\DayClose::checklist();
  $checkTotal = count($checklist);
  $variance = $done ? $done->variance() : null;
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
    </span>
    <div>
      <h1>Day-end close</h1>
      <div class="crumb">{{ d($today, 'l, j F Y') }} · {{ $closes->count() }} {{ \Illuminate\Support\Str::plural('close', $closes->count()) }} on record · {{ $done ? 'drawer counted' : 'not closed yet' }}</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('petty.index') }}">Petty cash</a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric"><span class="ix-metric-lbl">Petty in hand</span><b class="ix-metric-val is-money">{{ money($expected) }}</b><span class="ix-metric-sub">expected count</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Cash receipts today</span><b class="ix-metric-val is-money">{{ money($cashToday) }}</b><span class="ix-metric-sub">cash payments in</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Open invoices</span><b class="ix-metric-val">{{ $openInvoices }}</b><span class="ix-metric-sub">with a balance</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Cheques to deposit</span><b class="ix-metric-val">{{ $dueCheques }}</b><span class="ix-metric-sub">date reached</span></div>
</div>

<div class="card ix-card dc-card">
  <div class="card-head ix-card-head">
    <div><h3>Close · {{ d($today, 'j F Y') }}</h3><span class="hint">Count the drawer and tick off the day.</span></div>
    @if($done)
      <span class="ix-state is-green"><i></i>Closed</span>
    @else
      <span class="ix-state is-amber"><i></i>Open</span>
    @endif
  </div>

  <form method="POST" action="{{ route('dayclose.store') }}" class="dc-form">@csrf
    <div class="dc-cash">
      <label class="f" for="dc-cash-counted">Cash counted (₹) <span class="req">*</span></label>
      <input class="in" id="dc-cash-counted" type="number" step="0.01" min="0" name="cash_counted" required value="{{ old('cash_counted', $done->cash_counted ?? '') }}" placeholder="Physical cash in the drawer">
      <div class="dc-cash-meta">
        <span>Expected <b>{{ money($expected) }}</b></span>
        @if($done && $variance !== null)
          <span class="dc-var {{ abs($variance) < 0.01 ? 'is-ok' : 'is-bad' }}">{{ abs($variance) < 0.01 ? 'balanced' : (($variance > 0 ? '+' : '−').money(abs($variance)).' variance') }}</span>
        @else
          <span>Variance is recorded on close</span>
        @endif
      </div>
    </div>

    <div class="fsec">Checklist</div>
    <div class="dc-checks">
      @foreach($checklist as $k => $l)
        <label class="dc-check">
          <input type="checkbox" name="checks[{{ $k }}]" value="1" {{ ($done->checks[$k] ?? false) ? 'checked' : '' }}>
          <span>{{ $l }}</span>
        </label>
      @endforeach
    </div>

    <div class="dc-notes">
      <label class="f" for="dc-notes">Notes</label>
      <textarea class="in" id="dc-notes" name="notes" rows="2" placeholder="Anything for tomorrow…">{{ old('notes', $done->notes ?? '') }}</textarea>
    </div>

    <div class="form-actions">
      <button class="btn" type="submit">{{ $done ? 'Update close' : 'Close the day' }}</button>
    </div>
  </form>
</div>

<div class="card ix-card dc-past">
  <div class="card-head ix-card-head">
    <div><h3>Past closes</h3><span class="hint">Last 30 closes, newest first</span></div>
  </div>
  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table dc-table">
      <thead>
        <tr><th class="ix-c-main">Day</th><th class="ix-c-checks">Checks</th><th class="ix-c-by">Closed by</th><th class="ix-c-counted">Counted</th><th class="ix-c-expected">Expected</th><th class="ix-c-variance">Variance</th></tr>
      </thead>
      <tbody>
      @forelse($closes as $c)
        @php
          $v = $c->variance();
          $ticked = collect($c->checks)->filter()->count();
          $pct = $checkTotal > 0 ? (int) round($ticked / $checkTotal * 100) : 0;
        @endphp
        <tr>
          <td class="ix-c-main">
            <div class="ix-main-txt">
              <span class="ix-client">{{ d($c->day, 'j M Y') }}</span>
              <span class="ix-sub">{{ d($c->day, 'l') }}</span>
            </div>
          </td>
          <td class="ix-c-checks">
            <div class="ix-progress-head"><b>{{ $ticked }}/{{ $checkTotal }}</b></div>
            <div class="ix-progress" style="--ix-fill:{{ $pct === 100 ? 'var(--dc-green)' : 'var(--dc-amber)' }}"><i style="width:{{ $pct }}%"></i></div>
          </td>
          <td class="ix-c-by"><span class="ix-client">{{ $c->closer->name ?? '—' }}</span></td>
          <td class="ix-c-counted"><span class="ix-money">{{ money($c->cash_counted) }}</span></td>
          <td class="ix-c-expected"><span class="ix-money">{{ money($c->expected_petty) }}</span></td>
          <td class="ix-c-variance"><span class="dc-var {{ abs($v) < 0.01 ? 'is-ok' : 'is-bad' }}">{{ abs($v) < 0.01 ? 'Balanced' : (($v > 0 ? '+' : '−').money(abs($v))) }}</span></td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg></span><strong>No closes yet</strong><p>Close the day above and it will appear here with its variance and checklist.</p></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
