@extends('portal.layout')
@section('title','Documents')
@section('content')
<div class="page-head"><h1>Documents</h1><div class="crumb">Files shared with your account. Sign any document your team has sent for signature.</div></div>
<div class="card">
@if($documents->isEmpty())
  <p class="empty">No documents have been shared with your account yet.</p>
@else
  <div class="tbl-scroll roomy"><table class="tbl">
    <tr><th>Name</th><th>Size</th><th>Status</th><th class="noprint"></th></tr>
    @foreach($documents as $doc)
    <tr>
      <td><b>{{ $doc->name }}</b><span class="hint">{{ $doc->created_at->format('j M Y') }}</span></td>
      <td class="nowrap">{{ fmt_size($doc->size) }}</td>
      <td>
        @if($doc->signed_at)
          <span class="badge b-green">Signed by {{ $doc->signed_by }}</span>
        @elseif($doc->sign_token)
          <span class="badge b-amber">Signature requested</span>
        @else
          <span class="badge b-grey">Published</span>
        @endif
      </td>
      <td class="noprint" style="text-align:right;white-space:nowrap">
        <a class="btn sm sec" href="{{ route('documents.download', $doc) }}">Download</a>
        @if($doc->sign_token && ! $doc->signed_at)
        <a class="btn sm" href="{{ route('documents.sign', $doc->sign_token) }}">Sign →</a>
        @endif
      </td>
    </tr>
    @endforeach
  </table></div>
@endif
</div>
@endsection
