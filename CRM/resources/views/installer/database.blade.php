@extends('installer.layout')
@section('step','2')
@section('content')
<h1>2 — Database</h1>
<p class="hint" style="margin:-4px 0 0">Pick where the CRM keeps its records. This cannot be changed later without reinstalling.</p>

<style>
  .dbpick{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:16px}
  @media(max-width:640px){.dbpick{grid-template-columns:minmax(0,1fr)}}
  .dbcard{border:1px solid var(--dc-line,var(--line));border-radius:16px;padding:16px 16px 4px;background:var(--dc-panel,var(--surface));min-width:0}
  .dbcard h2{font-size:15px;margin:0;letter-spacing:-.01em}
  .dbcard>h2 .dbrec{display:inline-block;font-size:10px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:2px 7px;border-radius:99px;background:var(--dc-soft,var(--bg));color:var(--muted);vertical-align:2px;margin-left:5px}
  .dbcard>p.hint{font-size:12px;line-height:1.5;margin:5px 0 4px}
  .dbcard label{display:block;font-size:12px;font-weight:600;margin:10px 0 4px}
  .dbcard input{width:100%;box-sizing:border-box;font-size:16px;min-height:40px}
  .dbcard .btn{width:100%;justify-content:center;margin:14px 0}
  .dbcard .err{font-size:12px;margin:8px 0 0}
</style>

<div class="dbpick">
  <form class="dbcard" method="POST" action="{{ route('installer.database.store') }}">
    @csrf
    <input type="hidden" name="DB_CONNECTION" value="mysql">
    <h2>MySQL / MariaDB <span class="dbrec">Most hosts</span></h2>
    <p class="hint">Use a database already created in your hosting panel. Best when several people use the CRM at once.</p>
    @if(! $mysqlReady)<div class="err">PHP on this server has no pdo_mysql extension.</div>@endif
    <label for="db-host">Host</label>
    <input id="db-host" name="DB_HOST" value="{{ old('DB_HOST','127.0.0.1') }}" required autocomplete="off" spellcheck="false" placeholder="127.0.0.1">
    <label for="db-port">Port</label>
    <input id="db-port" name="DB_PORT" type="number" value="{{ old('DB_PORT','3306') }}" required autocomplete="off" placeholder="3306">
    <label for="db-name">Database name</label>
    <input id="db-name" name="DB_DATABASE" value="{{ old('DB_DATABASE','crm') }}" required autocomplete="off" spellcheck="false" placeholder="crm">
    <label for="db-user">Username</label>
    <input id="db-user" name="DB_USERNAME" value="{{ old('DB_USERNAME','') }}" required autocomplete="username" spellcheck="false">
    <label for="db-pass">Password</label>
    <input id="db-pass" name="DB_PASSWORD" type="password" value="{{ old('DB_PASSWORD','') }}" autocomplete="new-password">
    <button class="btn" type="submit" @disabled(! $mysqlReady)>Test &amp; migrate →</button>
  </form>

  <form class="dbcard" method="POST" action="{{ route('installer.database.store') }}">
    @csrf
    <input type="hidden" name="DB_CONNECTION" value="sqlite">
    <h2>SQLite <span class="dbrec">No server</span></h2>
    <p class="hint">Everything lives in one file inside the app folder. Nothing to create in cPanel, ideal for a small team or a local install.</p>
    @if(! $sqliteReady)<div class="err">PHP on this server has no pdo_sqlite extension.</div>@endif
    <label for="db-file">Database file</label>
    <input id="db-file" name="DB_DATABASE" value="{{ old('DB_DATABASE', $sqliteDefault) }}" autocomplete="off" spellcheck="false" placeholder="{{ $sqliteDefault }}">
    <p class="hint">Relative to the app folder, or an absolute path. The file is created if it does not exist.</p>
    <button class="btn" type="submit" @disabled(! $sqliteReady)>Create &amp; migrate →</button>
  </form>
</div>

<div class="btnrow"><a class="btn sec" href="{{ route('installer.requirements') }}">← Back to requirements</a></div>
@endsection
