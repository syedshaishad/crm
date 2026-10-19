@extends('layouts.app')
@section('title','Proposal templates')
@section('content')
<div class="card">
<div class="card-head"><h3>Start from a template</h3><a class="btn sm sec" href="{{ route('proposals.index') }}">All proposals</a></div>
<p class="hint" style="margin-top:0">Pick a client — a draft opens with the template's services, scope, results and terms, ready to review and send.</p>
<div class="tbl-scroll roomy"><table class="tbl"><tr><th>Template</th><th style="text-align:right">Services</th><th>New draft for</th><th></th></tr>
@forelse($templates as $t)<tr><td><b>{{ $t->name }}</b><div class="hint">{{ mb_substr($t->scope ?? $t->notes ?? '', 0, 90) }}</div></td><td class="nowrap" style="text-align:right">{{ count($t->items ?? []) }}</td>
<td><form method="POST" action="{{ route('proposals.templates.use',$t) }}" style="display:flex;gap:6px">@csrf<select class="in" name="client_id" required style="margin:0;min-width:180px"><option value="">Choose client…</option>@foreach(\Modules\Clients\Client::orderBy('name')->get(['id','name','company']) as $c)<option value="{{ $c->id }}">{{ $c->company ? $c->company.' ('.$c->name.')' : $c->name }}</option>@endforeach</select><button class="btn sm">Start draft</button></form></td>
<td style="text-align:right"><form method="POST" action="{{ route('proposals.templates.destroy',$t) }}" style="display:inline" data-confirm="Delete template {{ $t->name }}?">@csrf @method('DELETE')<button class="icon-btn danger">Delete</button></form></td></tr>
@empty<tr><td colspan="4"><div class="empty">No templates yet — open any proposal and “Save as template”.</div></td></tr>@endforelse</table></div></div>
@endsection
