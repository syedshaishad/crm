@extends('layouts.app')
@section('title','Email templates')
@section('content')
<form method="POST" action="{{ route('settings.emails.store') }}">@csrf
<p class="fhint" style="max-width:720px">Every customer email is built from these. Write plain text with &#123;&#123; &#125;&#125; tokens — the preview under each one shows exactly what the client receives.</p>
@php
  $groups = [
    'invoice' => ['title' => 'Invoice email', 'desc' => 'Sent from an invoice via “Email to client”.'],
    'receipt' => ['title' => 'Payment receipt email', 'desc' => 'Sent from a payment via “Email receipt”.'],
    'proposal' => ['title' => 'Proposal email', 'desc' => 'Sent from a proposal via “Email to client” — carries the signing link.'],
    'reset' => ['title' => 'Password reset email', 'desc' => 'Sent when staff click “Forgot password”.'],
    'reminder' => ['title' => 'Daily digest email', 'desc' => 'Morning roundup: follow-ups, due tasks, overdue invoices, expiring proposals, AMC renewals, compliance documents and cheques due.'],
    'overdue' => ['title' => 'Overdue nudge email', 'desc' => 'Auto-sent to clients with overdue invoices (at most once every 3 days each).'],
    'followup' => ['title' => 'Proposal follow-up email', 'desc' => 'Sent from a proposal via “Send follow-up” — at most once every 3 days each.'],
    'statement' => ['title' => 'Account statement email', 'desc' => 'Sent from a client ledger via “Email statement” — full ledger plus pay links.'],
  ];
@endphp
@foreach($groups as $g => $meta)
<div class="card" style="max-width:720px"><h3>{{ $meta['title'] }}</h3>
<p class="fhint">{{ $meta['desc'] }}</p>
<label class="f">Subject</label><input class="in" name="email_{{ $g }}_subject" value="{{ old('email_'.$g.'_subject', $s->get('email.'.$g.'_subject', $defaults['email.'.$g.'_subject'])) }}" required maxlength="255">
<label class="f">Body</label><textarea class="in" name="email_{{ $g }}_body" rows="9" required>{{ old('email_'.$g.'_body', $s->get('email.'.$g.'_body', $defaults['email.'.$g.'_body'])) }}</textarea>
<p class="fhint">Tokens: @foreach(email_placeholders($g) as $ph)<code>&#123;&#123;{{ $ph }}&#125;&#125;</code> @endforeach</p>
<details class="adv"><summary>Preview with sample data</summary>
<div style="margin-top:10px"><div class="bill-box"><div class="cap">{{ render_template('email.'.$g.'_subject', $samples[$g]) }}</div><div class="sub" style="white-space:pre-line">{{ render_template('email.'.$g.'_body', $samples[$g]) }}</div></div></div></details></div>
@endforeach
<div class="card" style="max-width:720px"><div class="form-actions"><button class="btn" type="submit">Save email templates</button><a class="btn sec" href="{{ route('settings.index') }}">Cancel</a><a class="btn sec" href="{{ route('settings.integrations') }}">Test mail setup</a></div></div>
</form>
<div class="card" style="max-width:720px"><div class="card-head"><h3>Outbox — recent mail</h3><span class="hint">auto-logged on send</span></div>
<div class="tbl-scroll"><table class="tbl"><tr><th>When</th><th>To</th><th>Subject</th></tr>
@forelse(\Illuminate\Support\Facades\DB::table('mail_logs')->orderByDesc('id')->limit(25)->get() as $m)<tr><td class="nowrap">{{ d($m->created_at) }}</td><td>{{ \Str::limit($m->to ?? '—', 40) }}</td><td>{{ $m->subject ?? '—' }}</td></tr>@empty<tr><td colspan="3"><div class="empty">Nothing sent yet — invoice, receipt, proposal, digest, nudge, follow-up and statement mails land here.</div></td></tr>@endforelse</table></div></div>
@endsection
