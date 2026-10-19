@extends('layouts.app')
@section('title','General')
@section('content')
<div class="card" style="max-width:680px"><h3>General</h3>
<p class="fhint">Locale and listing defaults for this installation.</p>
<form method="POST" action="{{ route('settings.general.store') }}">@csrf
<div class="form-grid">
<div><label class="f">Currency code <span class="req">*</span></label><input class="in" name="app_currency" value="{{ old('app_currency', $s->get('app.currency','INR')) }}" required maxlength="10" placeholder="INR"></div>
<div><label class="f">Date display format <span class="req">*</span></label><select class="in" name="app_date_format">@foreach(['Y-m-d' => date('Y-m-d'), 'd-m-Y' => date('d-m-Y'), 'd/m/Y' => date('d/m/Y'), 'm/d/Y' => date('m/d/Y'), 'j M Y' => date('j M Y')] as $v=>$ex)<option value="{{ $v }}" {{ old('app_date_format',$s->get('app.date_format','Y-m-d'))===$v?'selected':'' }}>{{ $v }} ({{ $ex }})</option>@endforeach</select></div>
</div>
<div class="form-grid">
<div><label class="f">Rows per page <span class="req">*</span></label><input class="in" type="number" min="5" max="100" name="app_per_page" value="{{ old('app_per_page', $s->get('app.per_page',20)) }}" required></div>
<div><label class="f">Timezone <span class="req">*</span></label><input class="in" name="app_timezone" list="tz-list" value="{{ old('app_timezone', $tz) }}" required placeholder="Type to search, e.g. Kolkata"><datalist id="tz-list">@foreach(DateTimeZone::listIdentifiers() as $zone)<option value="{{ $zone }}">@endforeach</datalist><div class="fhint">Full IANA list — type a city to filter.</div></div>
</div>
<div class="form-grid">
<div><label class="f">Max expense receipt (MB)</label><input class="in" type="number" min="1" max="100" name="uploads_receipt_max_mb" value="{{ old('uploads_receipt_max_mb', $s->get('uploads.receipt_max_mb',10)) }}"></div>
<div><label class="f">Max document upload (MB)</label><input class="in" type="number" min="1" max="100" name="uploads_document_max_mb" value="{{ old('uploads_document_max_mb', $s->get('uploads.document_max_mb',20)) }}"></div>
</div>
<div class="form-grid">
<div><label class="f">Leads start view</label><select class="in" name="leads_default_view"><option value="list" {{ old('leads_default_view',$s->get('leads.default_view','list'))==='list'?'selected':'' }}>List</option><option value="board" {{ old('leads_default_view',$s->get('leads.default_view','list'))==='board'?'selected':'' }}>Board</option></select></div>
<div><label class="f">Tasks start view</label><select class="in" name="tasks_default_view"><option value="list" {{ old('tasks_default_view',$s->get('tasks.default_view','list'))==='list'?'selected':'' }}>List</option><option value="calendar" {{ old('tasks_default_view',$s->get('tasks.default_view','list'))==='calendar'?'selected':'' }}>Calendar</option></select></div>
<div><label class="f">Lead goes stale after (days)</label><input class="in" type="number" min="1" max="90" name="leads_stale_days" value="{{ old('leads_stale_days', $s->get('leads.stale_days',7)) }}"><div class="fhint">Quiet open leads get flagged + listed in the digest.</div></div>
<div><label class="f">Default hourly rate (₹)</label><input class="in" type="number" step="0.01" min="0" name="projects_hourly_rate" value="{{ old('projects_hourly_rate', $s->get('projects.hourly_rate',0)) }}"><div class="fhint">Used for labor costing unless a task sets its own.</div></div>
<div><label class="f">Weekly capacity (hrs)</label><input class="in" type="number" min="1" max="168" name="team_weekly_hours" value="{{ old('team_weekly_hours', $s->get('team.weekly_hours',48)) }}"><div class="fhint">Full-time week for utilization. Per-person overrides live on the user.</div></div>
<div><label class="f">Monthly collection target (₹)</label><input class="in" type="number" step="0.01" min="0" name="company_monthly_target" value="{{ old('company_monthly_target', $s->get('company.monthly_target',0)) }}"><div class="fhint">Powers the dashboard target bar + forecast. 0 hides it.</div></div>
</div>
<div class="fsec">Yearly leave quotas</div>
<div class="form-grid">
<div><label class="f">Sick</label><input class="in" type="number" min="0" max="365" name="leave_sick" value="{{ old('leave_sick', $s->get('leave.sick',6)) }}"></div>
<div><label class="f">Casual</label><input class="in" type="number" min="0" max="365" name="leave_casual" value="{{ old('leave_casual', $s->get('leave.casual',6)) }}"></div>
<div><label class="f">Earned</label><input class="in" type="number" min="0" max="365" name="leave_earned" value="{{ old('leave_earned', $s->get('leave.earned',12)) }}"></div>
</div>
<div class="fsec">Morning digest email</div>
<p class="fhint">One email every morning with follow-ups, due tasks, overdue invoices and expiring proposals. Sent by the daily scheduler.</p>
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:14px"><input type="checkbox" name="reminders_enabled" value="1" {{ old('reminders_enabled',$s->get('reminders.enabled','0'))==='1'?'checked':'' }} style="width:auto;margin:0"> Enable daily digest email</label>
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:14px"><input type="checkbox" name="reminders_clients" value="1" {{ old('reminders_clients',$s->get('reminders.clients','0'))==='1'?'checked':'' }} style="width:auto;margin:0"> Also nudge clients with overdue invoices (each client emailed at most once every 3 days)</label>
<label class="f">Send to (one email per line)</label><textarea class="in" name="reminders_recipients" rows="3" placeholder="owner@yourcompany.com">{{ old('reminders_recipients', $s->get('reminders.recipients','')) }}</textarea>
<div><label class="f">Auto-backups to keep</label><input class="in" type="number" min="1" max="30" name="backups_retain_count" value="{{ old('backups_retain_count', $s->get('backups.retain_count',7)) }}" style="max-width:200px"><div class="fhint">Nightly 2 AM backup keeps this many zips.</div></div>
<div class="form-actions"><button class="btn" type="submit">Save general settings</button><a class="btn sec" href="{{ route('settings.index') }}">Cancel</a></div></form></div>
@endsection
