@extends('layouts.app')
@section('title','Notifications')
@section('content')
<div data-ajax-region="notifications">
<div class="card">
  <div class="card-head">
    <h3>Notifications</h3>
    @if($notes->isNotEmpty() && $notes->contains(fn ($n) => is_null($n->read_at)))
    <form method="POST" data-notes-read data-notes-all action="{{ route('notifications.readAll') }}">@csrf<button class="btn sec sm">Mark all as read</button></form>
    @endif
  </div>
  @forelse($notes as $n)
  @php $d = $n->data; @endphp
  <div class="mi-row" style="border-color:var(--line)">
    <span class="mi-ic" style="background:var(--surface-2)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="color:{{ is_null($n->read_at) ? 'var(--primary)' : 'var(--ink2)' }}"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
    </span>
    <span style="flex:1;min-width:0">
      <div class="note-top"><b>{{ $d['title'] ?? 'Notification' }}@if(is_null($n->read_at)) <span class="badge b-blue" style="margin-left:6px">new</span>@endif</b><time class="hint">{{ d($n->created_at, 'j M, h:i A') }}</time></div>
      <span>{{ $d['body'] ?? '' }}</span>
    </span>
    <span style="display:flex;gap:6px;align-items:center">
      @if(!empty($d['url']))<a class="icon-btn" href="{{ $d['url'] }}">Open</a>@endif
      @if(is_null($n->read_at))
      <form method="POST" data-notes-read action="{{ route('notifications.read',$n->id) }}">@csrf<button class="icon-btn" title="Mark as read">✓</button></form>
      @endif
    </span>
  </div>
  @empty
  <div class="empty">You're all caught up.<br><br><a class="btn sm" href="{{ route('dashboard') }}">Back to dashboard</a></div>
  @endforelse
</div>
{{ $notes->links() }}
</div>
@endsection
