@extends('layouts.app')
@section('title','Activity')
@section('content')
<div class="card">
<div class="card-head"><h3>Activity</h3><span class="hint">who did what, newest first</span></div>
<form method="GET" class="noprint" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap"><input class="in" style="margin:0;flex:2;min-width:160px" name="q" value="{{ $q ?? '' }}" placeholder="Search actions…"><select class="in" style="margin:0;flex:1;min-width:130px" name="action"><option value="">All actions</option>@foreach($actions as $a)<option value="{{ $a }}" {{ ($action ?? '')===$a?'selected':'' }}>{{ ucfirst($a) }}</option>@endforeach</select><select class="in" style="margin:0;flex:1;min-width:140px" name="user_id"><option value="">Everyone</option>@foreach($users as $u)<option value="{{ $u->id }}" {{ (string)($user_id ?? '')===(string)$u->id?'selected':'' }}>{{ $u->name }}</option>@endforeach</select><button class="btn sec sm">Filter</button></form>
<div class="tbl-scroll"><table class="tbl"><tr><th>When</th><th>Who</th><th>What</th></tr>
@forelse($items as $a)<tr><td class="nowrap">{{ d($a->created_at, 'j M Y, h:i A') }}</td>
<td>@if($a->user)<span class="who"><span class="av sm">{{ strtoupper(substr($a->user->name,0,1)) }}</span>{{ $a->user->name }}</span>@else<span class="badge b-blue">Client</span>@endif</td>
<td>{{ $a->description }}@if($a->subject_type !== '—')<div class="hint">{{ $a->subject_type }} #{{ $a->subject_id }}</div>@endif</td></tr>
@empty<tr><td colspan="3"><div class="empty">No activity yet — create, move or delete something.</div></td></tr>@endforelse</table></div>
{{ $items->links() }}</div>
@endsection
