@extends('layouts.app')
@section('title','Field visits')
@section('content')
<div class="card">
<div class="card-head"><h3>Who is on site</h3>@if($open)<span class="badge b-green">{{ $open->user->name ?? 'You' }} checked in {{ d($open->check_in_at,'h:i A') }}</span>@endif</div>
@if(!$open)
<form method="POST" action="{{ route('field.checkin') }}" class="noprint" id="checkin-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:12px">@csrf
<div><label class="f">Site task</label><select class="in" name="task_id" style="margin:0"><option value="">No task</option>@foreach($tasks as $t)<option value="{{ $t->id }}">{{ \Str::limit($t->title,50) }}</option>@endforeach</select></div>
<div style="flex:1;min-width:160px"><label class="f">Note</label><input class="in" name="note" maxlength="255" style="margin:0" placeholder="Site, purpose…"></div>
<input type="hidden" name="lat" id="geo-lat"><input type="hidden" name="lng" id="geo-lng">
<div><button class="btn sm" id="checkin-btn">Check in here</button></div></form>
<p class="fhint" id="geo-hint">Your phone's GPS location attaches automatically (or leave it blank).</p>
@else
<form method="POST" action="{{ route('field.checkout',$open) }}" class="noprint" id="checkout-form" style="display:flex;gap:8px;align-items:end;margin-bottom:12px">@csrf
<input type="hidden" name="lat" id="geo-lat"><input type="hidden" name="lng" id="geo-lng">
<div><button class="btn sm">Check out (in since {{ d($open->check_in_at,'h:i A') }})</button></div></form>
@endif
<form method="GET" data-ajax-filter data-region="field-list" class="noprint" style="display:flex;gap:8px;margin-bottom:12px">@if($canAll)<select class="in" style="margin:0" name="user_id"><option value="">Everyone</option>@foreach($users as $u)<option value="{{ $u->id }}" {{ (string)($user_id ?? '')===(string)$u->id?'selected':'' }}>{{ $u->name }}</option>@endforeach</select>@endif<input class="in" style="margin:0" type="date" name="day" value="{{ $day ?? '' }}"><button class="btn sec sm">Filter</button></form>
<div data-ajax-region="field-list"><div class="tbl-scroll roomy"><table class="tbl"><tr><th>Who</th><th>In</th><th>Out</th><th>Time</th><th>Task</th><th></th></tr>
@forelse($rows as $v)<tr>
<td><b>{{ $v->user->name ?? '—' }}</b>@if($v->note)<div class="hint">{{ \Str::limit($v->note,50) }}</div>@endif</td>
<td class="nowrap">{{ d($v->check_in_at,'j M, h:i A') }}@if($v->mapsUrl())<div><a class="hint" href="{{ $v->mapsUrl() }}" target="_blank">📍 map</a></div>@endif</td>
<td class="nowrap">@if($v->check_out_at){{ d($v->check_out_at,'h:i A') }}@else<span class="badge b-green">on site</span>@endif</td>
<td class="nowrap">@if($v->minutes())<b>{{ \Modules\Tasks\TaskTime::fmt($v->minutes()) }}</b>@else<span class="hint">—</span>@endif</td>
<td>{{ $v->task ? \Str::limit($v->task->title,40) : '—' }}</td>
<td>@if(!$v->check_out_at && ($v->user_id === auth()->id() || $canAll))<form method="POST" action="{{ route('field.checkout',$v) }}" style="display:inline">@csrf<button class="icon-btn">Check out</button></form>@endif</td></tr>
@empty<tr><td colspan="6"><div class="empty">No site visits logged.</div></td></tr>@endforelse</table></div>
{{ $rows->links() }}</div></div>
<script>
(function(){
if (!navigator.geolocation) return;
navigator.geolocation.getCurrentPosition(function(pos){
  var lat = document.getElementById('geo-lat'), lng = document.getElementById('geo-lng');
  if (lat) lat.value = pos.coords.latitude.toFixed(6);
  if (lng) lng.value = pos.coords.longitude.toFixed(6);
  var h = document.getElementById('geo-hint');
  if (h) h.textContent = 'GPS locked — location will attach.';
}, function(){}, {timeout: 8000});
})();
</script>
@endsection
