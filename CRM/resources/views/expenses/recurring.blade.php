@extends('layouts.app')
@section('title','Recurring expenses')
@section('content')
<div class="card">
<div class="card-head"><h3>Recurring schedules</h3><a class="btn sm sec" href="{{ route('expenses.index') }}">All expenses</a></div>
<p class="fhint">Repeat spend auto-books on schedule days (daily run). Pause anytime.</p>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Title</th><th>Every</th><th style="text-align:right">Amount</th><th>Next run</th><th>Last run</th><th>Status</th><th></th></tr>
@forelse($profiles as $p)<tr>
<td>{{ $p->title }}@if($p->vendor)<div class="hint">{{ $p->vendor }}</div>@endif @if($p->project)<div class="hint">{{ $p->project->name }}</div>@endif</td>
<td><span class="badge b-blue">{{ \Modules\Expenses\RecurringExpenseProfile::frequencies()[$p->frequency] ?? $p->frequency }}</span></td>
<td class="nowrap" style="text-align:right">{{ money($p->amount) }}</td>
<td class="nowrap">{{ d($p->next_run_on) }}</td>
<td class="nowrap">{{ $p->last_run_on ? d($p->last_run_on) : '—' }}</td>
<td><span class="badge {{ $p->is_active ? 'b-green' : 'b-grey' }}">{{ $p->is_active ? 'active' : 'paused' }}</span></td>
<td><div class="row-actions">
<form method="POST" action="{{ route('expenses.recurring.toggle',$p) }}" style="display:inline">@csrf<button class="icon-btn">{{ $p->is_active ? 'Pause' : 'Resume' }}</button></form>
<form method="POST" action="{{ route('expenses.recurring.destroy',$p) }}" style="display:inline" data-confirm="Delete this recurring schedule? Already booked expenses stay.">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form></div></td></tr>
@empty<tr><td colspan="7"><div class="empty">No schedules yet.<br><br>Open any expense and hit “Make recurring”.</div></td></tr>@endforelse</table></div>
{{ $profiles->links() }}</div>
@endsection
