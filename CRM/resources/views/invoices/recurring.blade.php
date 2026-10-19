@extends('layouts.app')
@section('title','Recurring invoices')
@section('content')
<div class="card">
<div class="card-head"><h3>Recurring schedules</h3><a class="btn sm sec" href="{{ route('invoices.index') }}">All invoices</a></div>
<p class="fhint">Subscriptions bill themselves on schedule days (daily run ~7:30 AM). Trials go out free, first bills can prorate, and monthly plans can snap to a billing day. Pause or cancel anytime.</p>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Client</th><th>Every</th><th>Next run</th><th>Last run</th><th>Status</th><th></th></tr>
@forelse($profiles as $p)<tr>
<td>{{ $p->client->company ?: ($p->client->name ?? '') }}@if($p->project)<div class="hint">{{ $p->project->name }}</div>@endif<div class="hint">{{ count($p->items ?? []) }} line(s) · {{ money(collect($p->items)->sum(fn ($it) => ($it['qty'] ?? 0) * ($it['rate'] ?? 0))) }}</div>@if($p->trial_ends_on && $p->trial_ends_on->gte(now()->toDateString()))<div class="hint">trial till {{ d($p->trial_ends_on) }}</div>@endif @if($p->prorate_first && !$p->last_run_on)<span class="chip">prorated first bill</span>@endif @if($p->billing_day)<span class="chip">bills on {{ $p->billing_day }}</span>@endif</td>
<td><span class="badge b-blue">{{ \Modules\Invoices\RecurringProfile::frequencies()[$p->frequency] ?? $p->frequency }}</span></td>
<td class="nowrap">{{ d($p->next_run_on) }}</td>
<td class="nowrap">{{ $p->last_run_on ? d($p->last_run_on) : '—' }}</td>
<td><span class="badge {{ $p->status === 'active' ? 'b-green' : ($p->status === 'paused' ? 'b-amber' : 'b-grey') }}">{{ \Modules\Invoices\RecurringProfile::statuses()[$p->status] ?? $p->status }}</span>@if($p->auto_send)<div class="hint">auto-sends</div>@endif</td>
<td><div class="row-actions">
@if($p->status !== 'cancelled')
<form method="POST" action="{{ route('recurring.toggle',$p) }}" style="display:inline">@csrf<button class="icon-btn">{{ $p->status === 'active' ? 'Pause' : 'Resume' }}</button></form>
<form method="POST" action="{{ route('recurring.cancel',$p) }}" style="display:inline" data-confirm="Cancel this subscription? Already generated invoices stay.">@csrf<button class="icon-btn danger">Cancel</button></form>
@endif
<form method="POST" action="{{ route('recurring.autosend',$p) }}" style="display:inline">@csrf<button class="icon-btn">{{ $p->auto_send ? 'Manual' : 'Auto-send' }}</button></form>
<form method="POST" action="{{ route('recurring.destroy',$p) }}" style="display:inline" data-confirm="Delete this recurring schedule? Already generated invoices stay.">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form></div></td></tr>
@empty<tr><td colspan="6"><div class="empty">No schedules yet.<br><br>Open any invoice and hit “Make recurring”.</div></td></tr>@endforelse</table></div>
{{ $profiles->links() }}</div>
@endsection
