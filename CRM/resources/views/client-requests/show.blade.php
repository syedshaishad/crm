@extends('layouts.app')
@section('title',$clientRequest->subject)
@section('content')
@php $req = $clientRequest; @endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v11H8l-4 4z"/></svg></span>
    <div>
      <h1>{{ $req->subject }}</h1>
      <div class="crumb">{{ $req->client ? ($req->client->company ?: $req->client->name) : 'Client' }} · #{{ $req->id }} · {{ ucfirst(str_replace('_',' ',$req->status)) }} · {{ ucfirst($req->priority) }} priority</div>
    </div>
  </div>
  <div class="ix-head-actions noprint"><a class="btn sm sec" href="{{ route('client-requests.index') }}">← All requests</a></div>
</div>

<div class="card" style="margin-bottom:14px">
  <div class="card-head"><div><h2>Conversation</h2><span class="hint">Replies are visible in the client portal</span></div></div>
  @foreach(($req->messages ?: []) as $m)
  <div class="mi-row" style="align-items:flex-start">
    <span class="av sm">{{ strtoupper(substr($m['author'] ?? 'T', 0, 1)) }}</span>
    <div style="flex:1;min-width:0">
      <b>{{ $m['author'] ?? 'Team' }}</b>
      <span class="hint">· {{ \Carbon\Carbon::parse($m['at'])->format('j M, H:i') }} · {{ ucfirst($m['from'] ?? 'client') }}</span>
      <div style="white-space:pre-wrap">{{ $m['body'] }}</div>
    </div>
  </div>
  @endforeach

  <form method="POST" action="{{ route('client-requests.reply', $req) }}" style="margin-top:14px">@csrf
    <label for="staff-reply">Reply to client</label>
    <textarea id="staff-reply" name="body" rows="3" required placeholder="Write a reply…"></textarea>
    <div class="form-actions"><button class="btn" type="submit">Send reply</button></div>
  </form>
</div>

<div class="card">
  <div class="card-head"><div><h2>Status</h2><span class="hint">Also updates what the client sees</span></div></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    @foreach(['open','in_progress','done','closed'] as $s)
    <form method="POST" action="{{ route('client-requests.status', $req) }}">@csrf<input type="hidden" name="status" value="{{ $s }}">
      <button class="btn sm {{ $s === $req->status ? '' : 'sec' }}">{{ ucfirst(str_replace('_',' ',$s)) }}</button>
    </form>
    @endforeach
  </div>
</div>
@endsection
