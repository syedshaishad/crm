@extends('layouts.app')
@section('title','Branding')
@section('head')
<link rel="stylesheet" href="{{ asset('assets/vendor/pickr/nano.min.css').'?v=1' }}">
@endsection
@section('content')
@php
  $isHex = fn ($v) => (bool) preg_match('/^#[0-9a-fA-F]{6}$/', trim((string) $v));
  $hexOr = fn ($v, $fallback) => $isHex($v) ? strtolower(trim($v)) : $fallback;
  $colourVal = function (string $key, bool $always = false, string $def = '') use ($s, $isHex) {
      $raw = trim((string) $s->get('brand.'.$key));
      if ($isHex($raw)) { return strtolower($raw); }
      return $always ? $def : '';
  };

  $primaryNow = $hexOr($s->get('brand.primary_color'), '#4f46e5');
  $accentNow  = $hexOr($s->get('brand.accent_color'), '#06b6d4');

  $brandColours = [
    ['name'=>'brand_primary_color','key'=>'primary_color','label'=>'Primary colour','hint'=>'','def'=>'#4f46e5','var'=>'--primary','accent'=>true,'always'=>true],
    ['name'=>'brand_accent_color','key'=>'accent_color','label'=>'Accent colour','hint'=>'','def'=>'#06b6d4','var'=>'--accent','accent'=>false,'always'=>true],
    ['name'=>'brand_link_color','key'=>'link_color','label'=>'Link colour','hint'=>'blank = primary','def'=>$primaryNow,'var'=>'--link','accent'=>false,'always'=>false],
  ];
  $surfaces = [
    ['name'=>'brand_background_color','key'=>'background_color','label'=>'Page background','var'=>'--dc-page','def'=>'#e6e2e2'],
    ['name'=>'brand_sidebar_bg','key'=>'sidebar_bg','label'=>'App frame','var'=>'--dc-shell','def'=>'#f3f1f1'],
    ['name'=>'brand_surface_color','key'=>'surface_color','label'=>'Panel / card','var'=>'--dc-panel','def'=>'#ffffff'],
    ['name'=>'brand_header_bg','key'=>'header_bg','label'=>'Soft surface','var'=>'--dc-soft','def'=>'#f6f4f4'],
    ['name'=>'brand_text_color','key'=>'text_color','label'=>'Primary text','var'=>'--dc-ink','def'=>'#161313'],
    ['name'=>'brand_text_secondary','key'=>'text_secondary','label'=>'Muted text','var'=>'--dc-muted','def'=>'#6f6a6a'],
    ['name'=>'brand_sidebar_text','key'=>'sidebar_text','label'=>'Frame text','var'=>'--dc-ink2','def'=>'#2a2626'],
    ['name'=>'brand_border_color','key'=>'border_color','label'=>'Hairline border','var'=>'--dc-line','def'=>'#e6e1e1'],
  ];
  $statuses = [
    ['name'=>'brand_success_color','key'=>'success_color','label'=>'Success','var'=>'--brand-success','def'=>'#1f9d6a'],
    ['name'=>'brand_warning_color','key'=>'warning_color','label'=>'Warning','var'=>'--brand-warning','def'=>'#f0b429'],
    ['name'=>'brand_danger_color','key'=>'danger_color','label'=>'Danger','var'=>'--brand-danger','def'=>'#d64545'],
    ['name'=>'brand_info_color','key'=>'info_color','label'=>'Info','var'=>'--brand-info','def'=>'#0a84ff'],
  ];

  $tokenCount = count($brandColours) + count($surfaces) + count($statuses);
  $customCount = 0;
  foreach (array_merge($brandColours, $surfaces, $statuses) as $t) {
      $raw = strtolower(trim((string) $s->get('brand.'.$t['key'])));
      if ($isHex($raw) && $raw !== strtolower($t['def'])) { $customCount++; }
  }

  $logoPath  = $s->get('brand.logo_path');
  $logoDark  = $s->get('brand.logo_dark_path');
  $favicon   = $s->get('brand.favicon_path');
  $logoCount = count(array_filter([$logoPath, $logoDark, $favicon]));
  $company   = $s->get('brand.company_name') ?: $s->get('app.name', config('app.name'));
  $loginHead = $s->get('brand.login_heading') ?: 'Welcome to '.$company;
@endphp

<div class="page-head ix-head">
  <div class="ix-head-main">
    <span class="ix-head-ic">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 0 18h1.4a2 2 0 0 0 1.4-3.4 2 2 0 0 1 1.4-3.4H18a3 3 0 0 0 3-3A9 9 0 0 0 12 3Z"/><circle cx="7.5" cy="10.5" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="14.5" cy="7.5" r="1"/><circle cx="7.8" cy="15" r="1"/></svg>
    </span>
    <div>
      <h1>Branding</h1>
      <div class="crumb">{{ $company }} · {{ $customCount === 0 ? 'design-default colours' : $customCount.' of '.$tokenCount.' colour tokens customised' }} · {{ $logoPath ? 'logo ready' : 'no logo yet' }}</div>
    </div>
  </div>
  <div class="ix-head-actions noprint">
    <a class="btn sm sec" href="{{ route('settings.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      Settings
    </a>
    <button class="btn sm" type="submit" form="branding-form">Save branding</button>
  </div>
</div>

<div class="ix-metrics">
  <div class="ix-metric">
    <span class="ix-metric-lbl">Primary colour</span>
    <b class="ix-metric-val br-metric-hex"><i class="br-swatch" style="background:{{ $primaryNow }}"></i>{{ $primaryNow }}</b>
    <span class="ix-metric-sub">buttons, links &amp; highlights</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Accent colour</span>
    <b class="ix-metric-val br-metric-hex"><i class="br-swatch" style="background:{{ $accentNow }}"></i>{{ $accentNow }}</b>
    <span class="ix-metric-sub">secondary emphasis</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Colour overrides</span>
    <b class="ix-metric-val">{{ $customCount }}<span class="br-metric-of">/{{ $tokenCount }}</span></b>
    <span class="ix-metric-sub">{{ $customCount === 0 ? 'running on design defaults' : 'tokens changed from defaults' }}</span>
  </div>
  <div class="ix-metric">
    <span class="ix-metric-lbl">Logo assets</span>
    <b class="ix-metric-val">{{ $logoCount }}<span class="br-metric-of">/3</span></b>
    <span class="ix-metric-sub">{{ $logoCount === 3 ? 'logo, dark logo & favicon' : ($logoCount === 0 ? 'upload a logo to begin' : 'add the remaining files') }}</span>
  </div>
</div>

<form id="branding-form" method="POST" action="{{ route('settings.branding.store') }}" enctype="multipart/form-data">@csrf

<div class="card ix-card br-card">
  <div class="card-head ix-card-head">
    <div><h3>Brand identity</h3><span class="hint">This is what your clients see — your name, not ours.</span></div>
    <span class="ix-tag">Identity</span>
  </div>

  <div class="form-grid">
    <div>
      <label class="f" for="br-app-name">App / website name <span class="req">*</span></label>
      <input class="in" id="br-app-name" name="app_name" value="{{ old('app_name', $s->get('app.name', config('app.name'))) }}" required autocomplete="organization">
    </div>
    <div>
      <label class="f" for="br-company-name">Company name <span class="req">*</span></label>
      <input class="in" id="br-company-name" name="brand_company_name" value="{{ old('brand_company_name', $s->get('brand.company_name')) }}" required>
    </div>
  </div>

  <label class="f" for="br-tagline">Tagline</label>
  <input class="in" id="br-tagline" name="brand_tagline" value="{{ $s->get('brand.tagline') }}" placeholder="e.g. We build brands that grow">

  <div class="br-tiles">
    <div class="br-tile">
      <div class="br-tile-top"><span class="f">Logo</span><span class="hint">Light backgrounds</span></div>
      <div class="br-shot">
        @if($logoPath)<img src="{{ asset('storage/'.$logoPath) }}" alt="Current logo">@else<span class="br-shot-empty">No logo yet</span>@endif
      </div>
      <input class="in br-file" type="file" name="logo" accept="image/*" aria-label="Upload logo">
    </div>
    <div class="br-tile">
      <div class="br-tile-top"><span class="f">Logo — dark</span><span class="hint">Optional</span></div>
      <div class="br-shot is-dark">
        @if($logoDark)<img src="{{ asset('storage/'.$logoDark) }}" alt="Current dark logo">@else<span class="br-shot-empty">Falls back to logo</span>@endif
      </div>
      <input class="in br-file" type="file" name="logo_dark" accept="image/*" aria-label="Upload dark mode logo">
    </div>
    <div class="br-tile">
      <div class="br-tile-top"><span class="f">Favicon</span><span class="hint">Browser tab</span></div>
      <div class="br-shot is-fav">
        @if($favicon)<img src="{{ asset('storage/'.$favicon) }}" alt="Current favicon">@else<span class="br-shot-empty">No icon</span>@endif
      </div>
      <input class="in br-file" type="file" name="favicon" accept="image/*,.ico" aria-label="Upload favicon">
    </div>
  </div>

  <div class="form-grid">
    <div>
      <label class="f" for="br-login-heading">Login heading</label>
      <input class="in" id="br-login-heading" name="brand_login_heading" value="{{ $s->get('brand.login_heading') }}" placeholder="Leave blank for “Welcome to …”">
    </div>
    <div>
      <label class="f" for="br-login-subtext">Login subtext</label>
      <input class="in" id="br-login-subtext" name="brand_login_subtext" value="{{ $s->get('brand.login_subtext') }}" placeholder="One line under the heading">
    </div>
  </div>
</div>

<div class="br-grid">
  <div class="card ix-card br-card">
    <div class="card-head ix-card-head">
      <div><h3>Brand colours</h3><span class="hint">Pick a colour and watch the page re-theme instantly.</span></div>
    </div>
    <div class="br-colours">
      @foreach($brandColours as $c)
        @php $val = $colourVal($c['key'], $c['always'], $c['def']); @endphp
        <div class="br-color">
          <div class="br-color-top">
            <label class="f" for="c-{{ $c['name'] }}">{{ $c['label'] }}</label>
            @if($c['hint'] !== '')<span class="hint">{{ $c['hint'] }}</span>@endif
          </div>
          <div class="color-field" data-var="{{ $c['var'] }}" data-default="{{ $c['def'] }}"@if($c['accent']) data-accent="1"@endif>
            <span class="color-btn" data-pickr title="Pick {{ strtolower($c['label']) }}"></span>
            <input class="in hex" id="c-{{ $c['name'] }}" type="text" name="{{ $c['name'] }}" value="{{ $val }}" placeholder="{{ $c['def'] }}" pattern="^#[0-9a-fA-F]{6}$" maxlength="7" autocomplete="off" spellcheck="false">
          </div>
          <span class="br-token">{{ $c['var'] }}</span>
        </div>
      @endforeach
    </div>
  </div>

  <div class="card ix-card br-card">
    <div class="card-head ix-card-head">
      <div><h3>Status colours</h3><span class="hint">Badges, dots and banners. Blank = design-system default.</span></div>
    </div>
    <div class="br-colours">
      @foreach($statuses as $sc)
        @php $val = $colourVal($sc['key']); @endphp
        <div class="br-color">
          <div class="br-color-top"><label class="f" for="c-{{ $sc['name'] }}">{{ $sc['label'] }}</label></div>
          <div class="color-field" data-var="{{ $sc['var'] }}" data-default="{{ $sc['def'] }}">
            <span class="color-btn" data-pickr title="Pick {{ strtolower($sc['label']) }}"></span>
            <input class="in hex" id="c-{{ $sc['name'] }}" type="text" name="{{ $sc['name'] }}" value="{{ $val }}" placeholder="{{ $sc['def'] }}" pattern="^#[0-9a-fA-F]{6}$" maxlength="7" autocomplete="off" spellcheck="false">
          </div>
          <span class="br-token">{{ $sc['var'] }}</span>
        </div>
      @endforeach
    </div>
    <div id="contrast-warn" class="br-warn" role="alert">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>
      <span>Primary text on the panel surface is below WCAG AA contrast (4.5:1). Consider a darker text or a lighter surface.</span>
    </div>
  </div>
</div>

<div class="card ix-card br-card">
  <div class="card-head ix-card-head">
    <div><h3>Surfaces &amp; text</h3><span class="hint">Page, panels, text and borders across the app. Blank = design-system default.</span></div>
    <span class="ix-tag">{{ count($surfaces) }} tokens</span>
  </div>
  <div class="br-colours is-4">
    @foreach($surfaces as $sf)
      @php $val = $colourVal($sf['key']); @endphp
      <div class="br-color">
        <div class="br-color-top"><label class="f" for="c-{{ $sf['name'] }}">{{ $sf['label'] }}</label></div>
        <div class="color-field" data-var="{{ $sf['var'] }}" data-default="{{ $sf['def'] }}">
          <span class="color-btn" data-pickr title="Pick {{ strtolower($sf['label']) }}"></span>
          <input class="in hex" id="c-{{ $sf['name'] }}" type="text" name="{{ $sf['name'] }}" value="{{ $val }}" placeholder="{{ $sf['def'] }}" pattern="^#[0-9a-fA-F]{6}$" maxlength="7" autocomplete="off" spellcheck="false">
        </div>
        <span class="br-token">{{ $sf['var'] }}</span>
      </div>
    @endforeach
  </div>
</div>

<div class="card ix-card br-card">
  <div class="card-head ix-card-head">
    <div><h3>Live preview</h3><span class="hint">Saving applies this everywhere: nav, buttons, links, forms, tables, badges, login and portal.</span></div>
    <span class="ix-state is-green"><i></i>Live theme</span>
  </div>

  <div class="br-preview">
    <div class="br-preview-top">
      <span class="br-preview-brand"><i></i>{{ $company }}</span>
      <span class="hint">{{ $loginHead }}</span>
    </div>

    <div class="br-preview-chips">
      <span class="ix-chip on"><i style="background:var(--dc-accent)"></i>Active <b>3</b></span>
      <span class="ix-chip"><i style="background:var(--dc-green)"></i>Paid <b>12</b></span>
      <span class="ix-chip"><i style="background:var(--dc-amber)"></i>Pending <b>4</b></span>
      <span class="ix-chip"><i style="background:var(--dc-red)"></i>Overdue <b>1</b></span>
    </div>

    <div class="br-preview-row">
      <button type="button" class="btn sm brand">Primary action</button>
      <button type="button" class="btn sm sec">Secondary</button>
      <a class="br-preview-link" href="#" onclick="return false">Sample link</a>
      <span class="ix-state is-green"><i></i>Paid</span>
      <span class="ix-state is-amber"><i></i>Pending</span>
      <span class="ix-state is-red"><i></i>Overdue</span>
    </div>

    <div class="br-preview-form">
      <input class="in" type="text" value="Sample input" aria-label="Sample input">
      <select class="in" aria-label="Sample select"><option>Select…</option><option>Option two</option></select>
      <input class="in" type="text" value="₹12,500.00" aria-label="Sample amount">
    </div>

    <div class="br-preview-scroll">
      <table class="tbl ix-table br-preview-table">
        <thead><tr><th class="ix-c-main">Invoice</th><th class="ix-c-client">Client</th><th class="ix-c-value">Total</th><th class="ix-c-status">Status</th></tr></thead>
        <tbody>
          <tr>
            <td class="ix-c-main"><span class="ix-name">INV-0001</span><span class="ix-sub">Due in 14 days</span></td>
            <td class="ix-c-client">Acme Studios</td>
            <td class="ix-c-value"><span class="ix-money">₹12,500.00</span></td>
            <td class="ix-c-status"><span class="ix-state is-green"><i></i>Paid</span></td>
          </tr>
          <tr>
            <td class="ix-c-main"><span class="ix-name">INV-0002</span><span class="ix-sub">Sent 3 days ago</span></td>
            <td class="ix-c-client">Northwind Co.</td>
            <td class="ix-c-value"><span class="ix-money">₹8,200.00</span></td>
            <td class="ix-c-status"><span class="ix-state is-amber"><i></i>Pending</span></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="br-preview-foot">
      <span class="hint">Preview only — nothing here is saved as content.</span>
      <button type="button" class="btn sec sm" id="brand-reset">Reset colours to defaults</button>
    </div>
  </div>
</div>

<div class="br-grid">
  <div class="card ix-card br-card">
    <div class="card-head ix-card-head">
      <div><h3>Company &amp; tax</h3><span class="hint">Printed on invoices and documents.</span></div>
    </div>
    <label class="f" for="br-address">Address</label>
    <textarea class="in" id="br-address" name="company_address" rows="2">{{ $s->get('company.address') }}</textarea>
    <div class="form-grid">
      <div>
        <label class="f" for="br-gstin">GSTIN</label>
        <input class="in" id="br-gstin" name="company_gstin" value="{{ $s->get('company.gstin') }}">
      </div>
      <div>
        <label class="f" for="br-state">State (registered)</label>
        <select class="in" id="br-state" name="company_state">
          <option value="">Select state</option>
          @foreach(indian_states() as $st)<option value="{{ $st }}" {{ $s->get('company.state') === $st ? 'selected' : '' }}>{{ $st }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="f" for="br-phone">Phone</label>
        <input class="in" id="br-phone" type="tel" name="company_phone" value="{{ $s->get('company.phone') }}">
      </div>
      <div>
        <label class="f" for="br-company-email">Company email</label>
        <input class="in" id="br-company-email" type="email" name="company_email" value="{{ $s->get('company.email') }}">
      </div>
    </div>
    <label class="f" for="br-website">Website</label>
    <input class="in" id="br-website" name="company_website" value="{{ $s->get('company.website') }}" placeholder="https://…">
  </div>

  <div class="card ix-card br-card">
    <div class="card-head ix-card-head">
      <div><h3>Email sender</h3><span class="hint">Used for invoice emails and password resets.</span></div>
    </div>
    <div class="form-grid">
      <div>
        <label class="f" for="br-mail-from">From address</label>
        <input class="in" id="br-mail-from" type="email" name="mail_from_address" value="{{ $s->get('mail.from_address') }}" placeholder="hello@yourcompany.com">
      </div>
      <div>
        <label class="f" for="br-mail-name">From name</label>
        <input class="in" id="br-mail-name" name="mail_from_name" value="{{ $s->get('mail.from_name') }}" placeholder="Your company">
      </div>
    </div>
    <p class="fhint br-note">The sender is used for invoice emails, reminders and password resets. Leave blank to use the server default.</p>
  </div>
</div>

<div class="card ix-card br-save-card">
  <div class="br-save">
    <div class="br-save-txt">
      <strong>Ready to apply?</strong>
      <span>Save once and the theme updates the app, login screen and client portal.</span>
    </div>
    <div class="br-save-actions">
      <a class="btn sec" href="{{ route('settings.index') }}">Cancel</a>
      <button class="btn" type="submit">Save branding</button>
    </div>
  </div>
</div>
</form>

<script src="{{ asset('assets/vendor/pickr/pickr.min.js').'?v=1' }}"></script>
<script>
(function () {
  var doc = document.documentElement;
  function isHex(v) { return /^#[0-9a-fA-F]{6}$/.test(v); }
  var presets = ['#4f46e5', '#0a84ff', '#06b6d4', '#30d158', '#ff9f0a', '#ff6482', '#bf5af2', '#111111'];
  try {
    var saved = JSON.parse(localStorage.getItem('crmSwatches') || '[]');
    if (Array.isArray(saved) && saved.length) presets = saved.concat(presets).slice(0, 12);
  } catch (e) {}
  function lum(hex){var c=hex.replace('#','');if(c.length!==6)return 0;var r=parseInt(c.substr(0,2),16)/255,g=parseInt(c.substr(2,2),16)/255,b=parseInt(c.substr(4,2),16)/255;function f(x){return x<=0.03928?x/12.92:Math.pow((x+0.055)/1.055,2.4);}return 0.2126*f(r)+0.7152*f(g)+0.0722*f(b);}
  function contrast(a,b){var l1=lum(a),l2=lum(b);var hi=Math.max(l1,l2),lo=Math.min(l1,l2);return (hi+0.05)/(lo+0.05);}
  function checkContrast(){
    var ink=((document.querySelector('input[name="brand_text_color"]')||{}).value||'').trim()||'#161313';
    var surf=((document.querySelector('input[name="brand_surface_color"]')||{}).value||'').trim()||'#ffffff';
    if(!isHex(ink)||!isHex(surf))return;
    var w=document.getElementById('contrast-warn');
    if(w){ w.style.display = contrast(ink,surf) < 4.5 ? 'flex' : 'none'; }
  }
  var HAS_PICKR = (typeof Pickr !== 'undefined');
  document.querySelectorAll('.color-field[data-var]').forEach(function (field) {
    var host = field.querySelector('.color-btn');
    var hex = field.querySelector('input.hex'); if (!hex) return;
    var vr = field.getAttribute('data-var');
    var def = field.getAttribute('data-default') || '#000000';
    var isAccent = field.hasAttribute('data-accent');
    var cur = isHex(hex.value) ? hex.value.toLowerCase() : def;
    var pickr = null;
    function paint(v, live, fromPickr) {
      v = String(v).toLowerCase();
      hex.value = v;
      if (pickr && !fromPickr) { try { pickr.setColor(v); } catch (e) {} }
      if (live !== false) {
        doc.style.setProperty(vr, v);
        if (isAccent) doc.style.setProperty('--brand-accent', v);
        checkContrast();
      }
    }
    if (HAS_PICKR && host) {
      pickr = Pickr.create({ el: host, theme: 'nano', default: cur, swatches: presets,
        components: { preview: true, opacity: false, hue: true, interaction: { hex: true, input: true, save: true } } });
      field.__pickr = pickr;
      pickr.on('save', function (c) {
        paint(c.toHEXA().toString(), true, true);
        pickr.hide();
        try {
          var l = JSON.parse(localStorage.getItem('crmSwatches') || '[]').filter(function (x) { return x !== hex.value; });
          l.unshift(hex.value); localStorage.setItem('crmSwatches', JSON.stringify(l.slice(0, 12)));
        } catch (e) {}
      });
    }
    hex.addEventListener('change', function () {
      var v = hex.value.trim();
      if (isHex(v)) { paint(v); } else { hex.value = ''; }
    });
  });
  var rs = document.getElementById('brand-reset');
  if (rs) rs.addEventListener('click', function () {
    document.querySelectorAll('.color-field[data-var]').forEach(function (field) {
      var hex = field.querySelector('input.hex');
      var def = field.getAttribute('data-default'); var vr = field.getAttribute('data-var');
      if (hex) hex.value = def;
      doc.style.setProperty(vr, def);
      if (field.hasAttribute('data-accent')) doc.style.setProperty('--brand-accent', def);
      if (field.__pickr) { try { field.__pickr.setColor(def); } catch (e) {} }
    });
    checkContrast();
  });
  checkContrast();
})();
</script>
@endsection
