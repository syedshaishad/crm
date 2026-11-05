@extends('layouts.app')
@section('title','Tax')
@section('content')
@php
  $gstRate = (string) old('tax_default_gst_rate', $s->get('tax.default_gst_rate',18));
  $gstOn = $s->get('tax.enable_gst','1') === '1';
  $tdsOn = $s->get('tax.tds_enabled','0') === '1';
  $tdsSections = old('tax_tds_sections', $s->get('tax.tds_sections', "194C:2\n194J:10\n194I:2\n194H:2\n194A:10"));
  $sectionCount = count(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $tdsSections))));
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="M4 10h16M8 3v4M16 3v4"/></svg>
    </span>
    <div>
      <h1>Tax</h1>
      <div class="crumb">GST and TDS behaviour for new documents</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Settings
    </a>
    <button class="btn sm" type="submit" form="tax-form">Save tax</button>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Default GST rate</span>
    <b class="ix-metric-val">{{ $gstRate }}%</b>
    <span class="ix-metric-sub">applied to new invoices</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">GST on invoices</span>
    <b class="ix-metric-val">{{ $gstOn ? 'Enabled' : 'Zero-rated' }}</b>
    <span class="ix-metric-sub">{{ $gstOn ? 'tax is calculated per line' : 'all new invoices are zero-rated' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">TDS tracking</span>
    <b class="ix-metric-val">{{ $tdsOn ? 'On' : 'Off' }}</b>
    <span class="ix-metric-sub">{{ $tdsOn ? 'deductions recorded on payments' : 'enable to record deductions' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">TDS sections</span>
    <b class="ix-metric-val">{{ $sectionCount }}</b>
    <span class="ix-metric-sub">codes available for deduction</span>
  </div>
</div>

<form id="tax-form" method="POST" action="{{ route('settings.tax.store') }}">@csrf
<div class="st-grid">
  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>GST</h3><span class="hint">Applies to new invoices. Existing invoices keep their own rates.</span></div>
      <span class="ix-tag">Indirect tax</span>
    </div>

    <div class="fsec">Default GST rate</div>
    <div class="st-rate-grid">
      @foreach([0,5,12,18,28] as $r)
        <label class="st-rate">
          <input type="radio" name="tax_default_gst_rate" value="{{ $r }}" {{ $gstRate===(string)$r?'checked':'' }}>
          <span>{{ $r }}%</span>
        </label>
      @endforeach
    </div>

    <div class="st-checks is-one">
      <label class="st-check">
        <input type="checkbox" name="tax_enable_gst" value="1" {{ $gstOn?'checked':'' }}>
        <span><b>Enable GST on invoices</b><br>When off, new invoices are treated as zero-rated. Existing documents are unchanged.</span>
      </label>
    </div>
    <p class="fhint st-note">The chosen rate pre-fills each new invoice line. Staff can still override it per document.</p>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>TDS — tax deducted at source</h3><span class="hint">Record client deductions on payments and vendor deductions on expenses.</span></div>
      <span class="ix-state {{ $tdsOn ? 'is-green' : 'is-grey' }}"><i></i>{{ $tdsOn ? 'Tracking' : 'Off' }}</span>
    </div>

    <div class="st-checks is-one">
      <label class="st-check">
        <input type="checkbox" name="tax_tds_enabled" value="1" {{ $tdsOn?'checked':'' }}>
        <span><b>Enable TDS</b><br>Adds deduction fields to payments and expenses.</span>
      </label>
    </div>

    <label class="f" for="tax-tds-sections">Sections &amp; default rates <span class="hint">— one per line as CODE:RATE</span></label>
    <textarea class="in" id="tax-tds-sections" name="tax_tds_sections" rows="6" placeholder="194C:2&#10;194J:10">{{ $tdsSections }}</textarea>
    <div class="fhint">Each line becomes a selectable section with a suggested rate. The rate can be edited on the payment or expense.</div>
  </div>
</div>

<div class="card ix-card st-card">
  <div class="st-actions">
    <div class="st-actions-txt">
      <strong>Save tax settings</strong>
      <span>Changes apply to documents created after saving.</span>
    </div>
    <div class="st-actions-btns">
      <a class="btn sec" href="{{ route('settings.index') }}">Cancel</a>
      <button class="btn" type="submit">Save tax settings</button>
    </div>
  </div>
</div>
</form>
@endsection
