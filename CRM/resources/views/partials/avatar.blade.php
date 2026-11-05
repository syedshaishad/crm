@php $avatarUrl = $user->avatarUrl(); $avatarClass = $class ?? 'avatar sm'; @endphp
@if($avatarUrl)
<span class="{{ $avatarClass }}"><img src="{{ $avatarUrl }}" alt="{{ $user->name }}"></span>
@else
<span class="{{ $avatarClass }}">{{ $user->initials() }}</span>
@endif
