@extends('layouts.app')
@section('title',$doc->number)
@section('content')
<x-page-header :title="$doc->number" :crumb="$doc->title.' · '.(\Modules\Expiry\ExpiryDocument::categories()[$doc->category] ?? $doc->category)" icon='<rect x="4" y="4" width="16" height="16" rx="2"/><path d="m8.5 12.5 2.5 2.5 5-5.5"/>' back="route('expiry.index')">
  
</x-page-header>
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
  <div class="kv"><span class="k">Validity</span><span>{{ d($doc->valid_from, 'j M Y') ?: '—' }} → {{ d($doc->valid_to, 'j M Y') ?: 'open-ended' }}</span></div>
</div>
<div>
  <div class="kv"><span class="k">Alert window</span><span>{{ $doc->remind_days }} days before</span></div>
  <div class="kv"><span class="k">Notes</span><span style="white-space:pre-line">{{ $doc->notes ?: '—' }}</span></div>
</div>
</div>
</div>
@endsection
