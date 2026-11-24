<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $pageTitle }} — {{ $brand['company_name'] ?? $brand['app_name'] ?? config('app.name') }}</title>
@if(!empty($brand['favicon_path']))<link rel="icon" href="{{ asset('storage/'.$brand['favicon_path']) }}">@else<link rel="icon" href="{{ asset('favicon.ico') }}">@endif
<link rel="stylesheet" href="{{ asset('assets/css/app.css').'?v=169' }}">
<link rel="preload" href="{{ asset('assets/fonts/onest-var.woff2') }}" as="font" type="font/woff2" crossorigin>
@include('brand.theme')
