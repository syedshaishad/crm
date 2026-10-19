@extends('layouts.app')
@section('title','Integrations')
@section('content')
<div class="card" style="max-width:720px"><div class="card-head"><h3>Razorpay & SMTP</h3>@php $rk = config('razorpay.key_id'); @endphp @if(!$rk)<span class="badge b-grey">Razorpay off</span>@elseif(str_starts_with($rk,'rzp_live'))<span class="badge b-green">Razorpay live</span>@elseif(str_starts_with($rk,'rzp_test'))<span class="badge b-amber">Razorpay test</span>@else <span class="badge b-blue">Razorpay custom</span>@endif</div>
<p class="hint">Saved to this installation's <code>.env</code>. Webhook URL: <code>{{ route('webhooks.razorpay') }}</code></p>
<form method="POST" action="{{ route('settings.integrations.store') }}">@csrf
<h4>Razorpay (leave blank to disable)</h4>
<label class="f">Key ID</label><input class="in" name="RAZORPAY_KEY_ID" value="{{ old('RAZORPAY_KEY_ID', config('razorpay.key_id')) }}" placeholder="rzp_live_…" autocomplete="off">
<label class="f">Key secret</label><input class="in" type="password" name="RAZORPAY_KEY_SECRET" value="{{ old('RAZORPAY_KEY_SECRET', config('razorpay.key_secret')) }}" autocomplete="new-password">
<label class="f">Webhook secret</label><input class="in" type="password" name="RAZORPAY_WEBHOOK_SECRET" value="{{ old('RAZORPAY_WEBHOOK_SECRET', config('razorpay.webhook_secret')) }}" autocomplete="new-password">
<h4>SMTP mail</h4>
<div class="form-grid">
<div><label class="f">Host</label><input class="in" name="MAIL_HOST" value="{{ old('MAIL_HOST', config('mail.mailers.smtp.host')) }}" required placeholder="mail.yourdomain.com"></div>
<div><label class="f">Port</label><input class="in" name="MAIL_PORT" value="{{ old('MAIL_PORT', config('mail.mailers.smtp.port')) }}" required></div>
<div><label class="f">Username</label><input class="in" name="MAIL_USERNAME" value="{{ old('MAIL_USERNAME', config('mail.mailers.smtp.username')) }}"></div>
<div><label class="f">Password</label><input class="in" type="password" name="MAIL_PASSWORD" value="{{ old('MAIL_PASSWORD', config('mail.mailers.smtp.password')) }}" autocomplete="new-password"></div>
<div><label class="f">Encryption</label><input class="in" name="MAIL_ENCRYPTION" value="{{ old('MAIL_ENCRYPTION', config('mail.mailers.smtp.encryption')) }}" placeholder="ssl / tls"></div>
</div>
<div class="form-actions"><button class="btn" type="submit">Save integrations</button><a class="btn sec" href="{{ route('settings.index') }}">Cancel</a></div></form>
<hr><p class="fhint">Prove the mail settings work before emailing invoices.</p>
<form method="POST" action="{{ route('settings.integrations.test') }}">@csrf<button class="btn sec sm">Send test email</button> <span class="hint">to {{ setting('mail.from_address', auth()->user()->email) }}</span></form></div>

<div class="card" style="max-width:720px"><div class="card-head"><h3>WhatsApp Cloud API</h3>@if(setting('whatsapp.enabled','0')==='1' && config('whatsapp.phone_number_id') && config('whatsapp.token'))<span class="badge b-green">configured</span>@else<span class="badge b-grey">off</span>@endif</div>
<p class="fhint">Business-initiated reminders go out as an approved Meta template with 4 body parameters: client name, invoice number, balance, due date. Create it in your WhatsApp Manager first (e.g. <code>payment_reminder</code>, language <code>en</code>).</p>
<form method="POST" action="{{ route('settings.whatsapp.store') }}">@csrf
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:12px"><input type="checkbox" name="WHATSAPP_ENABLED" value="1" {{ setting('whatsapp.enabled','0')==='1'?'checked':'' }} style="width:auto;margin:0"> Enable WhatsApp reminders</label>
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:12px"><input type="checkbox" name="WHATSAPP_AUTO" value="1" {{ setting('whatsapp.auto','0')==='1'?'checked':'' }} style="width:auto;margin:0"> Auto-send nightly alongside email nudges (overdue, max once / 3 days each)</label>
<div><label class="f">Phone number ID</label><input class="in" name="WHATSAPP_PHONE_NUMBER_ID" value="{{ old('WHATSAPP_PHONE_NUMBER_ID', config('whatsapp.phone_number_id')) }}" autocomplete="off"></div>
<div><label class="f">Access token</label><input class="in" type="password" name="WHATSAPP_TOKEN" value="{{ old('WHATSAPP_TOKEN', config('whatsapp.token') ? '••••••••' : '') }}" autocomplete="new-password" placeholder="EAAG…"><p class="fhint">Leave masked to keep the saved token.</p></div>
<div class="form-grid">
<div><label class="f">Template name</label><input class="in" name="WHATSAPP_TEMPLATE" value="{{ old('WHATSAPP_TEMPLATE', setting('whatsapp.template','payment_reminder')) }}"></div>
<div><label class="f">Language</label><input class="in" name="WHATSAPP_LANG" value="{{ old('WHATSAPP_LANG', setting('whatsapp.lang','en')) }}"></div>
</div>
<div><label class="f">Country code <span class="hint">(prefixed to 10-digit mobiles)</span></label><input class="in" name="WHATSAPP_COUNTRY_CODE" value="{{ old('WHATSAPP_COUNTRY_CODE', setting('whatsapp.country_code','91')) }}" style="max-width:160px"></div>
<div class="form-actions"><button class="btn" type="submit">Save WhatsApp</button></div></form>
<hr><p class="fhint">Prove it with a real template message.</p>
<form method="POST" action="{{ route('settings.whatsapp.test') }}">@csrf<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:end"><div><label class="f">Test mobile</label><input class="in" name="to" style="margin:0" placeholder="98765 43210" required></div><div><button class="btn sec sm">Send test template</button></div></div></form></div>
@endsection
