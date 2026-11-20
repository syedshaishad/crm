@extends('layouts.app')
@section('title','Client requests')
@section('content')
@php
  $statuses = ['open', 'in_progress', 'done', 'closed', 'all'];
  $urlFor = fn ($s) => route('client-requests.index', $s === 'open' ? [] : ['status' => $s]);
@endphp
<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v11H8l-4 4z"/></svg></span>
    <div><h1>Client requests</h1><div class="crumb">{{ $all->where('status','open')->count() }} open · {{ $all->count() }} total · shared with the client portal</div></div>
  </div>
</div>
<div class="card ix-card">
  <div class="card-head ix-card-head"><div><h2>Requests</h2><span class="hint">Newest first</span></div></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
    @foreach($statuses as $s)
    <a class="ix-chip {{ $status === $s ? 'on' : '' }}" href="{{ $urlFor($s) }}">{{ ucfirst(str_replace('_',' ',$s)) }}</a>
    @endforeach
  </div>
  @if($rows->isEmpty())
    <p class="empty">No requests in this view.</p>
  @else
  <div class="tbl-scroll roomy">
    <table class="tbl">
      <thead><tr><th scope="col">Request</th><th scope="col">Client</th><th scope="col">Priority</th><th scope="col">Status</th><th scope="col">Updated</th><th scope="col"></th></tr></thead>
      <tbody>
        @foreach($rows as $req)
        <tr>
          <td><b>{{ $req->subject }}</b><span class="hint">#{{ $req->id }}</span></td>
          <td>{{ $req->client ? ($req->client->company ?: $req->client->name) : '—' }}</td>
          <td>{{ ucfirst($req->priority) }}</td>
          <td><span class="badge b-{{ $req->status === 'done' ? 'green' : ($req->status === 'open' ? 'grey' : 'blue') }}">{{ ucfirst(str_replace('_',' ',$req->status)) }}</span></td>
          <td class="nowrap">{{ $req->updated_at->format('j M Y') }}</td>
          <td><a class="btn sm sec" href="{{ route('client-requests.show', $req) }}">Open →</a></td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="pg-rows">{{ $rows->links() }}</div>
  @endif
</div>
@endsection
