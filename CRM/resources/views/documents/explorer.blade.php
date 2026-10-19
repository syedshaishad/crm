@php
  $cname = fn ($c) => $c ? ($c->company ?: $c->name) : 'Unknown client';
  $loc = function ($dd) use ($cname) {
    $link = $dd->linkable;
    if ($link instanceof Modules\Projects\Project) return $cname($link->client).' / '.$link->name;
    if ($link instanceof Modules\Clients\Client) return $cname($link);
    return 'General';
  };
  $flat = [];
  foreach ($tree as $node) {
    foreach ($node['direct'] as $dd) $flat[] = $dd;
    foreach ($node['projects'] as $pg) foreach ($pg['items'] as $dd) $flat[] = $dd;
  }
  foreach ($general as $dd) $flat[] = $dd;
@endphp
<div class="fm-layout">
  <div class="card fm-side noprint">
    <form method="GET" action="{{ route('documents.index') }}" style="display:flex;gap:6px;margin-bottom:10px"><input class="in" style="margin:0" name="q" value="{{ $q }}" placeholder="Search all files…" autocomplete="off"><button class="btn sm sec">Go</button></form>
    <a class="fm-link {{ ($client_id ?? '')===''&&($project_id ?? '')===''?'active':'' }}" href="{{ route('documents.index', array_filter(['q'=>$q])) }}"><span class="fd-ic sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span><span class="t">All files</span><span class="n">{{ $totalCount }}</span></a>
    <a class="fm-link {{ ($client_id ?? '')==='general'?'active':'' }}" href="{{ route('documents.index', array_filter(['client_id'=>'general','q'=>$q])) }}"><span class="fd-ic sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span><span class="t">General</span><span class="n">{{ $generalCount }}</span></a>
    @foreach($clients as $c)
    @php $isActive = (string)($client_id ?? '')===(string)$c->id; @endphp
    <a class="fm-link {{ $isActive?'active':'' }}" href="{{ route('documents.index', array_filter(['client_id'=>$c->id,'q'=>$q])) }}"><span class="fd-ic sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span><span class="t">{{ $cname($c) }}</span><span class="n">{{ $clientTotals[$c->id] ?? 0 }}</span></a>
    @if($isActive)
      <div class="fm-sub">
      @foreach($c->projects as $p)
      <a class="fm-link sub {{ (string)($project_id ?? '')===(string)$p->id?'active':'' }}" href="{{ route('documents.index', array_filter(['client_id'=>$c->id,'project_id'=>$p->id,'q'=>$q])) }}"><span class="fd-ic xs"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span><span class="t">{{ $p->name }}</span><span class="n">{{ $projCounts[$p->id] ?? 0 }}</span></a>
      @endforeach
      </div>
    @endif
    @endforeach
  </div>

  <div class="card">
    <div class="fm-toolbar noprint">
      <div class="crumbs"><a href="{{ route('documents.index', array_filter(['q'=>$q])) }}">All files</a>
        @if(($client_id ?? '')==='general')<span>/</span><b>General</b>
        @elseif($activeClient)<span>/</span><a href="{{ route('documents.index', array_filter(['client_id'=>$activeClient->id,'q'=>$q])) }}">{{ $cname($activeClient) }}</a>
          @if(($project_id ?? '')!=='')<span>/</span><b>{{ $activeClient->projects->firstWhere('id',(int)$project_id)?->name ?? 'Project' }}</b>@endif
        @endif
        @if($q !== '')<span class="hint">— search “{{ $q }}” <a href="{{ route('documents.index', array_filter(['client_id'=>$client_id,'project_id'=>$project_id])) }}">✕</a></span>@endif
      </div>
      @php $here = ($project_id ?? '')!=='' ? 'p'.$project_id : ((($client_id ?? '')!=='' && ($client_id ?? '')!=='general') ? 'c'.$client_id : ''); @endphp
      <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="filed_under" value="{{ $here }}"><label class="btn sm fm-upload">+ Upload here<input type="file" name="file" required onchange="this.form.submit()"></label></form>
    </div>
    <div class="hint" style="margin-bottom:12px">{{ $totalCount }} file{{ $totalCount === 1 ? '' : 's' }} · {{ fmt_size($totalSize) }}</div>
    <div class="tbl-scroll roomy"><table class="tbl"><tr><th>Name</th><th>Location</th><th>Size</th><th>Modified</th><th class="noprint"></th></tr>
    @forelse($flat as $dd)
    @php $ext = file_ext($dd->name); @endphp
    <tr><td><span class="file-cell"><span class="file-ic xs {{ file_icon_class($ext) }}">{{ file_icon_label($ext) }}</span><a href="{{ route('documents.download',$dd) }}" title="Download {{ $dd->name }}">{{ $dd->name }}</a></span></td>
    <td><span class="hint">{{ $loc($dd) }}</span></td>
    <td class="nowrap">{{ fmt_size($dd->size) }}</td><td class="nowrap">{{ d($dd->created_at) }}</td>
     <td class="noprint"><div class="row-actions"><a class="icon-btn" href="{{ route('documents.download',$dd) }}" title="Download {{ $dd->name }}" aria-label="Download {{ $dd->name }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v3h16v-3"/></svg></a>
     <form method="POST" action="{{ route('documents.destroy',$dd) }}" style="display:inline" data-confirm="Delete document?">@csrf @method('DELETE')<button class="icon-btn danger" title="Delete {{ $dd->name }}" aria-label="Delete {{ $dd->name }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form></div></td></tr>
    @empty<tr><td colspan="5"><div class="empty">This folder is empty — use Upload here to add files.</div></td></tr>@endforelse</table></div>
    <div style="display:flex;justify-content:center">{{ $docs->links() }}</div>
  </div>
</div>
