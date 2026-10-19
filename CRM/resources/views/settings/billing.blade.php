@extends('layouts.app')
@section('title','Billing')
@section('content')
<form method="POST" action="{{ route('settings.billing.store') }}" enctype="multipart/form-data">@csrf
<div class="card" style="max-width:720px"><h3>Numbering</h3>
<div class="form-grid">
<div><label class="f">Invoice prefix</label><input class="in" name="invoice_prefix" value="{{ $s->get('invoice.prefix','INV-') }}"></div>
<div><label class="f">Expense prefix</label><input class="in" name="expense_prefix" value="{{ $s->get('expense.prefix','EXP-') }}"></div>
<div><label class="f">Receipt prefix</label><input class="in" name="payment_prefix" value="{{ $s->get('payments.prefix','PAY-') }}"></div>
<div><label class="f">Proposal prefix</label><input class="in" name="proposal_prefix" value="{{ $s->get('proposal.prefix','PROP-') }}"></div>
<div><label class="f">Proposal valid for (days)</label><input class="in" type="number" min="1" max="365" name="proposal_valid_days" value="{{ $s->get('proposal.valid_days',15) }}"></div>
<div><label class="f">Proposal theme</label><select class="in" name="proposal_theme"><option value="classic" {{ $s->get('proposal.theme','classic')==='classic'?'selected':'' }}>Classic — full boxes</option><option value="compact" {{ $s->get('proposal.theme')==='compact'?'selected':'' }}>Compact — dense, less ink</option></select></div>
<div><label class="f">Staff discount limit (%) <span class="hint">— 0 = no limit</span></label><input class="in" type="number" step="0.01" min="0" max="100" name="proposal_discount_limit" value="{{ $s->get('proposal.discount_limit',20) }}"></div>
<div><label class="f">Auto follow-up on day(s) <span class="hint">— blank = off</span></label><input class="in" name="proposal_followup_days" value="{{ $s->get('proposal.followup_days','3,7,14') }}" placeholder="e.g. 3,7,14"></div>
<div><label class="f">Credit note prefix</label><input class="in" name="credit_prefix" value="{{ $s->get('credit.prefix','CN-') }}"></div>
<div><label class="f">Invoice currencies <span class="hint">— one per line</span></label><textarea class="in" name="invoice_currencies" rows="3">{{ old('invoice_currencies', $s->get('invoice.currencies', "INR\nUSD\nEUR\nGBP\nAED")) }}</textarea></div>
</div><p class="fhint">New proposals auto-expire after this many days.</p>
<div class="fsec" style="margin-top:14px">Number series</div>
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:8px"><input type="radio" name="numbering_mode" value="continuous" {{ $s->get('numbering.mode','continuous')==='continuous'?'checked':'' }} style="width:auto;margin:0"> Continuous — <code>{{ $s->get('invoice.prefix','INV-') }}00001</code>, never resets</label>
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:8px"><input type="radio" name="numbering_mode" value="fy" {{ $s->get('numbering.mode')==='fy'?'checked':'' }} style="width:auto;margin:0"> Financial year — <code>{{ rtrim($s->get('invoice.prefix','INV-'),'-_/ ') }}/{{ fy() }}/001</code>, resets every April</label>
<p class="fhint">FY mode applies to invoices, payments, expenses, proposals, credit notes, purchase orders and challans. Existing numbers never change.</p></div>
<div class="card" style="max-width:720px"><h3>New invoice defaults</h3>
<div class="form-grid">
<div><label class="f">Due after (days)</label><input class="in" type="number" min="0" max="365" name="invoice_due_days" value="{{ $s->get('invoice.due_days',7) }}"></div>
<div><label class="f">Invoice theme</label><select class="in" name="invoice_theme"><option value="classic" {{ $s->get('invoice.theme','classic')==='classic'?'selected':'' }}>Classic — full boxes</option><option value="compact" {{ $s->get('invoice.theme')==='compact'?'selected':'' }}>Compact — dense, less ink</option></select></div>
<div><label class="f">Default payment method</label><select class="in" name="payments_default_method">@foreach(setting_lines('payments.methods',['cash','bank','upi','razorpay','other']) as $m)<option value="{{ $m }}" {{ $s->get('payments.default_method','upi')===$m?'selected':'' }}>{{ ucfirst($m) }}</option>@endforeach</select></div>
</div>
<label class="f">Default notes</label><textarea class="in" name="invoice_default_notes" rows="2" placeholder="Prefilled on every new invoice…">{{ $s->get('invoice.default_notes') }}</textarea></div>
<div class="card" style="max-width:720px"><h3>Late fees</h3>
<p class="fhint">One-click fee on overdue invoices — added as a zero-GST line so tax math stays untouched. Applies once per invoice.</p>
<label class="hint" style="display:flex;gap:8px;align-items:center;margin-bottom:12px"><input type="checkbox" name="late_fee_enabled" value="1" {{ $s->get('billing.late_fee_enabled','0')==='1'?'checked':'' }} style="width:auto;margin:0"> Enable late fees</label>
<div class="form-grid">
<div><label class="f">Type</label><select class="in" name="late_fee_type"><option value="flat" {{ $s->get('billing.late_fee_type','flat')==='flat'?'selected':'' }}>Flat amount (₹)</option><option value="percent" {{ $s->get('billing.late_fee_type')==='percent'?'selected':'' }}>% of balance due</option></select></div>
<div><label class="f">Value</label><input class="in" type="number" step="0.01" min="0" name="late_fee_value" value="{{ $s->get('billing.late_fee_value',500) }}"></div>
<div><label class="f">Grace days after due date</label><input class="in" type="number" min="0" max="365" name="late_fee_grace_days" value="{{ $s->get('billing.late_fee_grace_days',0) }}"></div>
</div></div>
<div class="card" style="max-width:720px"><h3>Collection ladder</h3>
<p class="fhint">Overdue reminders escalate automatically: gentle → firm → final notice as the days pile up. Blank = every nudge stays gentle.</p>
<div><label class="f">Escalate on day(s) overdue</label><input class="in" name="collections_dunning_days" value="{{ $s->get('collections.dunning_days','7,15,30') }}" placeholder="e.g. 7,15,30" style="max-width:240px"></div></div>
<div class="card" style="max-width:720px"><h3>Loyalty credits</h3>
<p class="fhint">Clients earn a % of every receipt as loyalty balance, redeemable as a payment method. 0 = off.</p>
<div><label class="f">Earn rate (% of receipt)</label><input class="in" type="number" step="0.01" min="0" max="100" name="loyalty_earn_percent" value="{{ $s->get('loyalty.earn_percent',0) }}" style="max-width:240px"></div></div>
<div class="card" style="max-width:720px"><h3>Payroll</h3>
<p class="fhint">Overtime pays a multiple of the hourly cost; salary TDS (192) is a flat % above the ₹4,167 monthly standard slice.</p>
<div class="form-grid">
<div><label class="f">Overtime multiple</label><input class="in" type="number" step="0.1" min="1" max="5" name="payroll_overtime_rate" value="{{ $s->get('payroll.overtime_rate',1.5) }}"></div>
<div><label class="f">Salary TDS (%)</label><input class="in" type="number" step="0.01" min="0" max="30" name="payroll_tds_percent" value="{{ $s->get('payroll.tds_percent',10) }}"></div>
</div></div>
<div class="card" style="max-width:720px"><h3>Cheques</h3>
<p class="fhint">Post-dated cheques surface in the morning digest this many days before their date.</p>
<div><label class="f">Digest alert window (days)</label><input class="in" type="number" min="1" max="90" name="cheque_alert_days" style="max-width:240px" value="{{ $s->get('cheques.alert_days',7) }}"></div></div>
<div class="card" style="max-width:720px"><h3>Bank details</h3>
<p class="fhint">Printed on every invoice so clients can pay by bank transfer. Leave blank to hide.</p>
<div class="form-grid">
<div><label class="f">Bank name</label><input class="in" name="bank_name" value="{{ old('bank_name', $s->get('bank.name')) }}" placeholder="e.g. HDFC Bank"></div>
<div><label class="f">Account holder</label><input class="in" name="bank_account_name" value="{{ old('bank_account_name', $s->get('bank.account_name')) }}" placeholder="Account holder name"></div>
</div>
<div class="form-grid">
<div><label class="f">Account number</label><input class="in" name="bank_account_number" value="{{ old('bank_account_number', $s->get('bank.account_number')) }}" placeholder="Account number"></div>
<div><label class="f">IFSC</label><input class="in" name="bank_ifsc" value="{{ old('bank_ifsc', $s->get('bank.ifsc')) }}" placeholder="IFSC code"></div>
</div>
<div class="form-grid">
<div><label class="f">Branch</label><input class="in" name="bank_branch" value="{{ old('bank_branch', $s->get('bank.branch')) }}" placeholder="Branch (optional)"></div>
<div><label class="f">UPI ID</label><input class="in" name="bank_upi_id" value="{{ old('bank_upi_id', $s->get('bank.upi_id')) }}" placeholder="you@upi (optional)"></div>
</div></div>
<div class="card" style="max-width:720px"><h3>Lists & options</h3>
<p class="fhint">One value per line. These drive the dropdowns and pickers across expenses, payments and invoices.</p>
<div class="form-grid">
<div><label class="f">Expense categories</label><textarea class="in" name="expense_categories" rows="4">{{ old('expense_categories', $s->get('expenses.categories', "general\ntravel\nsoftware\nhosting\nsalary\nmarketing\noffice")) }}</textarea></div>
<div><label class="f">Expense pay methods</label><textarea class="in" name="expense_methods" rows="4">{{ old('expense_methods', $s->get('expenses.methods', "cash\nbank\nupi\ncard\nother")) }}</textarea></div>
</div>
<div class="form-grid">
<div><label class="f">Payment methods</label><textarea class="in" name="payment_methods" rows="4">{{ old('payment_methods', $s->get('payments.methods', "cash\nbank\nupi\nrazorpay\nother")) }}</textarea></div>
<div><label class="f">Invoice units</label><textarea class="in" name="invoice_units" rows="4">{{ old('invoice_units', $s->get('invoice.units', "NOS\nHRS\nDAY\nKGS\nMTR\nLTR\nSET\nJOB")) }}</textarea></div>
</div>
<div><label class="f">Lead sources</label><textarea class="in" name="lead_sources" rows="3" placeholder="One per line…">{{ old('lead_sources', $s->get('leads.sources', "website\nindiamart\njustdial\ngoogle\nreferral\nwalkin\ninstagram\nfacebook\nwhatsapp\ncoldcall\nother")) }}</textarea></div></div>
<div class="card" style="max-width:720px"><h3>Invoice document</h3>
<label class="f">Invoice logo <span class="hint">(falls back to brand logo)</span></label>@if($s->get('invoice.logo_path'))<img class="brand-preview" src="{{ asset('storage/'.$s->get('invoice.logo_path')) }}" alt="invoice logo preview">@endif<input class="in" type="file" name="invoice_logo" accept="image/*">
<label class="f">Invoice logo — dark mode <span class="hint">(optional, falls back to invoice logo)</span></label>@if($s->get('invoice.logo_dark_path'))<img class="brand-preview" src="{{ asset('storage/'.$s->get('invoice.logo_dark_path')) }}" alt="dark invoice logo preview" style="background:#1c1a15">@endif<input class="in" type="file" name="invoice_logo_dark" accept="image/*">
<label class="f">Footer text</label><textarea class="in" name="invoice_footer" rows="2">{{ $s->get('invoice.footer') }}</textarea>
<label class="f">Signature <span class="hint">(scanned sign, printed above Authorised Signatory)</span></label>@if($s->get('invoice.signature_path'))<img class="brand-preview" src="{{ asset('storage/'.$s->get('invoice.signature_path')) }}" alt="signature preview">@endif<input class="in" type="file" name="signature" accept="image/*">
<label class="f">Declaration (GST)</label><textarea class="in" name="invoice_declaration" rows="3">{{ $s->get('invoice.declaration','We declare that this invoice shows the actual particulars of the supply described and that all particulars are true and correct.') }}</textarea>
<label class="f">Default proposal terms</label><textarea class="in" name="proposal_terms" rows="3" placeholder="Prefilled on every new proposal…">{{ $s->get('proposal.terms') }}</textarea>
<div class="form-actions"><button class="btn" type="submit">Save billing settings</button><a class="btn sec" href="{{ route('settings.index') }}">Cancel</a></div></div>
</form>
@endsection
