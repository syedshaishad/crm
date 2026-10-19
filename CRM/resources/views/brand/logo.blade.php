@php
// Theme-aware logo: dark variant on dark UI, light variant on light UI/print.
// Single logo falls back to itself everywhere. Pass light=/dark= to override
// (invoice logos), cls=/style= for sizing.
// Pass fixed=path to pin one file on surfaces that never change theme
// (dark sidebar, red auth panel): renders without theme classes so no
// light/dark rule can ever hide or swap it.
$light = $light ?? ($brand['logo_path'] ?? '');
$dark = $dark ?? ($brand['logo_dark_path'] ?? '');
$showDark = $dark ?: $light;
$showLight = $light ?: $dark;
@endphp
@if(!empty($fixed))
<img class="logo-fixed {{ $cls ?? '' }}" @if(!empty($style))style="{{ $style }}"@endif src="{{ asset('storage/'.$fixed) }}" alt="{{ $brand['company_name'] ?? 'logo' }}">
@elseif($showDark)
<img class="logo-dark {{ $cls ?? '' }}" @if(!empty($style))style="{{ $style }}"@endif src="{{ asset('storage/'.$showDark) }}" alt="{{ $brand['company_name'] ?? 'logo' }}">
@if($showLight !== $showDark)
<img class="logo-light {{ $cls ?? '' }}" @if(!empty($style))style="{{ $style }}"@endif src="{{ asset('storage/'.$showLight) }}" alt="{{ $brand['company_name'] ?? 'logo' }}">
@endif
@elseif(empty($hideName))
<span>{{ $brand['company_name'] ?? config('app.name') }}</span>
@endif
