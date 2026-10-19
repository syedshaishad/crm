@extends('layouts.app')
@section('title','Scheduled reports')
@section('content')
<div class="card">
<div class="card-head"><h3>Auto reports</h3><span class="hint">weekly fires Monday · monthly on the 1st</span></div>
<p class="fhint">Sales, receivables or P&L summaries emailed (or WhatsApped via your approved template) on schedule. Pair with the morning digest for full coverage.</p>
<form method="POST" action="{{ route('reports.schedules.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
<div><label class="f">Name</label><input class="in" name="name" required maxlength="120" style="margin:0" placeholder="e.g. Monday sales pulse"></div>
<div><label class="f">Report</label><select class="in" name="type" style="margin:0">@foreach(\Modules\Reports\ReportSchedule::types() as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
<div><label class="f">Every</label><select class="in" name="frequency" style="margin:0">@foreach(\Modules\Reports\ReportSchedule::frequencies() as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
<div><label class="f">Channel</label><select class="in" name="channel" style="margin:0"><option value="email">Email</option><option value="whatsapp">WhatsApp</option></select></div>
<div style="flex:2;min-width:200px"><label class="f">To <span class="hint">— emails, or mobile numbers for WhatsApp</span></label><input class="in" name="recipients" required style="margin:0" placeholder="owner@company.com, +9198…"></div>
<div><button class="btn sm">Schedule</button></div></form>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Name</th><th>Report</th><th>Every</th><th>Channel</th><th>To</th><th>Last run</th><th>Status</th><th></th></tr>
@forelse($rows as $r)<tr>
<td><b>{{ $r->name }}</b></td>
<td>{{ \Modules\Reports\ReportSchedule::types()[$r->type] ?? $r->type }}</td>
<td>{{ \Modules\Reports\ReportSchedule::frequencies()[$r->frequency] ?? $r->frequency }}</td>
<td>{{ ucfirst($r->channel) }}</td>
<td><span class="hint">{{ \Str::limit($r->recipients,60) }}</span></td>
<td class="nowrap">{{ $r->last_run_on ? d($r->last_run_on) : '—' }}</td>
<td><span class="badge {{ $r->is_active ? 'b-green' : 'b-grey' }}">{{ $r->is_active ? 'active' : 'paused' }}</span></td>
<td><div class="row-actions">
<form method="POST" action="{{ route('reports.schedules.toggle',$r) }}" style="display:inline">@csrf<button class="icon-btn">{{ $r->is_active ? 'Pause' : 'Resume' }}</button></form>
<form method="POST" action="{{ route('reports.schedules.destroy',$r) }}" style="display:inline" data-confirm="Remove this schedule?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form></div></td></tr>
@empty<tr><td colspan="8"><div class="empty">No schedules — set one above.</div></td></tr>@endforelse</table></div>
{{ $rows->links() }}</div>
@endsection
