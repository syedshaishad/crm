@extends('layouts.app')
@section('title',$user->exists ? 'Edit user' : 'New user')
@section('content')
<div class="card" style="max-width:640px"><h3>{{ $user->exists ? 'Edit user' : 'New user' }}</h3>
<form method="POST" action="{{ $user->exists ? route('settings.users.update',$user) : route('settings.users.store') }}">@csrf @if($user->exists)@method('PUT')@endif
<label class="f">Name</label><input class="in" name="name" value="{{ old('name',$user->name) }}" required autofocus autocomplete="off">
<label class="f">Email</label><input class="in" type="email" name="email" value="{{ old('email',$user->email) }}" required autocomplete="off">
<label class="f">Password {{ $user->exists ? '(leave blank to keep)' : '' }}</label><input class="in" type="password" name="password" {{ $user->exists ? '' : 'required' }}>
<label class="f">Confirm password</label><input class="in" type="password" name="password_confirmation">
<label class="f">Role</label><select class="in" name="role" id="role-select">@foreach(['admin','manager','staff'] as $r)<option value="{{ $r }}" {{ old('role',$user->role)===$r ? 'selected' : '' }}>{{ ucfirst($r) }}{{ $r==='admin' ? ' — full access incl. settings' : ($r==='manager' ? ' — workspace, no settings' : ' — workspace, no settings') }}</option>@endforeach</select>
<label class="f">Monthly collection target (₹) <span class="hint">— 0 means no target</span></label><input class="in" type="number" step="0.01" min="0" name="monthly_target" value="{{ old('monthly_target',$user->monthly_target ?? 0) }}">
<label class="f">Commission on collections (%) <span class="hint">— 0 means none</span></label><input class="in" type="number" step="0.01" min="0" max="100" name="commission_rate" style="max-width:240px" value="{{ old('commission_rate',$user->commission_rate ?? 0) }}">
<div class="fsec">Employee profile</div>
<div class="form-grid">
<div><label class="f">Designation</label><input class="in" name="designation" value="{{ old('designation',$user->designation) }}" placeholder="e.g. Site electrician"></div>
<div><label class="f">Department</label><input class="in" name="department" value="{{ old('department',$user->department) }}" placeholder="e.g. Execution"></div>
<div><label class="f">Phone</label><input class="in" name="phone" value="{{ old('phone',$user->phone) }}"></div>
<div><label class="f">Joining date</label><input class="in" type="date" name="joining_date" value="{{ old('joining_date', $user->joining_date ? d($user->joining_date,'Y-m-d') : '') }}"></div>
<div><label class="f">Weekly capacity (hrs)</label><input class="in" type="number" min="1" max="168" name="capacity_hours" value="{{ old('capacity_hours', $user->capacity_hours) }}" placeholder="Blank = team default"></div>
</div>
<label class="f">Address</label><input class="in" name="address" value="{{ old('address',$user->address) }}">
@php $perms = old('permissions', $user->permissions ?? []); @endphp
<div id="perm-matrix">
<div class="fsec">Module access <span class="hint">— inherit keeps the role default (full workspace)</span></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>Module</th><th>Access</th></tr>
@foreach(perm_modules() as $key => $label)
@php $cur = is_array($perms) ? ($perms[$key] ?? 'inherit') : 'inherit'; @endphp
<tr><td>{{ $label }}</td><td><select class="in" name="permissions[{{ $key }}]" style="margin:0;max-width:220px"><option value="inherit" {{ $cur==='inherit'?'selected':'' }}>Inherit role default</option><option value="full" {{ $cur==='full'?'selected':'' }}>Full</option><option value="view" {{ $cur==='view'?'selected':'' }}>View only</option><option value="none" {{ $cur==='none'?'selected':'' }}>No access</option></select></td></tr>
@endforeach
</table></div>
<p class="fhint">View only hides all save buttons by blocking writes (403). No access hides the section from the menu entirely. Invoice & proposal ownership (assigned / created) still applies on top.</p>
</div>
<script>
(function(){
var role = document.getElementById('role-select'), mx = document.getElementById('perm-matrix');
if (!role || !mx) return;
function sync(){ mx.style.display = role.value === 'admin' ? 'none' : ''; }
role.addEventListener('change', sync); sync();
})();
</script>
<input type="hidden" name="is_active" value="0">
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:16px"><input type="checkbox" name="is_active" value="1" {{ old('is_active',$user->is_active ?? true) ? 'checked' : '' }} style="width:auto;margin:0"> Active (can sign in)</label>
<div class="btnrow"><button class="btn">Save user</button><a class="btn sec" href="{{ route('settings.users.index') }}">Cancel</a></div>
</form></div>
@endsection
