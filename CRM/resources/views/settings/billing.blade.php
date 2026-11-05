@extends('layouts.app')
@section('title','Billing')
@section('content')
@php
  $numberingMode = $s->get('numbering.mode','continuous');
  $invoicePrefix = $s->get('invoice.prefix','INV-');
  $currencies = preg_split('/\r\n|\r|\n/', (string) old('invoice_currencies', $s->get('invoice.currencies', "INR\nUSD\nEUR\nGBP\nAED")));
  $currencyCount = count(array_filter(array_map('trim', $currencies)));
  $lateOn = $s->get('billing.late_fee_enabled','0') === '1';
  $lateType = $s->get('billing.late_fee_type','flat');
  $lateValue = $s->get('billing.late_fee_value',500);
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h7M9 17h5"/></svg>
    </span>
    <div>
      <h1>Billing</h1>
      <div class="crumb">Numbering, document defaults, collections and bank details</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Settings
    </a>
    <button class="btn sm" type="submit" form="billing-form">Save billing</button>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Numbering</span>
    <b class="ix-metric-val">{{ $numberingMode === 'fy' ? 'Financial year' : 'Continuous' }}</b>
    <span class="ix-metric-sub">{{ $numberingMode === 'fy' ? 'resets every April' : 'never resets' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Invoice series</span>
    <b class="ix-metric-val st-metric-code">{{ $invoicePrefix }}</b>
    <span class="ix-metric-sub">{{ $numberingMode === 'fy' ? rtrim($invoicePrefix,'-_/ ').'/'.fy().'/001' : $invoicePrefix.'00001' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Invoice currencies</span>
    <b class="ix-metric-val">{{ $currencyCount }}</b>
    <span class="ix-metric-sub">selectable on new invoices</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Late fees</span>
    <b class="ix-metric-val">{{ $lateOn ? 'On' : 'Off' }}</b>
    <span class="ix-metric-sub">{{ $lateOn ? ($lateType === 'percent' ? $lateValue.'% of balance' : '₹'.number_format((float)$lateValue,0)) : 'not charged on overdue invoices' }}</span>
  </div>
</div>

<form id="billing-form" method="POST" action="{{ route('settings.billing.store') }}" enctype="multipart/form-data">@csrf

<div class="st-grid">
  <div class="card ix-card is-wide">
    <div class="card-head ix-card-head">
      <div><h3>Numbering</h3><span class="hint">Document prefixes, proposal defaults and number series.</span></div>
      <span class="ix-tag">Series</span>
    </div>
    <div class="form-grid is-three">
      <div><label class="f" for="bill-invoice-prefix">Invoice prefix</label><input class="in" id="bill-invoice-prefix" name="invoice_prefix" value="{{ $invoicePrefix }}"></div>
      <div><label class="f" for="bill-expense-prefix">Expense prefix</label><input class="in" id="bill-expense-prefix" name="expense_prefix" value="{{ $s->get('expense.prefix','EXP-') }}"></div>
      <div><label class="f" for="bill-payment-prefix">Receipt prefix</label><input class="in" id="bill-payment-prefix" name="payment_prefix" value="{{ $s->get('payments.prefix','PAY-') }}"></div>
      <div><label class="f" for="bill-proposal-prefix">Proposal prefix</label><input class="in" id="bill-proposal-prefix" name="proposal_prefix" value="{{ $s->get('proposal.prefix','PROP-') }}"></div>
      <div><label class="f" for="bill-credit-prefix">Credit note prefix</label><input class="in" id="bill-credit-prefix" name="credit_prefix" value="{{ $s->get('credit.prefix','CN-') }}"></div>
      <div><label class="f" for="bill-valid-days">Proposal valid for (days)</label><input class="in" id="bill-valid-days" type="number" min="1" max="365" name="proposal_valid_days" value="{{ $s->get('proposal.valid_days',15) }}"></div>
      <div><label class="f" for="bill-proposal-theme">Proposal theme</label><select class="in" id="bill-proposal-theme" name="proposal_theme"><option value="classic" {{ $s->get('proposal.theme','classic')==='classic'?'selected':'' }}>Classic — full boxes</option><option value="compact" {{ $s->get('proposal.theme')==='compact'?'selected':'' }}>Compact — dense, less ink</option></select></div>
      <div><label class="f" for="bill-discount">Staff discount limit (%) <span class="hint">— 0 = no limit</span></label><input class="in" id="bill-discount" type="number" step="0.01" min="0" max="100" name="proposal_discount_limit" value="{{ $s->get('proposal.discount_limit',20) }}"></div>
      <div><label class="f" for="bill-followup">Auto follow-up on day(s) <span class="hint">— blank = off</span></label><input class="in" id="bill-followup" name="proposal_followup_days" value="{{ $s->get('proposal.followup_days','3,7,14') }}" placeholder="e.g. 3,7,14"></div>
    </div>
    <label class="f" for="bill-currencies">Invoice currencies <span class="hint">— one per line</span></label>
    <textarea class="in" id="bill-currencies" name="invoice_currencies" rows="3">{{ old('invoice_currencies', $s->get('invoice.currencies', "INR\nUSD\nEUR\nGBP\nAED")) }}</textarea>
    <p class="fhint">New proposals auto-expire after the valid-for window.</p>

    <div class="fsec">Number series</div>
    <div class="st-checks">
      <label class="st-check">
        <input type="radio" name="numbering_mode" value="continuous" {{ $numberingMode==='continuous'?'checked':'' }}>
        <span><b>Continuous</b><br><span class="st-code">{{ $invoicePrefix }}00001</span> — never resets.</span>
      </label>
      <label class="st-check">
        <input type="radio" name="numbering_mode" value="fy" {{ $numberingMode==='fy'?'checked':'' }}>
        <span><b>Financial year</b><br><span class="st-code">{{ rtrim($invoicePrefix,'-_/ ') }}/{{ fy() }}/001</span> — resets every April.</span>
      </label>
    </div>
    <p class="fhint st-note">FY mode applies to invoices, payments, expenses, proposals, credit notes, purchase orders and challans. Existing numbers never change.</p>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>New invoice defaults</h3><span class="hint">Pre-filled on every new invoice.</span></div>
      <span class="ix-tag">Defaults</span>
    </div>
    <div class="form-grid">
      <div><label class="f" for="bill-due-days">Due after (days)</label><input class="in" id="bill-due-days" type="number" min="0" max="365" name="invoice_due_days" value="{{ $s->get('invoice.due_days',7) }}"></div>
      <div><label class="f" for="bill-invoice-theme">Invoice theme</label><select class="in" id="bill-invoice-theme" name="invoice_theme"><option value="classic" {{ $s->get('invoice.theme','classic')==='classic'?'selected':'' }}>Classic — full boxes</option><option value="compact" {{ $s->get('invoice.theme')==='compact'?'selected':'' }}>Compact — dense, less ink</option></select></div>
    </div>
    <label class="f" for="bill-payment-method">Default payment method</label>
    <select class="in" id="bill-payment-method" name="payments_default_method">@foreach(setting_lines('payments.methods',['cash','bank','upi','razorpay','other']) as $m)<option value="{{ $m }}" {{ $s->get('payments.default_method','upi')===$m?'selected':'' }}>{{ ucfirst($m) }}</option>@endforeach</select>
    <label class="f" for="bill-notes">Default notes</label>
    <textarea class="in" id="bill-notes" name="invoice_default_notes" rows="3" placeholder="Prefilled on every new invoice…">{{ $s->get('invoice.default_notes') }}</textarea>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Late fees</h3><span class="hint">One-click fee on overdue invoices — added as a zero-GST line so tax math stays untouched.</span></div>
      <span class="ix-state {{ $lateOn ? 'is-amber' : 'is-grey' }}"><i></i>{{ $lateOn ? 'Enabled' : 'Off' }}</span>
    </div>
    <div class="st-checks is-one">
      <label class="st-check">
        <input type="checkbox" name="late_fee_enabled" value="1" {{ $lateOn?'checked':'' }}>
        <span><b>Enable late fees</b><br>Applies once per invoice, after the grace period.</span>
      </label>
    </div>
    <div class="form-grid is-three">
      <div><label class="f" for="bill-late-type">Type</label><select class="in" id="bill-late-type" name="late_fee_type"><option value="flat" {{ $lateType==='flat'?'selected':'' }}>Flat amount (₹)</option><option value="percent" {{ $lateType==='percent'?'selected':'' }}>% of balance due</option></select></div>
      <div><label class="f" for="bill-late-value">Value</label><input class="in" id="bill-late-value" type="number" step="0.01" min="0" name="late_fee_value" value="{{ $lateValue }}"></div>
      <div><label class="f" for="bill-late-grace">Grace days after due date</label><input class="in" id="bill-late-grace" type="number" min="0" max="365" name="late_fee_grace_days" value="{{ $s->get('billing.late_fee_grace_days',0) }}"></div>
    </div>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Collection ladder</h3><span class="hint">Overdue reminders escalate: gentle → firm → final notice. Blank = every nudge stays gentle.</span></div>
      <span class="ix-tag">Dunning</span>
    </div>
    <label class="f" for="bill-dunning">Escalate on day(s) overdue</label>
    <input class="in" id="bill-dunning" name="collections_dunning_days" value="{{ $s->get('collections.dunning_days','7,15,30') }}" placeholder="e.g. 7,15,30">
    <p class="fhint st-note">Comma-separated day counts after the due date.</p>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Loyalty credits</h3><span class="hint">Clients earn a percentage of every receipt as loyalty balance, redeemable as a payment method. 0 = off.</span></div>
      <span class="ix-tag">Retention</span>
    </div>
    <label class="f" for="bill-loyalty">Earn rate (% of receipt)</label>
    <input class="in" id="bill-loyalty" type="number" step="0.01" min="0" max="100" name="loyalty_earn_percent" value="{{ $s->get('loyalty.earn_percent',0) }}">
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Payroll</h3><span class="hint">Overtime pays a multiple of hourly cost; salary TDS (192) is a flat % above the monthly standard slice.</span></div>
      <span class="ix-tag">People</span>
    </div>
    <div class="form-grid">
      <div><label class="f" for="bill-overtime">Overtime multiple</label><input class="in" id="bill-overtime" type="number" step="0.1" min="1" max="5" name="payroll_overtime_rate" value="{{ $s->get('payroll.overtime_rate',1.5) }}"></div>
      <div><label class="f" for="bill-tds">Salary TDS (%)</label><input class="in" id="bill-tds" type="number" step="0.01" min="0" max="30" name="payroll_tds_percent" value="{{ $s->get('payroll.tds_percent',10) }}"></div>
    </div>
  </div>

  <div class="card ix-card">
    <div class="card-head ix-card-head">
      <div><h3>Cheques</h3><span class="hint">Post-dated cheques surface in the morning digest this many days before their date.</span></div>
      <span class="ix-tag">Reminders</span>
    </div>
    <label class="f" for="bill-cheque-days">Digest alert window (days)</label>
    <input class="in" id="bill-cheque-days" type="number" min="1" max="90" name="cheque_alert_days" value="{{ $s->get('cheques.alert_days',7) }}">
  </div>
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Bank details</h3><span class="hint">Printed on every invoice so clients can pay by bank transfer. Leave blank to hide.</span></div>
    <span class="ix-tag">Payments</span>
  </div>
  <div class="form-grid is-three">
    <div><label class="f" for="bill-bank-name">Bank name</label><input class="in" id="bill-bank-name" name="bank_name" value="{{ old('bank_name', $s->get('bank.name')) }}" placeholder="e.g. HDFC Bank"></div>
    <div><label class="f" for="bill-bank-holder">Account holder</label><input class="in" id="bill-bank-holder" name="bank_account_name" value="{{ old('bank_account_name', $s->get('bank.account_name')) }}" placeholder="Account holder name"></div>
    <div><label class="f" for="bill-bank-number">Account number</label><input class="in" id="bill-bank-number" name="bank_account_number" value="{{ old('bank_account_number', $s->get('bank.account_number')) }}" placeholder="Account number"></div>
    <div><label class="f" for="bill-bank-ifsc">IFSC</label><input class="in" id="bill-bank-ifsc" name="bank_ifsc" value="{{ old('bank_ifsc', $s->get('bank.ifsc')) }}" placeholder="IFSC code"></div>
    <div><label class="f" for="bill-bank-branch">Branch</label><input class="in" id="bill-bank-branch" name="bank_branch" value="{{ old('bank_branch', $s->get('bank.branch')) }}" placeholder="Branch (optional)"></div>
    <div><label class="f" for="bill-bank-upi">UPI ID</label><input class="in" id="bill-bank-upi" name="bank_upi_id" value="{{ old('bank_upi_id', $s->get('bank.upi_id')) }}" placeholder="you@upi (optional)"></div>
  </div>
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Lists &amp; options</h3><span class="hint">One value per line. These drive the dropdowns and pickers across expenses, payments and invoices.</span></div>
    <span class="ix-tag">Pickers</span>
  </div>
  <div class="form-grid">
    <div><label class="f" for="bill-expense-categories">Expense categories</label><textarea class="in" id="bill-expense-categories" name="expense_categories" rows="4">{{ old('expense_categories', $s->get('expenses.categories', "general\ntravel\nsoftware\nhosting\nsalary\nmarketing\noffice")) }}</textarea></div>
    <div><label class="f" for="bill-expense-methods">Expense pay methods</label><textarea class="in" id="bill-expense-methods" name="expense_methods" rows="4">{{ old('expense_methods', $s->get('expenses.methods', "cash\nbank\nupi\ncard\nother")) }}</textarea></div>
    <div><label class="f" for="bill-payment-methods">Payment methods</label><textarea class="in" id="bill-payment-methods" name="payment_methods" rows="4">{{ old('payment_methods', $s->get('payments.methods', "cash\nbank\nupi\nrazorpay\nother")) }}</textarea></div>
    <div><label class="f" for="bill-units">Invoice units</label><textarea class="in" id="bill-units" name="invoice_units" rows="4">{{ old('invoice_units', $s->get('invoice.units', "NOS\nHRS\nDAY\nKGS\nMTR\nLTR\nSET\nJOB")) }}</textarea></div>
  </div>
  <label class="f" for="bill-lead-sources">Lead sources</label>
  <textarea class="in" id="bill-lead-sources" name="lead_sources" rows="3" placeholder="One per line…">{{ old('lead_sources', $s->get('leads.sources', "website\nindiamart\njustdial\ngoogle\nreferral\nwalkin\ninstagram\nfacebook\nwhatsapp\ncoldcall\nother")) }}</textarea>
</div>

<div class="card ix-card">
  <div class="card-head ix-card-head">
    <div><h3>Invoice document</h3><span class="hint">Logo, footer, signature and declaration printed on invoices and proposals.</span></div>
    <span class="ix-tag">Document</span>
  </div>

  <div class="st-grid is-inner">
    <div>
      <label class="f" for="bill-invoice-logo">Invoice logo <span class="hint">— falls back to brand logo</span></label>
      @if($s->get('invoice.logo_path'))<img class="brand-preview" src="{{ asset('storage/'.$s->get('invoice.logo_path')) }}" alt="invoice logo preview">@endif
      <input class="in" id="bill-invoice-logo" type="file" name="invoice_logo" accept="image/*">
    </div>
    <div>
      <label class="f" for="bill-invoice-logo-dark">Invoice logo — dark mode <span class="hint">— optional, falls back to invoice logo</span></label>
      @if($s->get('invoice.logo_dark_path'))<img class="brand-preview" src="{{ asset('storage/'.$s->get('invoice.logo_dark_path')) }}" alt="dark invoice logo preview" style="background:#1c1a15">@endif
      <input class="in" id="bill-invoice-logo-dark" type="file" name="invoice_logo_dark" accept="image/*">
    </div>
  </div>

  <label class="f" for="bill-footer">Footer text</label>
  <textarea class="in" id="bill-footer" name="invoice_footer" rows="2">{{ $s->get('invoice.footer') }}</textarea>

  <label class="f" for="bill-signature">Signature <span class="hint">— scanned sign, printed above Authorised Signatory</span></label>
  @if($s->get('invoice.signature_path'))<img class="brand-preview" src="{{ asset('storage/'.$s->get('invoice.signature_path')) }}" alt="signature preview">@endif
  <input class="in" id="bill-signature" type="file" name="signature" accept="image/*">

  <label class="f" for="bill-declaration">Declaration (GST)</label>
  <textarea class="in" id="bill-declaration" name="invoice_declaration" rows="3">{{ $s->get('invoice.declaration','We declare that this invoice shows the actual particulars of the supply described and that all particulars are true and correct.') }}</textarea>

  <label class="f" for="bill-terms">Default proposal terms</label>
  <textarea class="in" id="bill-terms" name="proposal_terms" rows="3" placeholder="Prefilled on every new proposal…">{{ $s->get('proposal.terms') }}</textarea>
</div>

<div class="card ix-card st-card">
  <div class="st-actions">
    <div class="st-actions-txt">
      <strong>Save billing settings</strong>
      <span>Numbering, defaults, collections, bank details and document layout.</span>
    </div>
    <div class="st-actions-btns">
      <a class="btn sec" href="{{ route('settings.index') }}">Cancel</a>
      <button class="btn" type="submit">Save billing settings</button>
    </div>
  </div>
</div>
</form>
@endsection
