@extends('installer.layout')
@section('step','6')
@section('content')
<h1>6 — Storage, Razorpay & GST</h1>
<form method="POST" action="{{ route('installer.integrations.store') }}">@csrf
<label>Invoice prefix</label><input name="invoice_prefix" value="INV-">
<label>Razorpay Key ID (optional)</label><input name="RAZORPAY_KEY_ID">
<label>Razorpay Key Secret (optional)</label><input name="RAZORPAY_KEY_SECRET">
<label>Default GST %</label><select name="TAX_DEFAULT_GST_RATE"><option>0</option><option>5</option><option>12</option><option selected>18</option><option>28</option></select>
<div class="btnrow"><button class="btn">Save → Finish</button><a class="btn sec" href="{{ route('installer.mail') }}">← Back</a></div></form>
<p class="hint">Storage link is created automatically. Leave Razorpay blank to disable online payments.</p>
@endsection
