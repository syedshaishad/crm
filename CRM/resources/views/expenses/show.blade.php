@extends('layouts.app')
@section('title',($expense->number ?? 'Expense'))
@section('content')
@php $ext = strtolower(pathinfo($expense->receipt_path ?? '', PATHINFO_EXTENSION)); @endphp
<div class="inv-hero noprint">
  <div><div class="inv-num">{{ $expense->number ?? $expense->title }}</div>
    <div class="inv-chips">
      <span class="badge b-grey">{{ $expense->category ?? 'general' }}</span>
      <span class="badge {{ ($expense->status ?? 'approved')==='approved' ? 'b-green' : (($expense->status ?? '')==='pending' ? 'b-amber' : 'b-grey') }}">{{ ucfirst($expense->status ?? 'approved') }}</span>
      <span class="chip">Spent {{ d($expense->spent_on, 'j M Y') }}</span>
      @if($expense->method)<span class="chip">via {{ ucfirst($expense->method) }}</span>@endif
    </div></div>
  <div class="btnrow">
    <button class="btn" onclick="window.print()">Print / PDF</button>
    @if(($expense->status ?? '')==='pending' && (auth()->user()->isAdmin() || auth()->user()->isManager()))<form method="POST" action="{{ route('expenses.approve',$expense) }}" style="display:inline" data-confirm="Approve {{ money($expense->amount) }} for {{ $expense->title }}?">@csrf<button class="btn">Approve</button></form><form method="POST" action="{{ route('expenses.reject',$expense) }}" style="display:inline-flex;gap:6px" data-confirm="Reject this expense?">@csrf<input class="in" name="notes" placeholder="Reason (optional)" style="margin:0;width:180px"><button class="btn sec">Reject</button></form>@endif
    <a class="btn sec" href="{{ route('expenses.edit',$expense) }}">Edit</a>
    <button class="btn sec" data-modal-open="recurring-modal">Make recurring</button>
    <form method="POST" action="{{ route('expenses.destroy',$expense) }}" style="display:inline" data-confirm="Delete expense {{ $expense->number }}?">@csrf @method('DELETE')<button class="btn danger">Delete</button></form>
  </div>
</div>

<div class="card">
  <div class="doc-head">
    <div class="doc-brand"><div><h2>{{ $expense->title }}</h2><div class="doc-sub">{{ $expense->number ?? '' }}</div></div></div>
    <div class="doc-meta"><h3 style="margin:0">{{ money($expense->amount) }}</h3><div class="hint">Spent {{ d($expense->spent_on, 'j F Y') }}</div></div>
  </div>
  <div class="bill-grid">
    <div class="bill-box"><div class="cap">Paid to</div><b>{{ $expense->vendor ?: '—' }}</b>
      @if($expense->reference)<div class="sub">Bill ref: {{ $expense->reference }}</div>@endif
      <div class="sub">Method: {{ $expense->method ? ucfirst($expense->method) : '—' }}</div></div>
    <div class="bill-box"><div class="cap">Booked under</div><b>@if($expense->project)<a href="{{ route('projects.show',$expense->project) }}">{{ $expense->project->name }}</a>@else General @endif</b>
      @if($expense->project && $expense->project->client)<div class="sub">{{ $expense->project->client->company ?: $expense->project->client->name }}</div>@endif
      <div class="sub">Recorded by {{ $expense->creator->name ?? '—' }}</div></div>
  </div>
  @if($expense->notes)<div class="bill-box" style="margin-bottom:14px"><div class="cap">Notes</div><div>{{ $expense->notes }}</div></div>@endif
  <div class="words">{{ amount_words($expense->amount) }} only.</div>
</div>

@if($expense->receipt_path)
<div class="card"><div class="card-head"><h3>Receipt</h3><a class="btn sm sec noprint" href="{{ asset('storage/'.$expense->receipt_path) }}" target="_blank">Open full</a></div>
  @if(in_array($ext, ['jpg','jpeg','png','webp','gif']))
  <a href="{{ asset('storage/'.$expense->receipt_path) }}" target="_blank"><img src="{{ asset('storage/'.$expense->receipt_path) }}" alt="receipt" style="max-width:100%;max-height:520px;border-radius:14px;border:1px solid var(--line)"></a>
  @else
  <p><a class="btn sec sm" href="{{ asset('storage/'.$expense->receipt_path) }}" target="_blank">Open receipt ({{ strtoupper($ext) }})</a></p>
  @endif
</div>
@endif
<div class="modal" id="recurring-modal" role="dialog" aria-label="Make recurring">
  <div class="modal-bg" data-modal-close></div>
  <div class="modal-card">
    <h3>Repeat {{ $expense->title }}</h3>
    <p class="hint" style="margin:0 0 12px">Auto-books a copy of {{ money($expense->amount) }} on each run day.</p>
    <form method="POST" action="{{ route('expenses.recurring.store',$expense) }}">@csrf
    <div class="form-grid">
    <div><label class="f">Every</label><select class="in" name="frequency" style="margin-bottom:0">@foreach(\Modules\Expenses\RecurringExpenseProfile::frequencies() as $fv=>$fl)<option value="{{ $fv }}">{{ $fl }}</option>@endforeach</select></div>
    <div><label class="f">First run</label><input class="in" type="date" name="next_run_on" value="{{ date('Y-m-d', strtotime('+1 month')) }}" style="margin-bottom:0" required></div>
    </div>
    <div class="form-actions" style="margin-top:16px"><button class="btn" type="submit">Create schedule</button><button class="btn sec" data-modal-close type="button">Cancel</button></div></form>
  </div>
</div>
@endsection
