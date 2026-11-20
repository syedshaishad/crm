@extends('portal.layout')
@section('title','Requests')
@section('content')
<div class="page-head"><h1>Requests</h1><div class="crumb">A shared thread with our team — ask a question, request a change or report an issue.</div></div>

<div class="card" style="margin-bottom:14px">
  <div class="card-head"><div><h2>New request</h2><span class="hint">We reply in the thread below.</span></div></div>
  <form method="POST" action="{{ route('portal.requests.store') }}">@csrf
    <label for="req-subject">Subject</label>
    <input id="req-subject" name="subject" maxlength="180" required autocomplete="off" placeholder="What do you need?">
    <label for="req-priority">Priority</label>
    <select id="req-priority" name="priority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option></select>
    <label for="req-body">Message</label>
    <textarea id="req-body" name="body" rows="4" required placeholder="A few lines of context helps us help you faster."></textarea>
    <div class="form-actions"><button class="btn" type="submit">Send request</button></div>
  </form>
</div>

@forelse($requests as $req)
<div class="card" style="margin-bottom:12px">
  <div class="card-head">
    <div>
      <h2>{{ $req->subject }}</h2>
      <span class="hint">#{{ $req->id }} · {{ $req->created_at->format('j M Y') }} · {{ ucfirst($req->status) }} · {{ ucfirst($req->priority) }} priority</span>
    </div>
  </div>
  @foreach(($req->messages ?: []) as $m)
  <div class="mi-row" style="align-items:flex-start">
    <span class="av sm">{{ strtoupper(substr($m['author'] ?? 'T', 0, 1)) }}</span>
    <div style="flex:1;min-width:0">
      <b>{{ $m['author'] ?? 'Team' }}</b>
      <span class="hint">· {{ \Carbon\Carbon::parse($m['at'])->format('j M, H:i') }}</span>
      <div style="white-space:pre-wrap">{{ $m['body'] }}</div>
    </div>
  </div>
  @endforeach
  @if($req->status === 'open')
  <form method="POST" action="{{ route('portal.requests.reply', $req) }}" style="margin-top:10px">@csrf
    <label for="reply-{{ $req->id }}">Reply</label>
    <textarea id="reply-{{ $req->id }}" name="body" rows="2" required placeholder="Add anything else…"></textarea>
    <div class="form-actions"><button class="btn sm" type="submit">Send reply</button></div>
  </form>
  @endif
</div>
@empty
<p class="empty">No requests yet. Send one above and we will pick it up from here.</p>
@endforelse
{{ $requests->links() }}
@endsection
