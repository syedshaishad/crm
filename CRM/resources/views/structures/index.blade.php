@extends('layouts.app')
@section('title','Salary structures')
@section('content')
<div class="card">
<div class="card-head"><h3>Salary structures</h3><span class="hint">latest effective wins</span></div>
<form method="POST" action="{{ route('structures.store') }}" class="noprint" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
<div><label class="f">Who</label><select class="in" name="user_id" style="margin:0">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
<div><label class="f">Effective from</label><input class="in" type="date" name="effective_from" value="{{ date('Y-m-01') }}" style="margin:0" required></div>
<div><label class="f">Basic (₹)</label><input class="in" type="number" step="0.01" min="0" name="basic" style="margin:0" required></div>
<div><label class="f">HRA (₹)</label><input class="in" type="number" step="0.01" min="0" name="hra" value="0" style="margin:0"></div>
<div><label class="f">Allowances (₹)</label><input class="in" type="number" step="0.01" min="0" name="allowances" value="0" style="margin:0"></div>
<div><button class="btn sm">Save</button></div></form>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Who</th><th>From</th><th style="text-align:right">Basic</th><th style="text-align:right">HRA</th><th style="text-align:right">Allow.</th><th style="text-align:right">Gross</th><th></th></tr>
@forelse($rows as $r)<tr>
<td><b>{{ $r->user->name ?? '—' }}</b></td>
<td class="nowrap">{{ d($r->effective_from) }}</td>
<td class="nowrap" style="text-align:right">{{ money($r->basic) }}</td>
<td class="nowrap" style="text-align:right">{{ money($r->hra) }}</td>
<td class="nowrap" style="text-align:right">{{ money($r->allowances) }}</td>
<td class="nowrap" style="text-align:right"><b>{{ money($r->gross()) }}</b></td>
<td><form method="POST" action="{{ route('structures.destroy',$r) }}" style="display:inline" data-confirm="Remove this structure?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form></td></tr>
@empty<tr><td colspan="7"><div class="empty">No structures — payroll skips staff without one.</div></td></tr>@endforelse</table></div>
{{ $rows->links() }}</div>
@endsection
