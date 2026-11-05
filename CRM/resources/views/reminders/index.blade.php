@extends('layouts.app')
@section('title','Reminder log')
@section('content')
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
    </span>
    <div>
      <h1>Reminder log</h1>
      <div class="crumb">{{ $stats['logs'] }} {{ \Illuminate\Support\Str::plural('invoice reminder', $stats['logs']) }} · {{ $stats['followups'] }} proposal {{ \Illuminate\Support\Str::plural('follow-up', $stats['followups']) }} · {{ $stats['whatsapps'] }} WhatsApp {{ \Illuminate\Support\Str::plural('nudge', $stats['whatsapps']) }}</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.general') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
      Reminder settings
    </a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric"><span class="ix-metric-lbl">Invoice reminders</span><b class="ix-metric-val">{{ $stats['logs'] }}</b><span class="ix-metric-sub">{{ $stats['auto'] }} automatic · {{ $stats['logs'] - $stats['auto'] }} manual</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Failed deliveries</span><b class="ix-metric-val">{{ $stats['failed'] }}</b><span class="ix-metric-sub">{{ $stats['failed'] ? 'check the error column' : 'no failures logged' }}</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">Follow-ups</span><b class="ix-metric-val">{{ $stats['followups'] }}</b><span class="ix-metric-sub">proposal nudges · max once / 3 days each</span></div>
  <div class="ix-metric"><span class="ix-metric-lbl">WhatsApp nudges</span><b class="ix-metric-val">{{ $stats['whatsapps'] }}</b><span class="ix-metric-sub">{{ $stats['wa_failed'] }} failed · max once / 3 days each</span></div>
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Invoice reminders</h3><span class="hint">Last 100 attempts, newest first · nightly auto (max once / 3 days per invoice) and manual nudges</span></div>
  </div>
  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table rm-table rm-inv">
      <thead>
        <tr><th class="ix-c-main">Reminder</th><th class="ix-c-ref">Invoice</th><th class="ix-c-client">Client</th><th class="ix-c-date">Sent</th><th class="ix-c-status">Status</th></tr>
      </thead>
      <tbody>
      @forelse($logs as $g)
        <tr>
          <td class="ix-c-main">
            <div class="ix-main">
              <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="m4 6 8 6 8-6"/></svg></span>
              <div class="ix-main-txt">
                <span class="ix-name">{{ $g->recipient ?: 'No recipient' }}</span>
                <span class="ix-sub">{{ $g->channel ?: 'email' }} · {{ $g->source === 'auto' ? 'automatic' : 'manual' }}</span>
              </div>
            </div>
          </td>
          <td class="ix-c-ref">
            @if($g->invoice)
              <div class="ix-main-txt">
                <a class="ix-name" href="{{ route('invoices.show', $g->invoice_id) }}">{{ $g->invoice }}</a>
                <span class="ix-sub">due reminder</span>
              </div>
            @else
              <span class="ix-client">—</span>
            @endif
          </td>
          <td class="ix-c-client"><span class="ix-client">{{ $g->client ?: '—' }}</span></td>
          <td class="ix-c-date">
            <div class="ix-main-txt">
              <span class="ix-client">{{ d($g->created_at, 'j M Y') }}</span>
              <span class="ix-sub">{{ d($g->created_at, 'h:i A') }}</span>
            </div>
          </td>
          <td class="ix-c-status">
            <span class="ix-state {{ $g->status === 'sent' ? 'is-green' : 'is-red' }}"><i></i>{{ $g->status === 'sent' ? 'Sent' : 'Failed' }}</span>
            @if($g->error)<span class="ix-sub is-danger" title="{{ $g->error }}">{{ \Illuminate\Support\Str::limit($g->error, 46) }}</span>@endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg></span><strong>No invoice reminders yet</strong><p>Overdue invoices get a nightly nudge, and manual reminders are logged here.</p><div class="ix-empty-actions"><a class="btn sm sec" href="{{ route('settings.general') }}">Reminder settings</a></div></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card ix-card rm-card">
  <div class="card-head ix-card-head">
    <div><h3>Proposal follow-ups</h3><span class="hint">Last 100 attempts, newest first · max once / 3 days each</span></div>
  </div>
  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table rm-table rm-prop">
      <thead>
        <tr><th class="ix-c-main">Follow-up</th><th class="ix-c-ref">Proposal</th><th class="ix-c-client">Client</th><th class="ix-c-date">Sent</th><th class="ix-c-status">Status</th></tr>
      </thead>
      <tbody>
      @forelse($followups as $g)
        <tr>
          <td class="ix-c-main">
            <div class="ix-main">
              <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></span>
              <div class="ix-main-txt">
                <span class="ix-name">{{ $g->recipient ?: 'No recipient' }}</span>
                <span class="ix-sub">{{ $g->channel ?: 'email' }} · {{ $g->source === 'auto' ? 'automatic' : 'manual' }}</span>
              </div>
            </div>
          </td>
          <td class="ix-c-ref">
            @if($g->proposal)
              <div class="ix-main-txt">
                <a class="ix-name" href="{{ route('proposals.show', $g->proposal_id) }}">{{ $g->proposal }}</a>
                <span class="ix-sub">unopened proposal</span>
              </div>
            @else
              <span class="ix-client">—</span>
            @endif
          </td>
          <td class="ix-c-client"><span class="ix-client">{{ $g->client ?: '—' }}</span></td>
          <td class="ix-c-date">
            <div class="ix-main-txt">
              <span class="ix-client">{{ d($g->created_at, 'j M Y') }}</span>
              <span class="ix-sub">{{ d($g->created_at, 'h:i A') }}</span>
            </div>
          </td>
          <td class="ix-c-status">
            <span class="ix-state {{ $g->status === 'sent' ? 'is-green' : 'is-red' }}"><i></i>{{ $g->status === 'sent' ? 'Sent' : 'Failed' }}</span>
            @if($g->error)<span class="ix-sub is-danger" title="{{ $g->error }}">{{ \Illuminate\Support\Str::limit($g->error, 46) }}</span>@endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></span><strong>No proposal follow-ups yet</strong><p>Unopened proposals get a gentle nudge every three days, and each attempt lands here.</p></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card ix-card rm-card">
  <div class="card-head ix-card-head">
    <div><h3>WhatsApp nudges</h3><span class="hint">Last 100 attempts, newest first · max once / 3 days each</span></div>
  </div>
  <div class="tbl-scroll roomy ix-table-scroll">
    <table class="tbl ix-table rm-table rm-wa">
      <thead>
        <tr><th class="ix-c-main">Message</th><th class="ix-c-ref">Invoice</th><th class="ix-c-client">Client</th><th class="ix-c-date">Sent</th><th class="ix-c-status">Status</th></tr>
      </thead>
      <tbody>
      @forelse($whatsapps as $g)
        <tr>
          <td class="ix-c-main">
            <div class="ix-main">
              <span class="ix-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.7a8.5 8.5 0 1 1 16.1-3.8z"/><path d="M8.5 9.5c0 3 2 5 5 5"/></svg></span>
              <div class="ix-main-txt">
                <span class="ix-name">{{ $g->recipient ? '+'.$g->recipient : 'No number' }}</span>
                <span class="ix-sub">{{ $g->template ?: 'payment nudge' }} · {{ $g->source === 'auto' ? 'automatic' : 'manual' }}</span>
              </div>
            </div>
          </td>
          <td class="ix-c-ref">
            @if($g->invoice)
              <div class="ix-main-txt">
                <a class="ix-name" href="{{ route('invoices.show', $g->invoice_id) }}">{{ $g->invoice }}</a>
                <span class="ix-sub">due reminder</span>
              </div>
            @else
              <span class="ix-client">—</span>
            @endif
          </td>
          <td class="ix-c-client"><span class="ix-client">{{ $g->client ?: '—' }}</span></td>
          <td class="ix-c-date">
            <div class="ix-main-txt">
              <span class="ix-client">{{ d($g->created_at, 'j M Y') }}</span>
              <span class="ix-sub">{{ d($g->created_at, 'h:i A') }}</span>
            </div>
          </td>
          <td class="ix-c-status">
            <span class="ix-state {{ $g->status === 'sent' ? 'is-green' : 'is-red' }}"><i></i>{{ $g->status === 'sent' ? 'Sent' : 'Failed' }}</span>
            @if($g->error)<span class="ix-sub is-danger" title="{{ $g->error }}">{{ \Illuminate\Support\Str::limit($g->error, 46) }}</span>@endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5"><div class="ix-empty"><span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.7a8.5 8.5 0 1 1 16.1-3.8z"/></svg></span><strong>No WhatsApp nudges yet</strong><p>Send a payment nudge from an invoice and the delivery log appears here.</p></div></td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
