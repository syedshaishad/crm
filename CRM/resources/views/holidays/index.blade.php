@extends('layouts.app')
@section('title','Holidays')
@section('content')
<div class="card" style="max-width:680px">
<div class="card-head"><h3>Company holidays ({{ date('Y') }})</h3><span class="hint">payroll + rosters skip these</span></div>
<form method="POST" action="{{ route('holidays.store') }}" class="noprint" style="display:flex;gap:8px;align-items:end;margin-bottom:12px">@csrf
<div><label class="f">Day</label><input class="in" type="date" name="day" style="margin:0" required></div>
<div style="flex:1"><label class="f">Occasion</label><input class="in" name="name" required maxlength="120" style="margin:0" placeholder="e.g. Diwali"></div>
<div><button class="btn sm">Add</button></div></form>
<div class="tbl-scroll"><table class="tbl"><tr><th>Date</th><th>Occasion</th><th></th></tr>
@forelse($rows as $h)<tr><td class="nowrap">{{ d($h->day,'l, j F Y') }}</td><td><b>{{ $h->name }}</b></td>
<td style="text-align:right"><form method="POST" action="{{ route('holidays.destroy',$h) }}" style="display:inline" data-confirm="Remove {{ $h->name }}?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form></td></tr>
@empty<tr><td colspan="3"><div class="empty">No holidays marked.</div></td></tr>@endforelse</table></div>
{{ $rows->links() }}</div>
@endsection
