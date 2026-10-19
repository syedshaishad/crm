@extends('layouts.app')
@section('title','Reminder log')
@section('content')
<div class="card">
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Sent</th><th>Invoice</th><th>Client</th><th>To</th><th>By</th><th>Status</th></tr>
@forelse($logs as $g)<tr><td class="nowrap">{{ d($g->created_at,'j M Y, h:i A') }}</td><td class="nowrap">@if($g->invoice)<a href="{{ route('invoices.show',$g->invoice_id) }}"><b>{{ $g->invoice }}</b></a>@else<span class="hint">—</span>@endif</td><td>{{ $g->client ?: '—' }}</td><td class="nowrap">{{ $g->recipient }}</td><td>{{ $g->source === 'auto' ? 'Auto' : 'Manual' }}</td><td>@if($g->status==='sent')<span class="badge b-green">sent</span>@else<span class="badge b-red" title="{{ $g->error }}">failed</span>@endif</td></tr>@empty<tr><td colspan="6"><div class="empty">No reminders sent yet.</div></td></tr>@endforelse</table></div>
<p class="hint" style="margin:10px 0 0">Nightly auto (max once / 3 days per invoice) and manual nudges. Configure under Settings → General (reminders).</p></div>

<div class="card" style="margin-top:22px"><div class="card-head"><h3>Proposal follow-ups</h3><span class="hint">max once / 3 days each</span></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Sent</th><th>Proposal</th><th>Client</th><th>To</th><th>Status</th></tr>
@forelse($followups as $g)<tr><td class="nowrap">{{ d($g->created_at,'j M Y, h:i A') }}</td><td class="nowrap">@if($g->proposal)<a href="{{ route('proposals.show',$g->proposal_id) }}"><b>{{ $g->proposal }}</b></a>@else<span class="hint">—</span>@endif</td><td>{{ $g->client ?: '—' }}</td><td class="nowrap">{{ $g->recipient }}</td><td>@if($g->status==='sent')<span class="badge b-green">sent</span>@else<span class="badge b-red" title="{{ $g->error }}">failed</span>@endif</td></tr>@empty<tr><td colspan="5"><div class="empty">No proposal follow-ups yet.</div></td></tr>@endforelse</table></div></div>

<div class="card" style="margin-top:22px"><div class="card-head"><h3>WhatsApp nudges</h3><span class="hint">max once / 3 days each</span></div>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Sent</th><th>Invoice</th><th>Client</th><th>To</th><th>By</th><th>Status</th></tr>
@forelse($whatsapps as $g)<tr><td class="nowrap">{{ d($g->created_at,'j M Y, h:i A') }}</td><td class="nowrap">@if($g->invoice)<a href="{{ route('invoices.show',$g->invoice_id) }}"><b>{{ $g->invoice }}</b></a>@else<span class="hint">—</span>@endif</td><td>{{ $g->client ?: '—' }}</td><td class="nowrap">+{{ $g->recipient }}</td><td>{{ $g->source === 'auto' ? 'Auto' : 'Manual' }}</td><td>@if($g->status==='sent')<span class="badge b-green">sent</span>@else<span class="badge b-red" title="{{ $g->error }}">failed</span>@endif</td></tr>@empty<tr><td colspan="6"><div class="empty">No WhatsApp nudges yet.</div></td></tr>@endforelse</table></div></div>
@endsection
