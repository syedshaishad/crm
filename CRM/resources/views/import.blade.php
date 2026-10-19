@extends('layouts.app')
@section('title','Import & Export')
@section('content')
<div class="card">
  <div class="card-head"><h3>Import from CSV</h3></div>
  <p class="hint">Upload a CSV with a header row. Supported types: <b>Clients</b> and <b>Leads</b>.<br>
  Columns are matched by name (any order, case-insensitive). Clients with an existing email are skipped, not overwritten.</p>

  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-top:8px">
    <div class="card sm">
      <div class="card-head"><h3>Clients</h3></div>
      <code class="hint" style="white-space:pre-line">name*, company, email, phone,
address, gstin, state, status</code>
      <div style="margin-top:10px"><a class="btn sec sm" href="{{ route('export.csv','clients') }}">Download a sample (current clients)</a></div>
    </div>
    <div class="card sm">
      <div class="card-head"><h3>Leads</h3></div>
      <code class="hint" style="white-space:pre-line">name*, company, email, phone,
city, state, source, stage, value</code>
      <div style="margin-top:10px"><a class="btn sec sm" href="{{ route('export.csv','leads') }}">Download a sample (current leads)</a></div>
    </div>
  </div>

  <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data" style="margin-top:18px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    @csrf
    <select class="in" name="type" style="margin:0;min-width:140px">
      @foreach($types as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach
    </select>
    <input class="in" type="file" name="file" accept=".csv,text/csv" required style="margin:0;flex:1;min-width:200px">
    <button class="btn sm">Import</button>
  </form>

  @if(session('import_result'))
  @php $r = session('import_result'); @endphp
  <div class="alert" style="margin-top:16px">
    {{ $r['message'] }}
    @if($r['first_error'])<br><span class="hint">First issue: {{ $r['first_error'] }}</span>@endif
  </div>
  @endif
</div>

<div class="card" style="margin-top:22px">
  <div class="card-head"><h3>Export to CSV</h3></div>
  <p class="hint">Download any list as CSV. Exports honor the current data (filters not applied — they export everything).</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
    @foreach(['clients','leads','invoices','payments','expenses','tasks','projects','proposals'] as $t)
      <a class="btn sec sm" href="{{ route('export.csv',$t) }}">{{ ucfirst($t) }} ↓</a>
    @endforeach
  </div>
  @if(!auth()->user()->isAdmin())<p class="hint" style="margin-top:10px">Client export is admin-only.</p>@endif
</div>

<div class="card" style="margin-top:22px">
  <div class="card-head"><h3>Live sync — Sheets & Zoho</h3><form method="POST" action="{{ route('sync.rotate') }}" data-confirm="Rotate the feed token? Existing sheet links stop working.">@csrf<button class="btn sec sm">New token</button></form></div>
  <p class="hint">Each list below is a live CSV URL. Paste one into Google Sheets with <code>=IMPORTDATA("URL")</code> and it refreshes itself — or import it into Zoho Books/CRM. The token is the password: keep it secret, rotate anytime. Client directory is excluded from feeds.</p>
  @php $feedToken = setting('sync.feed_token'); @endphp
  @if($feedToken)
  <div class="tbl-scroll"><table class="tbl"><tr><th>Feed</th><th>URL</th><th>Sheets formula</th></tr>
  @foreach(\App\Http\Controllers\ImportExportController::feedTypes() as $t)
  @php $u = route('sync.feed', ['token' => $feedToken, 'type' => $t]); @endphp
  <tr><td><b>{{ ucfirst($t) }}</b></td><td><code style="overflow-wrap:anywhere">{{ $u }}</code></td><td><button class="btn sec sm" onclick="navigator.clipboard.writeText('=IMPORTDATA(&quot;{{ $u }}&quot;)');this.textContent='Copied!';setTimeout(()=>this.textContent='Copy formula',1500)">Copy formula</button></td></tr>
  @endforeach</table></div>
  @else
  <p class="hint">No token yet — hit <b>New token</b> above to switch feeds on.</p>
  @endif
</div>
@endsection
