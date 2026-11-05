@extends('layouts.app')
@section('title','General')
@section('content')
@php
  $currency = old('app_currency', $s->get('app.currency','INR'));
  $timezone = old('app_timezone', $tz);
  $perPage = old('app_per_page', $s->get('app.per_page',20));
  $digestOn = old('reminders_enabled', $s->get('reminders.enabled','0')) === '1';
  $leaveSick = old('leave_sick', $s->get('leave.sick',6));
  $leaveCasual = old('leave_casual', $s->get('leave.casual',6));
  $leaveEarned = old('leave_earned', $s->get('leave.earned',12));
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
    </span>
    <div>
      <h1>General</h1>
      <div class="crumb">Locale and listing defaults for this installation</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Settings
    </a>
    <button class="btn sm" type="submit" form="general-form">Save settings</button>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Currency</span>
    <b class="ix-metric-val">{{ $currency }}</b>
    <span class="ix-metric-sub">used across invoices and reports</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Timezone</span>
    <b class="ix-metric-val st-metric-code">{{ $timezone }}</b>
    <span class="ix-metric-sub">dates and reminders follow this</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Rows per page</span>
    <b class="ix-metric-val">{{ $perPage }}</b>
    <span class="ix-metric-sub">shared listing default</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Morning digest</span>
    <b class="ix-metric-val">{{ $digestOn ? 'On' : 'Off' }}</b>
    <span class="ix-metric-sub">{{ $digestOn ? 'sends daily to your recipients' : 'enable below to receive it' }}</span>
  </div>
</div>

<form id="general-form" method="POST" action="{{ route('settings.general.store') }}">@csrf

<div class="st-grid">
  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Locale &amp; display</h3><span class="hint">How dates, money and lists appear.</span></div>
      <span class="ix-tag">Locale</span>
    </div>
    <div class="form-grid">
      <div>
        <label class="f" for="gen-currency">Currency code <span class="req">*</span></label>
        <input class="in" id="gen-currency" name="app_currency" value="{{ $currency }}" required maxlength="10" placeholder="INR">
      </div>
      <div>
        <label class="f" for="gen-date">Date display format <span class="req">*</span></label>
        <select class="in" id="gen-date" name="app_date_format">
          @foreach(['Y-m-d' => date('Y-m-d'), 'd-m-Y' => date('d-m-Y'), 'd/m/Y' => date('d/m/Y'), 'm/d/Y' => date('m/d/Y'), 'j M Y' => date('j M Y')] as $v=>$ex)
            <option value="{{ $v }}" {{ old('app_date_format',$s->get('app.date_format','Y-m-d'))===$v?'selected':'' }}>{{ $v }} ({{ $ex }})</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="f" for="gen-rows">Rows per page <span class="req">*</span></label>
        <input class="in" id="gen-rows" type="number" min="5" max="100" name="app_per_page" value="{{ $perPage }}" required>
      </div>
      <div>
        <label class="f" for="gen-timezone">Timezone <span class="req">*</span></label>
        <input class="in" id="gen-timezone" name="app_timezone" list="tz-list" value="{{ $timezone }}" required placeholder="Type to search, e.g. Kolkata">
        <datalist id="tz-list">@foreach(DateTimeZone::listIdentifiers() as $zone)<option value="{{ $zone }}">@endforeach</datalist>
        <div class="fhint">Full IANA list — type a city to filter.</div>
      </div>
    </div>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Uploads &amp; limits</h3><span class="hint">File caps for receipts and documents.</span></div>
      <span class="ix-tag">Storage</span>
    </div>
    <div class="form-grid">
      <div>
        <label class="f" for="gen-receipt">Max expense receipt (MB)</label>
        <input class="in" id="gen-receipt" type="number" min="1" max="100" name="uploads_receipt_max_mb" value="{{ old('uploads_receipt_max_mb', $s->get('uploads.receipt_max_mb',10)) }}">
      </div>
      <div>
        <label class="f" for="gen-document">Max document upload (MB)</label>
        <input class="in" id="gen-document" type="number" min="1" max="100" name="uploads_document_max_mb" value="{{ old('uploads_document_max_mb', $s->get('uploads.document_max_mb',20)) }}">
      </div>
    </div>
    <div class="form-grid">
      <div>
        <label class="f" for="gen-leads-view">Leads start view</label>
        <select class="in" id="gen-leads-view" name="leads_default_view">
          <option value="list" {{ old('leads_default_view',$s->get('leads.default_view','list'))==='list'?'selected':'' }}>List</option>
          <option value="board" {{ old('leads_default_view',$s->get('leads.default_view','list'))==='board'?'selected':'' }}>Board</option>
        </select>
      </div>
      <div>
        <label class="f" for="gen-tasks-view">Tasks start view</label>
        <select class="in" id="gen-tasks-view" name="tasks_default_view">
          <option value="list" {{ old('tasks_default_view',$s->get('tasks.default_view','list'))==='list'?'selected':'' }}>List</option>
          <option value="calendar" {{ old('tasks_default_view',$s->get('tasks.default_view','list'))==='calendar'?'selected':'' }}>Calendar</option>
        </select>
      </div>
      <div>
        <label class="f" for="gen-stale">Lead goes stale after (days)</label>
        <input class="in" id="gen-stale" type="number" min="1" max="90" name="leads_stale_days" value="{{ old('leads_stale_days', $s->get('leads.stale_days',7)) }}">
        <div class="fhint">Quiet open leads get flagged and listed in the digest.</div>
      </div>
      <div>
        <label class="f" for="gen-rate">Default hourly rate (₹)</label>
        <input class="in" id="gen-rate" type="number" step="0.01" min="0" name="projects_hourly_rate" value="{{ old('projects_hourly_rate', $s->get('projects.hourly_rate',0)) }}">
        <div class="fhint">Used for labour costing unless a task sets its own.</div>
      </div>
      <div>
        <label class="f" for="gen-capacity">Weekly capacity (hrs)</label>
        <input class="in" id="gen-capacity" type="number" min="1" max="168" name="team_weekly_hours" value="{{ old('team_weekly_hours', $s->get('team.weekly_hours',48)) }}">
        <div class="fhint">Full-time week for utilisation. Per-person overrides live on the user.</div>
      </div>
      <div>
        <label class="f" for="gen-target">Monthly collection target (₹)</label>
        <input class="in" id="gen-target" type="number" step="0.01" min="0" name="company_monthly_target" value="{{ old('company_monthly_target', $s->get('company.monthly_target',0)) }}">
        <div class="fhint">Powers the dashboard target bar and forecast. 0 hides it.</div>
      </div>
    </div>
  </div>
</div>

<div class="st-grid">
  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Yearly leave quotas</h3><span class="hint">Default allowance per person, per year.</span></div>
      <span class="ix-tag">People</span>
    </div>
    <div class="st-rate-grid is-three">
      <label class="st-rate-field">
        <span class="f">Sick</span>
        <input class="in" type="number" min="0" max="365" name="leave_sick" value="{{ $leaveSick }}">
      </label>
      <label class="st-rate-field">
        <span class="f">Casual</span>
        <input class="in" type="number" min="0" max="365" name="leave_casual" value="{{ $leaveCasual }}">
      </label>
      <label class="st-rate-field">
        <span class="f">Earned</span>
        <input class="in" type="number" min="0" max="365" name="leave_earned" value="{{ $leaveEarned }}">
      </label>
    </div>
    <p class="fhint st-note">Quotas reset at the start of each calendar year. Adjust a single person from their user profile.</p>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Maintenance</h3><span class="hint">Housekeeping for the nightly backup run.</span></div>
      <span class="ix-tag">System</span>
    </div>
    <div>
      <label class="f" for="gen-backups">Auto-backups to keep</label>
      <input class="in" id="gen-backups" type="number" min="1" max="3" name="backups_retain_count" value="{{ old('backups_retain_count', $s->get('backups.retain_count',3)) }}">
      <div class="fhint">Keeps this many latest zips (maximum 3); older backups are deleted automatically.</div>
    </div>
  </div>
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Morning digest email</h3><span class="hint">One email every morning with follow-ups, due tasks, overdue invoices and expiring proposals. Sent by the daily scheduler.</span></div>
    <span class="ix-state {{ $digestOn ? 'is-green' : 'is-grey' }}"><i></i>{{ $digestOn ? 'Sending' : 'Paused' }}</span>
  </div>
  <div class="st-checks">
    <label class="st-check">
      <input type="checkbox" name="reminders_enabled" value="1" {{ old('reminders_enabled',$s->get('reminders.enabled','0'))==='1'?'checked':'' }}>
      <span><b>Enable daily digest email</b><br>Send the morning roundup to your recipients.</span>
    </label>
    <label class="st-check">
      <input type="checkbox" name="reminders_clients" value="1" {{ old('reminders_clients',$s->get('reminders.clients','0'))==='1'?'checked':'' }}>
      <span><b>Also nudge clients</b><br>Overdue invoice reminders, at most once every 3 days per client.</span>
    </label>
  </div>
  <label class="f" for="gen-recipients">Send to (one email per line)</label>
  <textarea class="in" id="gen-recipients" name="reminders_recipients" rows="3" placeholder="owner@yourcompany.com">{{ old('reminders_recipients', $s->get('reminders.recipients','')) }}</textarea>
</div>

<div class="card ix-card st-card">
  <div class="st-actions">
    <div class="st-actions-txt">
      <strong>Save general settings</strong>
      <span>Currency, locale, listing defaults and digest delivery.</span>
    </div>
    <div class="st-actions-btns">
      <a class="btn sec" href="{{ route('settings.index') }}">Cancel</a>
      <button class="btn" type="submit">Save general settings</button>
    </div>
  </div>
</div>
</form>
@endsection
