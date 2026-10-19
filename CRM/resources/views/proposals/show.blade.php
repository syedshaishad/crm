@extends('layouts.app')
@section('title',$proposal->number)
@section('content')
@php
  $st = $proposal->displayStatus();
  $stColor = $st === 'accepted' || $st === 'invoiced' ? 'b-green' : ($st === 'sent' ? 'b-blue' : ($st === 'declined' ? 'b-red' : ($st === 'expired' ? 'b-amber' : 'b-grey')));
  $intra = $proposal->isIntraState();
  $halfRate = $proposal->gst_rate / 2;
  $signUrl = $proposal->sign_token ? route('proposals.sign', $proposal->sign_token) : '';
@endphp
<div class="inv-hero noprint">
  <div><div class="inv-num">{{ $proposal->number }}</div>
    <div class="inv-chips">
      <span class="badge {{ $stColor }}">{{ \Modules\Proposals\Proposal::statuses()[$st] ?? $st }}</span>
      @if($proposal->project)<span class="chip">Project: <a href="{{ route('projects.show',$proposal->project) }}">{{ $proposal->project->name }}</a></span>@endif
      @if($proposal->valid_until)<span class="chip {{ $st==='expired' ? 'overdue' : '' }}">Valid till {{ d($proposal->valid_until) }}</span>@endif
      @if(($proposal->view_count ?? 0) > 0)<span class="chip">Opened {{ $proposal->view_count }}×@if($proposal->last_viewed_at) · {{ $proposal->last_viewed_at->diffForHumans() }}@endif</span>@endif
      @if($proposal->converted_invoice_id)<span class="chip">→ <a href="{{ route('invoices.show',$proposal->converted_invoice_id) }}">invoice</a></span>@endif
      @if($proposal->status==='declined')<span class="chip">Lost: {{ \Modules\Proposals\Proposal::lostReasons()[$proposal->lost_reason] ?? 'not recorded' }}@if($proposal->competitor) → {{ $proposal->competitor }}@endif</span>@endif
    </div></div>
  <div class="btnrow">
    <a class="btn sec sm" href="{{ route('proposals.pitch',$proposal) }}" target="_blank">Pitch mode</a>
    @if($proposal->status==='draft')
      <a class="btn sec sm" href="{{ route('proposals.edit',$proposal) }}">Edit</a>
      <form method="POST" action="{{ route('proposals.send',$proposal) }}" data-ajax-post data-refresh="page" style="display:inline">@csrf<button class="btn sm">Send to client</button></form>
    @endif
    @if($proposal->status==='sent')
      <form method="POST" action="{{ route('proposals.acceptOffline',$proposal) }}" style="display:inline-flex;gap:6px;align-items:center" data-confirm="Mark accepted offline? The client confirmed outside the signing link.">@csrf<select class="in" name="accept_mode" required style="margin:0;width:auto"><option value="">Accepted via…</option><option value="call">Phone call</option><option value="whatsapp">WhatsApp</option><option value="in_person">In person</option><option value="email">Email</option></select><input class="in" name="accept_note" maxlength="255" placeholder="Note (optional)" style="margin:0;width:170px"><button class="btn sm sec">Mark accepted</button></form>
    @endif
    @if(in_array($proposal->status,['sent','expired']))
      <form method="POST" action="{{ route('proposals.declineOffline',$proposal) }}" style="display:inline-flex;gap:6px;align-items:center" data-confirm="Mark declined? It will count in win-loss analytics.">@csrf<select class="in" name="lost_reason" required style="margin:0;width:auto"><option value="">Lost: why?...</option>@foreach(\Modules\Proposals\Proposal::lostReasons() as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select><input class="in" name="competitor" maxlength="120" placeholder="Competitor (optional)" style="margin:0;width:150px"><button class="btn sm sec">Mark declined</button></form>
    @endif
    @if($proposal->status==='accepted')      <form method="POST" action="{{ route('proposals.convert',$proposal) }}" style="display:inline">@csrf<button class="btn sm brand">Convert to invoice</button></form>
      <form method="POST" action="{{ route('proposals.startProject',$proposal) }}" style="display:inline" data-confirm="Start a project from the scope of work? Each scope line becomes a task.">@csrf<button class="btn sm sec">Start project</button></form>
    @endif
    <div class="has-drop">
      <button type="button" class="btn sec sm" data-drop-toggle aria-label="More actions">•••</button>
      <div class="drop">
        <form method="POST" action="{{ route('proposals.duplicate',$proposal) }}" data-confirm="Clone {{ $proposal->number }} as a new draft?">@csrf<button class="menu-item" type="submit">Clone as draft</button></form>
        <a class="menu-item" href="{{ route('proposals.compare',$proposal) }}">Compare versions</a>
        <form method="POST" action="{{ route('proposals.templates.save',$proposal) }}" data-confirm="Save {{ $proposal->number }} as a reusable template?">@csrf<input type="hidden" name="name" value="{{ $proposal->number }} package"><button class="menu-item" type="submit">Save as template</button></form>
        @if(!in_array($proposal->status,['accepted','invoiced']))
        <form method="POST" action="{{ route('proposals.destroy',$proposal) }}" data-confirm="Delete proposal {{ $proposal->number }}?">@csrf @method('DELETE')<button class="menu-item danger" type="submit">Delete</button></form>
        @endif
      </div>
    </div>
  </div>
</div>

@if($proposal->status==='sent')
<div class="card noprint"><div class="card-head"><h3>Client signing link</h3><span class="btnrow" style="gap:8px"><button class="btn sec sm" onclick="navigator.clipboard.writeText('{{ $signUrl }}');this.textContent='Copied!';setTimeout(()=>this.textContent='Copy link',1500)">Copy link</button><form method="POST" action="{{ route('proposals.email',$proposal) }}" data-ajax-post style="display:inline">@csrf<button class="btn sm">Email to client</button></form><form method="POST" action="{{ route('proposals.followup',$proposal) }}" data-ajax-post data-refresh="page" style="display:inline">@csrf<button class="btn sm sec">Send follow-up</button></form></span></div>
<p class="hint" style="margin:0 0 8px">Share this with <b>{{ $proposal->client->name }}</b> (WhatsApp / email). No login needed — they open, sign, done.@if($proposal->last_followed_up_at) Last follow-up {{ $proposal->last_followed_up_at->diffForHumans() }} (max one every 3 days).@endif</p>
<code style="overflow-wrap:anywhere">{{ $signUrl }}</code></div>

@php
$propSms = 'Hello '.($proposal->client->company ?: ($proposal->client->name ?? 'there')).', our quote '.$proposal->number.' of Rs. '.number_format($proposal->total,2).' from '.($brand['company_name'] ?? '').' is waiting your approval.'
  .($signUrl ? ' Review & sign: '.$signUrl : '')
  .($proposal->valid_until ? ' Valid till '.d($proposal->valid_until,'j M Y').'.' : '');
@endphp
<div class="card noprint"><div class="card-head"><h3>Chase via SMS</h3><button class="btn sec sm" data-sms="{{ $propSms }}" onclick="navigator.clipboard.writeText(this.dataset.sms);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy SMS text',1500)">Copy SMS text</button></div>
<p class="hint" style="margin:0;white-space:pre-line">{{ $propSms }}</p></div>

<div class="card noprint"><div class="card-head"><h3>Client thread</h3>@if(($replies ?? collect())->isNotEmpty())<span class="hint">{{ $replies->where('side','client')->count() }} from client</span>@endif</div>
@forelse($replies ?? [] as $rep)<div style="margin-bottom:10px"><b>{{ $rep->side === 'staff' ? 'You' : ($rep->author ?? 'Client') }}</b> <span class="hint">{{ d($rep->created_at, 'j M, h:i A') }}</span><div class="sub" style="white-space:pre-line">{{ $rep->body }}</div></div>@empty<p class="hint" style="margin:0">No messages — the client can ask questions from their signing link.</p>@endforelse
<form method="POST" action="{{ route('proposals.reply',$proposal) }}" style="margin-top:10px">@csrf
<textarea class="in" name="body" rows="2" required maxlength="2000" placeholder="Reply to the client — shows on their link…"></textarea>
<div class="form-actions"><button class="btn sec sm" type="submit">Reply</button></div></form></div>

<div class="card noprint"><div class="card-head"><h3>Follow-ups</h3></div>
@if($followups->isNotEmpty())
<div class="tbl-scroll"><table class="tbl"><tr><th>Sent</th><th>Channel</th><th>To</th><th>Status</th></tr>
@foreach($followups as $g)<tr><td class="nowrap">{{ d($g->created_at,'j M Y, h:i A') }}</td><td>{{ ucfirst($g->channel) }}</td><td class="nowrap">{{ $g->recipient }}</td><td>@if($g->status==='sent')<span class="badge b-green">sent</span>@else<span class="badge b-red" title="{{ $g->error }}">failed</span>@endif</td></tr>@endforeach</table></div>
@else
<p class="hint" style="margin:0">No follow-ups yet — nudge the client while the proposal is open, at most once every 3 days. Auto sequence days: {{ setting('proposal.followup_days','3,7,14') ?: 'off' }}.</p>
@endif
</div>
@endif

<div class="card">
@include('proposals.doc-'.proposal_theme())
</div>
@endsection
