@php $snipList = ($snippets ?? collect())->where('kind', $kind); @endphp
@if($snipList->isNotEmpty())
<select class="in snippet-pick" data-field="{{ $field }}" style="margin:-4px 0 10px;max-width:340px"><option value="">Insert {{ strtolower($label) }} boilerplate…</option>@foreach($snipList as $sn)<option value="{{ $sn->id }}" data-body="{{ $sn->body }}">{{ $sn->title }}</option>@endforeach</select>
@endif
