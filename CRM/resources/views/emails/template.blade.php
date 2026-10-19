<div style="font-family:system-ui,Arial,sans-serif;line-height:1.6;color:#1c1a15;white-space:pre-line">{{ $body }}</div>
@php $b = $brand ?? []; $contact = function_exists('brand_contact') ? brand_contact(is_array($b) ? $b : []) : ''; @endphp
@if(!empty($b['company_name']) || $contact !== '')
<div style="font-family:system-ui,Arial,sans-serif;margin-top:24px;padding-top:12px;border-top:1px solid #e5e0d2;font-size:12px;color:#8a8474">@if(!empty($b['company_name']))<b style="color:#5b5648">{{ $b['company_name'] }}</b>@endif @if($contact !== '')<br>{{ $contact }}@endif</div>
@endif
