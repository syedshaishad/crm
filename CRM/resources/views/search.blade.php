@extends('layouts.app')
@section('title','Search')
@section('content')
<div class="card">
  <form method="GET" action="{{ route('search') }}" style="display:flex;gap:8px;flex-wrap:wrap">
    <input class="in" style="margin:0;flex:1;min-width:200px" name="q" value="{{ $q }}" placeholder="Search clients, projects, tasks, invoices, receipts, expenses, files…" autocomplete="off" autofocus>
    <button class="btn">Search</button>
  </form>
  @if($q === '')
    <p class="fhint" style="margin:12px 0 0">Type at least 2 characters. Tip: press <b>/</b> anywhere to jump here.</p>
  @elseif(mb_strlen($q) < 2)
    <p class="fhint" style="margin:12px 0 0">Type at least 2 characters.</p>
  @elseif(empty($groups))
    <div class="empty">No matches for “{{ $q }}”.</div>
  @endif
</div>
@foreach($groups as $g)
<div class="card">
  <div class="card-head"><h3>{{ $g['label'] }} ({{ count($g['items']) }})</h3></div>
  @foreach($g['items'] as $it)
  <div class="cat-row"><div class="top"><b><a href="{{ $it['url'] }}">{{ $it['title'] }}</a></b>@if(!empty($it['sub']))<span class="hint">{{ $it['sub'] }}</span>@endif</div></div>
  @endforeach
</div>
@endforeach
@endsection
