@extends('layouts.app')
@section('title',$doc->number)
@section('content')
<div class="page-head"><h1>{{ $doc->number }}</h1><div class="crumb">{{ $doc->title }} · {{ \Modules\Expiry\ExpiryDocument::categories()[$doc->category] ?? $doc->category }} · <span class="badge {{ $state==='valid' ? 'b-green' : ($state==='expiring' ? 'b-amber' : ($state==='expired' ? 'b-red' : 'b-grey')) }}">{{ ucfirst($state) }}</span></div></div>
<div class="noprint" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
  <a class="btn sm sec" href="{{ route('expiry.edit',$doc) }}">Edit</a>
  <form method="POST" action="{{ route('expiry.renew',$doc) }}" style="display:inline-flex;gap:6px;align-items:center" data-confirm="Renew {{ $doc->number }}?">@csrf<select class="in" name="years" style="margin:0;width:auto">@for($y=1;$y<=5;$y++)<option value="{{ $y }}">{{ $y }} yr</option>@endfor</select><button class="btn sm">Renew</button></form>
  <form method="POST" action="{{ route('expiry.destroy',$doc) }}" style="display:inline" data-confirm="Delete {{ $doc->number }}?">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form>
</div>

<div class="card">
<div class="cols-2">
<div>
  <div class="kv"><span class="k">Ref no</span><span>{{ $doc->ref_no ?: '—' }}</span></div>
  <div class="kv"><span class="k">Authority</span><span>{{ $doc->authority ?: '—' }}</span></div>
  <div class="kv"><span class="k">Validity</span><span>{{ d($doc->valid_from) ?: '—' }} → {{ d($doc->valid_to) ?: 'open-ended' }}</span></div>
</div>
<div>
  <div class="kv"><span class="k">Alert window</span><span>{{ $doc->remind_days }} days before</span></div>
  <div class="kv"><span class="k">Notes</span><span style="white-space:pre-line">{{ $doc->notes ?: '—' }}</span></div>
</div>
</div>
</div>
@endsection
