@extends('layouts.app')
@section('title','API')
@section('content')
<div class="card">
  <div class="card-head"><h3>API access</h3><span class="badge b-blue">v1</span></div>
  <p class="hint">Authenticate other apps, scripts, or a mobile client against this install.<br>
  Send the token as a bearer header: <code>Authorization: Bearer YOUR_TOKEN</code>. Base URL: <code>{{ url('/api/v1') }}</code>.</p>

  @if(session('plain_token'))
  <div class="alert" style="margin-top:14px">
    <b>New token — copy it now. It will not be shown again.</b><br>
    <code style="word-break:break-all;user-select:all">{{ session('plain_token') }}</code>
  </div>
  @endif

  <form method="POST" action="{{ route('settings.api.tokens.store') }}" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    @csrf
    <input class="in" name="name" required placeholder="Token name (e.g. Zapier, Mobile app)" style="margin:0;flex:2;min-width:180px" maxlength="60">
    <select class="in" name="scope" style="margin:0;flex:1;min-width:150px" title="Token scope">
      <option value="full">Full access (read + write)</option>
      <option value="readonly">Read-only (GET requests only)</option>
    </select>
    <button class="btn sm">Create token</button>
  </form>
  <p class="hint">Read-only tokens get a 403 on any POST/PUT/PATCH/DELETE call. 120 requests/minute per token. Deactivating a user revokes their tokens immediately.</p>
</div>

<div class="card" style="margin-top:22px">
  <div class="card-head"><h3>Your tokens</h3></div>
  @forelse($tokens as $t)
  <div class="mi-row" style="border-color:var(--line)">
    <span class="mi-ic" style="background:var(--surface-2)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="color:var(--ink2)"><path d="M4 7V5a2 2 0 0 1 2-2h2"/><path d="M16 3h2a2 2 0 0 1 2 2v2"/><path d="M4 17v2a2 2 0 0 0 2 2h2"/><path d="M16 21h2a2 2 0 0 0 2-2v-2"/><path d="M8 12h8"/></svg></span>
    <span style="flex:1"><b>{{ $t->name }}</b> <span class="badge {{ in_array('*', $t->abilities ?? []) ? 'b-green' : 'b-blue' }}">{{ in_array('*', $t->abilities ?? []) ? 'full' : 'read-only' }}</span><span>last used {{ $t->last_used_at ? d($t->last_used_at,'j M, Y') : 'never' }} · created {{ d($t->created_at,'j M, Y') }}</span></span>
    <form method="POST" action="{{ route('settings.api.tokens.destroy',$t->id) }}" style="display:inline" data-confirm="Revoke token {{ $t->name }}? Any app using it will stop working immediately.">@csrf @method('DELETE')<button class="icon-btn danger">Revoke</button></form>
  </div>
  @empty
  <div class="empty">No tokens yet. Create one above to get started.</div>
  @endforelse
</div>

<div class="card" style="margin-top:22px">
  <div class="card-head"><h3>Endpoints</h3></div>
  <p class="hint">JSON in / JSON out. Lists accept <code>?q</code>, <code>?per_page</code> and type filters; write requests take JSON bodies.</p>
  <div class="tbl-scroll"><table class="tbl">
    <tr><th>Method</th><th>Path</th><th>Description</th></tr>
    <tr><td>GET</td><td><code>/clients</code></td><td>List / search clients</td></tr>
    <tr><td>POST</td><td><code>/clients</code></td><td>Create a client</td></tr>
    <tr><td>GET</td><td><code>/leads</code></td><td>List leads (filter <code>?stage</code>)</td></tr>
    <tr><td>POST</td><td><code>/leads/{id}/move</code></td><td>Move a lead's stage</td></tr>
    <tr><td>GET</td><td><code>/invoices</code></td><td>List invoices (filter <code>?status</code>)</td></tr>
    <tr><td>POST</td><td><code>/invoices</code></td><td>Create a GST invoice with <code>items[]</code></td></tr>
    <tr><td>POST</td><td><code>/invoices/{id}/sent</code></td><td>Mark an invoice sent</td></tr>
    <tr><td>POST</td><td><code>/payments</code></td><td>Record a payment</td></tr>
    <tr><td>GET/POST</td><td><code>/expenses</code></td><td>List / create expenses</td></tr>
    <tr><td>GET/POST</td><td><code>/products</code></td><td>Catalog list / create (active by default)</td></tr>
    <tr><td>POST</td><td><code>/proposals</code></td><td>Create a draft proposal with <code>items[]</code></td></tr>
    <tr><td>POST</td><td><code>/proposals/{id}/send</code> · <code>/convert</code></td><td>Send (signing link) · convert accepted → invoice</td></tr>
    <tr><td>GET</td><td><code>/tasks</code> · <code>/projects</code> · <code>/proposals</code></td><td>Lists + detail</td></tr>
  </table></div>
</div>

<div class="card" style="margin-top:22px">
  <div class="card-head"><h3>Webhooks</h3><span class="hint">push events to Zapier / your app</span></div>
  <p class="hint">POSTs JSON <code>{event, at, data}</code> with <code>X-CRM-Signature: sha256=…</code> (HMAC of the body with your secret). Verify before trusting.</p>
  <form method="POST" action="{{ route('settings.api.webhooks.store') }}" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:end">@csrf
  <div><label class="f">Name</label><input class="in" name="name" required maxlength="120" style="margin:0" placeholder="e.g. Zapier"></div>
  <div style="flex:2;min-width:200px"><label class="f">URL</label><input class="in" name="url" type="url" required maxlength="500" style="margin:0" placeholder="https://…"></div>
  <div><label class="f">Secret</label><input class="in" name="secret" maxlength="64" style="margin:0" placeholder="optional"></div>
  <div><label class="f">Events</label><div style="display:flex;gap:10px;flex-wrap:wrap">@foreach($events as $v=>$l)<label class="hint" style="display:flex;gap:4px;align-items:center"><input type="checkbox" name="events[]" value="{{ $v }}" style="width:auto;margin:0"> {{ $l }}</label>@endforeach</div></div>
  <div><button class="btn sm">Add webhook</button></div></form>
  @forelse($webhooks ?? [] as $w)
  <div class="mi-row" style="border-color:var(--line)">
    <span style="flex:1"><b>{{ $w->name }}</b> <span class="badge {{ $w->is_active ? 'b-green' : 'b-grey' }}">{{ $w->is_active ? 'live' : 'paused' }}</span>@if($w->fails ?? 0)<span class="badge b-red">{{ $w->fails }} failed</span>@endif<span class="hint" style="overflow-wrap:anywhere">{{ \Str::limit($w->url,70) }} · {{ implode(', ', array_map(fn($e) => $events[$e] ?? $e, $w->events ?? [])) }}</span></span>
    <form method="POST" action="{{ route('settings.api.webhooks.ping',$w) }}" style="display:inline">@csrf<button class="icon-btn">Ping</button></form>
    <a class="icon-btn" href="{{ route('settings.api.webhooks.deliveries',$w) }}">Log</a>
    <form method="POST" action="{{ route('settings.api.webhooks.toggle',$w) }}" style="display:inline">@csrf<button class="icon-btn">{{ $w->is_active ? 'Pause' : 'Resume' }}</button></form>
    <form method="POST" action="{{ route('settings.api.webhooks.destroy',$w) }}" style="display:inline" data-confirm="Remove webhook {{ $w->name }}?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form>
  </div>
  @empty
  <div class="empty">No webhooks — add one above to stream events out.</div>
  @endforelse
</div>
@endsection
