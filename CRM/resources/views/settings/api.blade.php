@extends('layouts.app')
@section('title','API')
@section('content')
@php
  $tokenCount = $tokens->count();
  $readOnly = $tokens->filter(fn ($t) => ! in_array('*', $t->abilities ?? []))->count();
  $webhookCount = ($webhooks ?? collect())->count();
  $failedCount = ($webhooks ?? collect())->sum('fails');
  $activeHooks = ($webhooks ?? collect())->where('is_active', true)->count();
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m8 7-5 5 5 5M16 7l5 5-5 5M13 4l-2 16"/></svg>
    </span>
    <div>
      <h1>API &amp; webhooks</h1>
      <div class="crumb">Bearer tokens, v1 endpoints and outbound event delivery</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Settings
    </a>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Tokens</span>
    <b class="ix-metric-val">{{ $tokenCount }}</b>
    <span class="ix-metric-sub">{{ $readOnly }} read-only · {{ $tokenCount - $readOnly }} full access</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Webhooks</span>
    <b class="ix-metric-val">{{ $webhookCount }}</b>
    <span class="ix-metric-sub">{{ $activeHooks }} live · {{ $webhookCount - $activeHooks }} paused</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Failed deliveries</span>
    <b class="ix-metric-val">{{ $failedCount }}</b>
    <span class="ix-metric-sub">{{ $failedCount ? 'check the webhook log' : 'all deliveries healthy' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Rate limit</span>
    <b class="ix-metric-val">120<span class="br-metric-of">/min</span></b>
    <span class="ix-metric-sub">per token</span>
  </div>
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>API access</h3><span class="hint">Authenticate other apps, scripts, or a mobile client against this install.</span></div>
    <span class="ix-tag">v1</span>
  </div>
  <div class="st-api-lines">
    <div><span>Bearer header</span><code class="st-code">Authorization: Bearer YOUR_TOKEN</code></div>
    <div><span>Base URL</span><code class="st-code">{{ url('/api/v1') }}</code></div>
  </div>

  @if(session('plain_token'))
    <div class="alert" style="margin-top:14px">
      <b>New token — copy it now. It will not be shown again.</b><br>
      <code style="word-break:break-all;user-select:all">{{ session('plain_token') }}</code>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.api.tokens.store') }}" class="st-token-form">@csrf
    <div>
      <label class="f" for="api-token-name">Token name</label>
      <input class="in" id="api-token-name" name="name" required placeholder="e.g. Zapier, Mobile app" maxlength="60">
    </div>
    <div>
      <label class="f" for="api-token-scope">Scope</label>
      <select class="in" id="api-token-scope" name="scope">
        <option value="full">Full access (read + write)</option>
        <option value="readonly">Read-only (GET requests only)</option>
      </select>
    </div>
    <div class="st-token-form-action">
      <button class="btn sm">Create token</button>
    </div>
  </form>
  <p class="fhint">Read-only tokens get a 403 on any POST/PUT/PATCH/DELETE call. Deactivating a user revokes their tokens immediately.</p>
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Your tokens</h3><span class="hint">Tokens carry your permissions. Revoke one and any app using it stops immediately.</span></div>
    <span class="ix-tag">{{ $tokenCount }}</span>
  </div>
  @forelse($tokens as $t)
    <div class="st-item">
      <span class="st-item-ic">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V5a2 2 0 0 1 2-2h2"/><path d="M16 3h2a2 2 0 0 1 2 2v2"/><path d="M4 17v2a2 2 0 0 0 2 2h2"/><path d="M16 21h2a2 2 0 0 0 2-2v-2"/><path d="M8 12h8"/></svg>
      </span>
      <span class="st-item-main">
        <b>{{ $t->name }}</b>
        <span>{{ in_array('*', $t->abilities ?? []) ? 'Full access' : 'Read-only' }} · last used {{ $t->last_used_at ? d($t->last_used_at,'j M, Y') : 'never' }} · created {{ d($t->created_at,'j M, Y') }}</span>
      </span>
      <span class="st-item-actions">
        <span class="ix-state {{ in_array('*', $t->abilities ?? []) ? 'is-green' : 'is-blue' }}"><i></i>{{ in_array('*', $t->abilities ?? []) ? 'Full' : 'Read-only' }}</span>
        <form method="POST" action="{{ route('settings.api.tokens.destroy',$t->id) }}" data-confirm="Revoke token {{ $t->name }}? Any app using it will stop working immediately.">@csrf @method('DELETE')
          <button class="btn sec sm" type="submit">Revoke</button>
        </form>
      </span>
    </div>
  @empty
    <div class="ix-empty">
      <span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V5a2 2 0 0 1 2-2h2"/><path d="M16 3h2a2 2 0 0 1 2 2v2"/><path d="M4 17v2a2 2 0 0 0 2 2h2"/><path d="M16 21h2a2 2 0 0 0 2-2v-2"/><path d="M8 12h8"/></svg></span>
      <strong>No tokens yet</strong>
      <p>Create one above to get started.</p>
    </div>
  @endforelse
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Endpoints</h3><span class="hint">JSON in / JSON out. Lists accept <code class="st-code">?q</code>, <code class="st-code">?per_page</code> and type filters; write requests take JSON bodies.</span></div>
    <span class="ix-tag">Reference</span>
  </div>
  <div class="ix-table-scroll">
    <table class="tbl ix-table api-table">
      <thead><tr><th class="ix-c-method">Method</th><th class="ix-c-path">Path</th><th class="ix-c-desc">Description</th></tr></thead>
      <tbody>
        <tr><td class="ix-c-method"><span class="api-method">GET</span></td><td class="ix-c-path"><code class="st-code">/clients</code></td><td class="ix-c-desc">List / search clients</td></tr>
        <tr><td class="ix-c-method"><span class="api-method is-write">POST</span></td><td class="ix-c-path"><code class="st-code">/clients</code></td><td class="ix-c-desc">Create a client</td></tr>
        <tr><td class="ix-c-method"><span class="api-method">GET</span></td><td class="ix-c-path"><code class="st-code">/leads</code></td><td class="ix-c-desc">List leads (filter <code class="st-code">?stage</code>)</td></tr>
        <tr><td class="ix-c-method"><span class="api-method is-write">POST</span></td><td class="ix-c-path"><code class="st-code">/leads/{id}/move</code></td><td class="ix-c-desc">Move a lead's stage</td></tr>
        <tr><td class="ix-c-method"><span class="api-method">GET</span></td><td class="ix-c-path"><code class="st-code">/invoices</code></td><td class="ix-c-desc">List invoices (filter <code class="st-code">?status</code>)</td></tr>
        <tr><td class="ix-c-method"><span class="api-method is-write">POST</span></td><td class="ix-c-path"><code class="st-code">/invoices</code></td><td class="ix-c-desc">Create a GST invoice with <code class="st-code">items[]</code></td></tr>
        <tr><td class="ix-c-method"><span class="api-method is-write">POST</span></td><td class="ix-c-path"><code class="st-code">/invoices/{id}/sent</code></td><td class="ix-c-desc">Mark an invoice sent</td></tr>
        <tr><td class="ix-c-method"><span class="api-method is-write">POST</span></td><td class="ix-c-path"><code class="st-code">/payments</code></td><td class="ix-c-desc">Record a payment</td></tr>
        <tr><td class="ix-c-method"><span class="api-method">GET/POST</span></td><td class="ix-c-path"><code class="st-code">/expenses</code></td><td class="ix-c-desc">List / create expenses</td></tr>
        <tr><td class="ix-c-method"><span class="api-method">GET/POST</span></td><td class="ix-c-path"><code class="st-code">/products</code></td><td class="ix-c-desc">Catalog list / create (active by default)</td></tr>
        <tr><td class="ix-c-method"><span class="api-method is-write">POST</span></td><td class="ix-c-path"><code class="st-code">/proposals</code></td><td class="ix-c-desc">Create a draft proposal with <code class="st-code">items[]</code></td></tr>
        <tr><td class="ix-c-method"><span class="api-method is-write">POST</span></td><td class="ix-c-path"><code class="st-code">/proposals/{id}/send · /convert</code></td><td class="ix-c-desc">Send (signing link) · convert accepted → invoice</td></tr>
        <tr><td class="ix-c-method"><span class="api-method">GET</span></td><td class="ix-c-path"><code class="st-code">/tasks · /projects · /proposals</code></td><td class="ix-c-desc">Lists + detail</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Webhooks</h3><span class="hint">POSTs JSON <code class="st-code">{event, at, data}</code> with <code class="st-code">X-CRM-Signature: sha256=…</code> (HMAC of the body with your secret). Verify before trusting.</span></div>
    <span class="ix-state {{ $failedCount ? 'is-red' : 'is-green' }}"><i></i>{{ $failedCount ? $failedCount.' failed' : 'Healthy' }}</span>
  </div>

  <form method="POST" action="{{ route('settings.api.webhooks.store') }}" class="st-webhook-form">@csrf
    <div class="form-grid is-three">
      <div><label class="f" for="wh-name">Name</label><input class="in" id="wh-name" name="name" required maxlength="120" placeholder="e.g. Zapier"></div>
      <div><label class="f" for="wh-url">URL</label><input class="in" id="wh-url" name="url" type="url" required maxlength="500" placeholder="https://…"></div>
      <div><label class="f" for="wh-secret">Secret</label><input class="in" id="wh-secret" name="secret" maxlength="64" placeholder="optional"></div>
    </div>
    <div class="fsec">Events</div>
    <div class="st-checks is-compact">
      @foreach($events as $v=>$l)
        <label class="st-check">
          <input type="checkbox" name="events[]" value="{{ $v }}">
          <span>{{ $l }}</span>
        </label>
      @endforeach
    </div>
    <div class="st-actions-inline">
      <button class="btn sm">Add webhook</button>
    </div>
  </form>

  <div class="st-divider"></div>

  @forelse($webhooks ?? [] as $w)
    <div class="st-item">
      <span class="st-item-ic">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 16.98h-5.99c-1.1 0-1.95.94-2.48 1.9A4 4 0 0 1 2 17c.01-.7.2-1.4.56-2"/><path d="m6 17 3.13-5.78c.53-.97.1-2.18-.5-3.1a4 4 0 1 1 6.88-4.02"/><path d="m12 6 3.13 5.73C15.66 12.7 16.9 13 18 13a4 4 0 0 1 0 8"/></svg>
      </span>
      <span class="st-item-main">
        <b>{{ $w->name }}</b>
        <span>{{ \Str::limit($w->url,80) }} · {{ implode(', ', array_map(fn($e) => $events[$e] ?? $e, $w->events ?? [])) }}</span>
      </span>
      <span class="st-item-actions">
        <span class="ix-state {{ $w->is_active ? 'is-green' : 'is-grey' }}"><i></i>{{ $w->is_active ? 'Live' : 'Paused' }}</span>
        @if($w->fails ?? 0)<span class="ix-state is-red"><i></i>{{ $w->fails }} failed</span>@endif
        <form method="POST" action="{{ route('settings.api.webhooks.ping',$w) }}">@csrf<button class="btn sec sm">Ping</button></form>
        <a class="btn sec sm" href="{{ route('settings.api.webhooks.deliveries',$w) }}">Log</a>
        <form method="POST" action="{{ route('settings.api.webhooks.toggle',$w) }}">@csrf<button class="btn sec sm">{{ $w->is_active ? 'Pause' : 'Resume' }}</button></form>
        <form method="POST" action="{{ route('settings.api.webhooks.destroy',$w) }}" data-confirm="Remove webhook {{ $w->name }}?">@csrf @method('DELETE')<button class="btn sec sm danger">Delete</button></form>
      </span>
    </div>
  @empty
    <div class="ix-empty">
      <span class="ix-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 16.98h-5.99c-1.1 0-1.95.94-2.48 1.9A4 4 0 0 1 2 17c.01-.7.2-1.4.56-2"/><path d="m6 17 3.13-5.78c.53-.97.1-2.18-.5-3.1a4 4 0 1 1 6.88-4.02"/><path d="m12 6 3.13 5.73C15.66 12.7 16.9 13 18 13a4 4 0 0 1 0 8"/></svg></span>
      <strong>No webhooks</strong>
      <p>Add one above to stream events out.</p>
    </div>
  @endforelse
</div>
@endsection
