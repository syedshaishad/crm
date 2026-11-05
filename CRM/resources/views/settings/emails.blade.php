@extends('layouts.app')
@section('title','Email templates')
@section('content')
@php
  $groups = [
    'invoice' => ['title' => 'Invoice email', 'desc' => 'Sent from an invoice via “Email to client”.'],
    'receipt' => ['title' => 'Payment receipt email', 'desc' => 'Sent from a payment via “Email receipt”.'],
    'proposal' => ['title' => 'Proposal email', 'desc' => 'Sent from a proposal via “Email to client” — carries the signing link.'],
    'reset' => ['title' => 'Password reset email', 'desc' => 'Sent when staff click “Forgot password”.'],
    'reminder' => ['title' => 'Daily digest email', 'desc' => 'Morning roundup: follow-ups, due tasks, overdue invoices, expiring proposals, AMC renewals, compliance documents and cheques due.'],
    'overdue' => ['title' => 'Overdue nudge email', 'desc' => 'Auto-sent to clients with overdue invoices (at most once every 3 days each).'],
    'followup' => ['title' => 'Proposal follow-up email', 'desc' => 'Sent from a proposal via “Send follow-up” — at most once every 3 days each.'],
    'statement' => ['title' => 'Account statement email', 'desc' => 'Sent from a client ledger via “Email statement” — full ledger plus pay links.'],
  ];
  $customised = 0;
  foreach ($groups as $g => $meta) {
      $subject = $s->get('email.'.$g.'_subject');
      $body = $s->get('email.'.$g.'_body');
      if (($subject && $subject !== ($defaults['email.'.$g.'_subject'] ?? null)) || ($body && $body !== ($defaults['email.'.$g.'_body'] ?? null))) {
          $customised++;
      }
  }
  $outboxTotal = \Illuminate\Support\Facades\DB::table('mail_logs')->count();
  $lastMail = \Illuminate\Support\Facades\DB::table('mail_logs')->orderByDesc('id')->first();
  $outboxRows = \Illuminate\Support\Facades\DB::table('mail_logs')->orderByDesc('id')->limit(25)->get();
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
    </span>
    <div>
      <h1>Email templates</h1>
      <div class="crumb">Every customer email is built from these templates</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.integrations') }}">Test mail setup</a>
    <button class="btn sm" type="submit" form="emails-form">Save templates</button>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Templates</span>
    <b class="ix-metric-val">{{ count($groups) }}</b>
    <span class="ix-metric-sub">customer and staff emails</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Customised</span>
    <b class="ix-metric-val">{{ $customised }}<span class="br-metric-of">/{{ count($groups) }}</span></b>
    <span class="ix-metric-sub">{{ $customised === 0 ? 'all using design defaults' : 'templates changed by you' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Outbox</span>
    <b class="ix-metric-val">{{ $outboxTotal }}</b>
    <span class="ix-metric-sub">messages logged on send</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Last send</span>
    <b class="ix-metric-val is-money">{{ $lastMail ? d($lastMail->created_at,'j M, H:i') : '—' }}</b>
    <span class="ix-metric-sub">{{ $lastMail ? \Str::limit($lastMail->subject ?? 'no subject', 36) : 'nothing sent yet' }}</span>
  </div>
</div>

<form id="emails-form" method="POST" action="{{ route('settings.emails.store') }}">@csrf
<p class="fhint" style="max-width:760px;margin:-4px 0 16px">Write plain text with <code class="st-code">&#123;&#123; &#125;&#125;</code> tokens — the preview under each template shows exactly what the client receives.</p>

<div class="st-grid">
  @foreach($groups as $g => $meta)
    @php
      $subjectVal = old('email_'.$g.'_subject', $s->get('email.'.$g.'_subject', $defaults['email.'.$g.'_subject']));
      $bodyVal = old('email_'.$g.'_body', $s->get('email.'.$g.'_body', $defaults['email.'.$g.'_body']));
      $isCustom = ($s->get('email.'.$g.'_subject') && $s->get('email.'.$g.'_subject') !== ($defaults['email.'.$g.'_subject'] ?? null)) || ($s->get('email.'.$g.'_body') && $s->get('email.'.$g.'_body') !== ($defaults['email.'.$g.'_body'] ?? null));
    @endphp
    <div class="card ix-card">
      <div class="card-head ix-card-head">
        <div><h3>{{ $meta['title'] }}</h3><span class="hint">{{ $meta['desc'] }}</span></div>
        <span class="ix-state {{ $isCustom ? 'is-blue' : 'is-grey' }}"><i></i>{{ $isCustom ? 'Custom' : 'Default' }}</span>
      </div>

      <label class="f" for="email-{{ $g }}-subject">Subject</label>
      <input class="in" id="email-{{ $g }}-subject" name="email_{{ $g }}_subject" value="{{ $subjectVal }}" required maxlength="255">

      <label class="f" for="email-{{ $g }}-body">Body</label>
      <textarea class="in" id="email-{{ $g }}-body" name="email_{{ $g }}_body" rows="9" required>{{ $bodyVal }}</textarea>

      <div class="st-tokens">
        <span class="st-tokens-lbl">Tokens</span>
        @foreach(email_placeholders($g) as $ph)
          <code class="st-code">&#123;&#123;{{ $ph }}&#125;&#125;</code>
        @endforeach
      </div>

      <details class="st-snippet">
        <summary><span class="st-snippet-title">Preview with sample data</span></summary>
        <div class="st-snippet-body">
          <div class="bill-box">
            <div class="cap">{{ render_template('email.'.$g.'_subject', $samples[$g]) }}</div>
            <div class="sub" style="white-space:pre-line">{{ render_template('email.'.$g.'_body', $samples[$g]) }}</div>
          </div>
        </div>
      </details>
    </div>
  @endforeach
</div>

<div class="card ix-card st-card">
  <div class="st-actions">
    <div class="st-actions-txt">
      <strong>Save email templates</strong>
      <span>Applies to the next email sent from each workflow.</span>
    </div>
    <div class="st-actions-btns">
      <a class="btn sec" href="{{ route('settings.index') }}">Cancel</a>
      <a class="btn sec" href="{{ route('settings.integrations') }}">Test mail setup</a>
      <button class="btn" type="submit">Save email templates</button>
    </div>
  </div>
</div>
</form>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Outbox — recent mail</h3><span class="hint">Auto-logged on send; the latest 25 messages.</span></div>
    <span class="ix-tag">{{ number_format($outboxTotal) }} total</span>
  </div>
  <div class="ix-table-scroll">
    <table class="tbl ix-table em-table">
      <thead><tr><th class="ix-c-when">When</th><th class="ix-c-to">To</th><th class="ix-c-subject">Subject</th></tr></thead>
      <tbody>
        @forelse($outboxRows as $m)
          <tr>
            <td class="ix-c-when nowrap">{{ d($m->created_at) }}</td>
            <td class="ix-c-to">{{ \Str::limit($m->to ?? '—', 48) }}</td>
            <td class="ix-c-subject">{{ $m->subject ?? '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="3">
            <div class="ix-empty">
              <span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg></span>
              <strong>Nothing sent yet</strong>
              <p>Invoice, receipt, proposal, digest, nudge, follow-up and statement mails land here.</p>
            </div>
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
