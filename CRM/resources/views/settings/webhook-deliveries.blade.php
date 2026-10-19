@extends('layouts.app')
@section('title','Webhook log')
@section('content')
<div class="card">
<div class="card-head"><h3>{{ $webhook->name }}</h3><a class="btn sec sm" href="{{ route('settings.api') }}">Back to API</a></div>
<p class="hint" style="margin-top:0">{{ $webhook->url }} · verify <code>X-CRM-Signature</code> (HMAC-SHA256 of the raw body with your secret).</p>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>When</th><th>Event</th><th>Status</th><th>HTTP</th><th>Error</th></tr>
@forelse($rows as $d)<tr>
<td class="nowrap">{{ d($d->created_at,'j M Y, h:i A') }}</td>
<td><span class="badge b-grey">{{ $d->event }}</span></td>
<td><span class="badge {{ $d->status==='sent' ? 'b-green' : 'b-red' }}">{{ $d->status }}</span></td>
<td class="nowrap">{{ $d->http_code ?? '—' }}</td>
<td><span class="hint">{{ \Str::limit($d->error ?? '',120) }}</span></td></tr>
@empty<tr><td colspan="5"><div class="empty">Nothing delivered yet — trigger an event or hit Ping.</div></td></tr>@endforelse</table></div>
{{ $rows->links() }}</div>
@endsection
