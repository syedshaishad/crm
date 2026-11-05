@extends('layouts.app')
@section('title','Integrations')
@section('content')
@php
  $rk = config('razorpay.key_id');
  $rzpState = ! $rk ? 'off' : (str_starts_with($rk,'rzp_live') ? 'live' : (str_starts_with($rk,'rzp_test') ? 'test' : 'custom'));
  $rzpTone = $rzpState === 'live' ? 'is-green' : ($rzpState === 'test' ? 'is-amber' : ($rzpState === 'off' ? 'is-grey' : 'is-blue'));
  $mailHost = config('mail.mailers.smtp.host');
  $waOn = setting('whatsapp.enabled','0') === '1' && config('whatsapp.phone_number_id') && config('whatsapp.token');
  $waEnabled = setting('whatsapp.enabled','0') === '1';
  $waAuto = setting('whatsapp.auto','0') === '1';
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M8 7.5 10.5 16M16 7.5 13.5 16M8.5 6h7"/></svg>
    </span>
    <div>
      <h1>Integrations</h1>
      <div class="crumb">Payments, mail delivery and WhatsApp reminders</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Settings
    </a>
    <button class="btn sm" type="submit" form="integrations-form">Save integrations</button>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Razorpay</span>
    <b class="ix-metric-val"><span class="ix-state {{ $rzpTone }}"><i></i>{{ ucfirst($rzpState) }}</span></b>
    <span class="ix-metric-sub">{{ $rzpState === 'off' ? 'add keys to accept payments' : ($rzpState === 'live' ? 'live payments are enabled' : 'not live yet') }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">SMTP host</span>
    <b class="ix-metric-val st-metric-code">{{ $mailHost ?: 'Not set' }}</b>
    <span class="ix-metric-sub">outgoing mail server</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">WhatsApp</span>
    <b class="ix-metric-val">{{ $waOn ? 'Configured' : 'Off' }}</b>
    <span class="ix-metric-sub">{{ $waOn ? 'template reminders ready' : 'add Meta credentials below' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Razorpay webhook</span>
    <b class="ix-metric-val is-money">Endpoint</b>
    <span class="ix-metric-sub st-metric-code">{{ \Str::limit(route('webhooks.razorpay'), 40) }}</span>
  </div>
</div>

<div class="st-grid">
  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Razorpay</h3><span class="hint">Payment gateway keys. Leave blank to disable online payments.</span></div>
      <span class="ix-state {{ $rzpTone }}"><i></i>{{ ucfirst($rzpState) }}</span>
    </div>
    <form id="integrations-form" method="POST" action="{{ route('settings.integrations.store') }}">@csrf
      <label class="f" for="int-razorpay-key">Key ID</label>
      <input class="in" id="int-razorpay-key" name="RAZORPAY_KEY_ID" value="{{ old('RAZORPAY_KEY_ID', config('razorpay.key_id')) }}" placeholder="rzp_live_…" autocomplete="off">
      <label class="f" for="int-razorpay-secret">Key secret</label>
      <input class="in" id="int-razorpay-secret" type="password" name="RAZORPAY_KEY_SECRET" value="{{ old('RAZORPAY_KEY_SECRET', config('razorpay.key_secret')) }}" autocomplete="new-password">
      <label class="f" for="int-razorpay-webhook">Webhook secret</label>
      <input class="in" id="int-razorpay-webhook" type="password" name="RAZORPAY_WEBHOOK_SECRET" value="{{ old('RAZORPAY_WEBHOOK_SECRET', config('razorpay.webhook_secret')) }}" autocomplete="new-password">
      <p class="fhint">Register this URL with Razorpay: <code class="st-code">{{ route('webhooks.razorpay') }}</code></p>

      <div class="fsec">SMTP mail</div>
      <div class="form-grid">
        <div><label class="f" for="int-mail-host">Host</label><input class="in" id="int-mail-host" name="MAIL_HOST" value="{{ old('MAIL_HOST', config('mail.mailers.smtp.host')) }}" required placeholder="mail.yourdomain.com"></div>
        <div><label class="f" for="int-mail-port">Port</label><input class="in" id="int-mail-port" name="MAIL_PORT" value="{{ old('MAIL_PORT', config('mail.mailers.smtp.port')) }}" required></div>
        <div><label class="f" for="int-mail-user">Username</label><input class="in" id="int-mail-user" name="MAIL_USERNAME" value="{{ old('MAIL_USERNAME', config('mail.mailers.smtp.username')) }}"></div>
        <div><label class="f" for="int-mail-pass">Password</label><input class="in" id="int-mail-pass" type="password" name="MAIL_PASSWORD" value="{{ old('MAIL_PASSWORD', config('mail.mailers.smtp.password')) }}" autocomplete="new-password"></div>
        <div><label class="f" for="int-mail-enc">Encryption</label><input class="in" id="int-mail-enc" name="MAIL_ENCRYPTION" value="{{ old('MAIL_ENCRYPTION', config('mail.mailers.smtp.encryption')) }}" placeholder="ssl / tls"></div>
      </div>
      <div class="st-actions-inline">
        <button class="btn" type="submit">Save integrations</button>
        <a class="btn sec" href="{{ route('settings.index') }}">Cancel</a>
        <span class="hint">Saved to this installation’s <code class="st-code">.env</code>.</span>
      </div>
    </form>

    <div class="st-divider"></div>
    <form method="POST" action="{{ route('settings.integrations.test') }}" class="st-test-row">@csrf
      <div>
        <strong>Test mail delivery</strong>
        <span class="hint">Prove the SMTP settings work before emailing invoices.</span>
      </div>
      <div class="st-test-actions">
        <span class="hint">to {{ setting('mail.from_address', auth()->user()->email) }}</span>
        <button class="btn sec sm">Send test email</button>
      </div>
    </form>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>WhatsApp Cloud API</h3><span class="hint">Business-initiated reminders go out as an approved Meta template with four body parameters: client name, invoice number, balance and due date.</span></div>
      <span class="ix-state {{ $waOn ? 'is-green' : 'is-grey' }}"><i></i>{{ $waOn ? 'Configured' : 'Off' }}</span>
    </div>
    <form method="POST" action="{{ route('settings.whatsapp.store') }}">@csrf
      <div class="st-checks">
        <label class="st-check">
          <input type="checkbox" name="WHATSAPP_ENABLED" value="1" {{ $waEnabled?'checked':'' }}>
          <span><b>Enable WhatsApp reminders</b><br>Allow staff to send approved template messages.</span>
        </label>
        <label class="st-check">
          <input type="checkbox" name="WHATSAPP_AUTO" value="1" {{ $waAuto?'checked':'' }}>
          <span><b>Auto-send nightly</b><br>Alongside email nudges, max once every 3 days per client.</span>
        </label>
      </div>
      <label class="f" for="int-wa-phone">Phone number ID</label>
      <input class="in" id="int-wa-phone" name="WHATSAPP_PHONE_NUMBER_ID" value="{{ old('WHATSAPP_PHONE_NUMBER_ID', config('whatsapp.phone_number_id')) }}" autocomplete="off">
      <label class="f" for="int-wa-token">Access token</label>
      <input class="in" id="int-wa-token" type="password" name="WHATSAPP_TOKEN" value="{{ old('WHATSAPP_TOKEN', config('whatsapp.token') ? '••••••••' : '') }}" autocomplete="new-password" placeholder="EAAG…">
      <p class="fhint">Leave masked to keep the saved token.</p>
      <div class="form-grid">
        <div><label class="f" for="int-wa-template">Template name</label><input class="in" id="int-wa-template" name="WHATSAPP_TEMPLATE" value="{{ old('WHATSAPP_TEMPLATE', setting('whatsapp.template','payment_reminder')) }}"></div>
        <div><label class="f" for="int-wa-lang">Language</label><input class="in" id="int-wa-lang" name="WHATSAPP_LANG" value="{{ old('WHATSAPP_LANG', setting('whatsapp.lang','en')) }}"></div>
      </div>
      <label class="f" for="int-wa-country">Country code <span class="hint">— prefixed to 10-digit mobiles</span></label>
      <input class="in" id="int-wa-country" name="WHATSAPP_COUNTRY_CODE" value="{{ old('WHATSAPP_COUNTRY_CODE', setting('whatsapp.country_code','91')) }}">
      <p class="fhint">Create the template in WhatsApp Manager first — for example <code class="st-code">payment_reminder</code> with language <code class="st-code">en</code>.</p>
      <div class="st-actions-inline">
        <button class="btn" type="submit">Save WhatsApp</button>
      </div>
    </form>

    <div class="st-divider"></div>
    <form method="POST" action="{{ route('settings.whatsapp.test') }}" class="st-test-row">@csrf
      <div>
        <strong>Send a test template</strong>
        <span class="hint">Prove it with a real template message.</span>
      </div>
      <div class="st-test-actions">
        <input class="in" name="to" placeholder="98765 43210" required aria-label="Test mobile">
        <button class="btn sec sm">Send test template</button>
      </div>
    </form>
  </div>
</div>
@endsection
