@extends('layouts.app')
@section('title','Users')
@section('content')
<div class="card">
  <div class="card-head"><h3>Team ({{ $users->total() }})</h3><a class="btn sm" href="{{ route('settings.users.create') }}">+ New user</a></div>
  <form method="GET" data-ajax-filter data-region="users-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q }}" placeholder="Search name or email…"><select class="in" style="margin:0;flex:1;min-width:120px" name="role"><option value="">All roles</option>@foreach(['admin','manager','staff'] as $rl)<option {{ ($role ?? '')===$rl?'selected':'' }}>{{ $rl }}</option>@endforeach</select><select class="in" style="margin:0;flex:1;min-width:120px" name="status"><option value="">Any status</option><option value="active" {{ ($status ?? '')==='active'?'selected':'' }}>Active</option><option value="disabled" {{ ($status ?? '')==='disabled'?'selected':'' }}>Disabled</option></select><button class="btn sec sm">Search</button></form>
  <div data-ajax-region="users-list"><div class="tbl-scroll"><table class="tbl"><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr>
  @forelse($users as $u)<tr>
    <td><span class="who"><span class="av sm">{{ strtoupper(substr($u->name,0,1)) }}</span><b>{{ $u->name }}</b></span></td><td>{{ $u->email }}</td>
    <td><span class="badge {{ $u->role==='admin' ? 'b-blue' : ($u->role==='manager' ? 'b-amber' : 'b-grey') }}">{{ $u->role }}</span></td>
    <td><span class="badge {{ $u->is_active ? 'b-green' : 'b-grey' }}">{{ $u->is_active ? 'active' : 'disabled' }}</span></td>
    <td><div class="row-actions"><a class="icon-btn" href="{{ route('settings.users.edit',$u) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Edit</a>
      @if($u->id !== auth()->id())
      <form method="POST" action="{{ route('settings.users.destroy',$u) }}" style="display:inline" data-confirm="Delete {{ $u->email }}?">@csrf @method('DELETE')<button class="icon-btn danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>Delete</button></form>
      @endif</div></td>
  </tr>@empty<tr><td colspan="5"><div class="empty">No users found.</div></td></tr>@endforelse</table></div>
  {{ $users->links() }}</div>
</div>
@endsection
