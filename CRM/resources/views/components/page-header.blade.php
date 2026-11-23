@props(['title', 'crumb' => null, 'icon' => null, 'back' => null])

<div {{ $attributes->merge(['class' => 'page-head ix-head']) }}>
  <div class="ix-head-main">
    @if($icon)
      <span class="ix-head-ic" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
      </span>
    @endif
    <div>
      <h1>{{ $title }}</h1>
      @if($crumb)<div class="crumb">{{ $crumb }}</div>@endif
    </div>
  </div>
  <div class="ix-head-actions noprint">
    @if($back)<a class="btn sm sec" href="{{ $back }}">← Back</a>@endif
    {{ $slot }}
  </div>
</div>
