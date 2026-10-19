@extends('layouts.app')
@section('title', $doc->exists ? 'Edit '.$doc->number : 'New document')
@section('content')
<div class="card" style="max-width:680px">
<h3>{{ $doc->exists ? 'Edit '.$doc->number : 'Track a document' }}</h3>
<form method="POST" action="{{ $doc->exists ? route('expiry.update',$doc) : route('expiry.store') }}">@csrf @if($doc->exists)@method('PUT')@endif
<label class="f">Title <span class="req">*</span></label>
<input class="in" name="title" value="{{ old('title',$doc->title) }}" required placeholder="e.g. GSTR-3B June, Shop licence, Fire insurance">
<div class="form-grid">
<div><label class="f">Type</label><select class="in" name="category">@foreach(\Modules\Expiry\ExpiryDocument::categories() as $ck=>$cl)<option value="{{ $ck }}" {{ old('category',$doc->category ?? 'other')===$ck?'selected':'' }}>{{ $cl }}</option>@endforeach</select></div>
<div><label class="f">Alert me (days before)</label><input class="in" type="number" min="1" max="365" name="remind_days" value="{{ old('remind_days',$doc->remind_days ?? 30) }}"></div>
</div>
<div class="form-grid">
<div><label class="f">Ref / policy / ARN no</label><input class="in" name="ref_no" value="{{ old('ref_no',$doc->ref_no) }}" placeholder="optional"></div>
<div><label class="f">Authority / insurer</label><input class="in" name="authority" value="{{ old('authority',$doc->authority) }}" placeholder="e.g. GST portal, Municipal corp"></div>
</div>
<div class="form-grid">
<div><label class="f">Valid from</label><input class="in" type="date" name="valid_from" value="{{ old('valid_from', $doc->valid_from ? d($doc->valid_from,'Y-m-d') : date('Y-m-d')) }}"></div>
<div><label class="f">Valid to / due date</label><input class="in" type="date" name="valid_to" value="{{ old('valid_to', $doc->valid_to ? d($doc->valid_to,'Y-m-d') : '') }}"></div>
</div>
<label class="f">Notes</label><textarea class="in" name="notes" rows="2">{{ old('notes',$doc->notes) }}</textarea>
<div class="form-actions"><button class="btn" type="submit">{{ $doc->exists ? 'Save changes' : 'Start tracking' }}</button><a class="btn sec" href="{{ $doc->exists ? route('expiry.show',$doc) : route('expiry.index') }}">Cancel</a></div>
</form></div>
@endsection
