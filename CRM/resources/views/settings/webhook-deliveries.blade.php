@extends('layouts.app')
@section('title','Webhook log')
@section('content')
@php
  $total = $stats['total'] ?? $rows->total();
  $sent = $stats['sent'] ?? 0;
  $failed = $stats['failed'] ?? 0;
  $successRate = $total > 0 ? round(($sent / $total) * 100) : 0;
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 16.98h-5.99c-1.1 0-1.95.94-2.48 1.9A4 4 0 0 1 2 17c.01-.7.2-1.4.56-2"/><path d="m6 17 3.13-5.78c.53-.97.1-2.18-.5-3.1a4 4 0 1 1 6.88-4.02"/><path d="m12 6 3.13 5.73C15.66 12.7 16.9 13 18 13a4 4 0 0 1 0 8"/></svg>
    </span>
    <div>
      <h1>{{ $webhook->name }}</h1>
      <div class="crumb">Delivery log · {{ $webhook->is_active ? 'live' : 'paused' }}</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.api') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Back to API
    </a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Deliveries</span>
    <b class="ix-metric-val">{{ number_format($total) }}</b>
    <span class="ix-metric-sub">events sent to this endpoint</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Delivered</span>
    <b class="ix-metric-val">{{ number_format($sent) }}</b>
    <span class="ix-metric-sub">{{ $successRate }}% success rate</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Failed</span>
    <b class="ix-metric-val">{{ number_format($failed) }}</b>
    <span class="ix-metric-sub">{{ $failed ? 'inspect the error column' : 'no failures recorded' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Last delivery</span>
    <b class="ix-metric-val is-money">{{ !empty($stats['last']) ? d($stats['last'],'j M, H:i') : '—' }}</b>
    <span class="ix-metric-sub">{{ !empty($stats['last']) ? 'most recent attempt' : 'nothing delivered yet' }}</span>
  </div>
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Recent attempts</h3><span class="hint">{{ $webhook->url }}</span></div>
    <span class="ix-state {{ $failed ? 'is-red' : ($total ? 'is-green' : 'is-grey') }}"><i></i>{{ $failed ? 'Attention' : ($total ? 'Healthy' : 'Idle') }}</span>
  </div>
  <p class="fhint" style="margin-top:-4px">Verify <code class="st-code">X-CRM-Signature</code> — an HMAC-SHA256 of the raw body using your secret.</p>

  <div class="ix-table-scroll">
    <table class="tbl ix-table wh-table">
      <thead><tr>
        <th class="ix-c-main">When</th>
        <th class="ix-c-event">Event</th>
        <th class="ix-c-status">Status</th>
        <th class="ix-c-http">HTTP</th>
        <th class="ix-c-error">Error</th>
      </tr></thead>
      <tbody>
        @forelse($rows as $d)
          <tr>
            <td class="ix-c-main nowrap"><span class="ix-name">{{ d($d->created_at,'j M Y, h:i A') }}</span></td>
            <td class="ix-c-event"><span class="ix-tag">{{ $d->event }}</span></td>
            <td class="ix-c-status"><span class="ix-state {{ $d->status==='sent' ? 'is-green' : 'is-red' }}"><i></i>{{ ucfirst($d->status) }}</span></td>
            <td class="ix-c-http nowrap">{{ $d->http_code ?? '—' }}</td>
            <td class="ix-c-error"><span class="ix-sub">{{ \Str::limit($d->error ?? '',120) ?: '—' }}</span></td>
          </tr>
        @empty
          <tr><td colspan="5">
            <div class="ix-empty">
              <span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 16.98h-5.99c-1.1 0-1.95.94-2.48 1.9A4 4 0 0 1 2 17c.01-.7.2-1.4.56-2"/><path d="m6 17 3.13-5.78c.53-.97.1-2.18-.5-3.1a4 4 0 1 1 6.88-4.02"/><path d="m12 6 3.13 5.73C15.66 12.7 16.9 13 18 13a4 4 0 0 1 0 8"/></svg></span>
              <strong>Nothing delivered yet</strong>
              <p>Trigger an event or send a ping from the API page.</p>
              <div class="ix-empty-actions"><a class="btn sm" href="{{ route('settings.api') }}">Back to API</a></div>
            </div>
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($rows->hasPages())<div class="st-pager">{{ $rows->links() }}</div>@endif
</div>
@endsection
