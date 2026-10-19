@extends('installer.layout')
@section('step','4')
@section('content')
<h1>4 — Company details (your branding, not ours)</h1>
<form method="POST" action="{{ route('installer.company.store') }}">@csrf
<label>App / website name</label><input name="app_name" value="{{ old('app_name','My Agency CRM') }}" required>
<label>Company name</label><input name="company_name" value="{{ old('company_name') }}" required>
<label>Company email</label><input type="email" name="company_email" value="{{ old('company_email') }}">
<label>Phone</label><input name="company_phone" value="{{ old('company_phone') }}">
<label>Address</label><textarea name="company_address">{{ old('company_address') }}</textarea>
<label>GSTIN</label><input name="company_gstin" value="{{ old('company_gstin') }}">
<div class="btnrow"><button class="btn">Save → Mail/SMTP</button><a class="btn sec" href="{{ route('installer.admin') }}">← Back</a></div></form>
@endsection
