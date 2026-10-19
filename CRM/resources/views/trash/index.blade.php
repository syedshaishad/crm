@extends('layouts.app')
@section('title','Trash')
@section('content')
<p class="fhint" style="max-width:720px">Deleted clients, projects, tasks, invoices, payments, expenses, documents, leads, proposals and users rest here. Restore them, or delete forever.</p>
@foreach($groups as $g)
<div class="card"><div class="card-head"><h3>{{ $g['label'] }} ({{ $g['items']->count() }})</h3></div>
@if($g['items']->isEmpty())<p class="hint" style="margin:0">Nothing here.</p>@else
<div class="tbl-scroll"><table class="tbl"><tr><th>Item</th><th>Deleted</th><th></th></tr>
@foreach($g['items'] as $m)<tr>
<td><b>{{ \App\Http\Controllers\TrashController::label($m) }}</b><div class="hint">#{{ $m->id }}</div></td>
<td class="nowrap">{{ d($m->deleted_at) }}</td>
<td><div class="row-actions">
<form method="POST" action="{{ route('trash.restore',[$g['type'],$m->id]) }}" style="display:inline">@csrf<button class="icon-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>Restore</button></form>
<form method="POST" action="{{ route('trash.destroy',[$g['type'],$m->id]) }}" style="display:inline" data-confirm="Permanently delete {{ \App\Http\Controllers\TrashController::label($m) }}? This cannot be undone.">@csrf @method('DELETE')<button class="icon-btn danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>Delete forever</button></form></div></td></tr>
@endforeach</table></div>
@endif</div>
@endforeach
@endsection
