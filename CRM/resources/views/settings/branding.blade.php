@extends('layouts.app')
@section('title','Branding')
@section('content')
<form method="POST" action="{{ route('settings.branding.store') }}" enctype="multipart/form-data">@csrf
<div class="card" style="max-width:720px"><h3>Brand identity</h3>
<p class="fhint">This is what your clients see — your name, not ours.</p>
<label class="f">App / website name <span class="req">*</span></label><input class="in" name="app_name" value="{{ old('app_name', $s->get('app.name', config('app.name'))) }}" required>
<label class="f">Company name <span class="req">*</span></label><input class="in" name="brand_company_name" value="{{ old('brand_company_name', $s->get('brand.company_name')) }}" required>
<label class="f">Tagline</label><input class="in" name="brand_tagline" value="{{ $s->get('brand.tagline') }}" placeholder="e.g. We build brands that grow">
<div class="form-grid">
<div><label class="f">Logo <span class="hint">(light backgrounds)</span></label>@if($s->get('brand.logo_path'))<img class="brand-preview" src="{{ asset('storage/'.$s->get('brand.logo_path')) }}" alt="logo preview">@endif<input class="in" type="file" name="logo" accept="image/*"></div>
<div><label class="f">Logo — dark mode <span class="hint">(optional, falls back to logo)</span></label>@if($s->get('brand.logo_dark_path'))<img class="brand-preview" src="{{ asset('storage/'.$s->get('brand.logo_dark_path')) }}" alt="dark logo preview" style="background:#1c1a15">@endif<input class="in" type="file" name="logo_dark" accept="image/*"></div>
<div><label class="f">Favicon</label>@if($s->get('brand.favicon_path'))<img class="brand-preview fav" src="{{ asset('storage/'.$s->get('brand.favicon_path')) }}" alt="favicon preview">@endif<input class="in" type="file" name="favicon" accept="image/*,.ico"></div>
</div></div>

@php
  $primaryNow = $s->get('brand.primary_color') ?: '#4f46e5';
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
@endphp

<link rel="stylesheet" href="{{ asset('assets/vendor/pickr/nano.min.css').'?v=1' }}">
<div class="card" style="max-width:720px"><h3>Brand colours</h3>
<p class="fhint">Pick a colour and watch the whole page re-theme instantly. Hit Save branding to keep it.</p>
<div class="form-grid">
@foreach($brandColours as $c)
@php $raw = $s->get('brand.'.$c['key']); $val = ($raw !== null && $raw !== '') ? $raw : ($c['always'] ? $c['def'] : ''); @endphp
<div><label class="f">{{ $c['label'] }}@if($c['hint']) <span class="hint">({{ $c['hint'] }})</span>@endif</label>
<div class="color-field" data-var="{{ $c['var'] }}" data-default="{{ $c['def'] }}"@if($c['accent']) data-accent="1"@endif>
<span class="color-btn" data-pickr title="Pick colour"></span>
<input class="in hex" type="text" name="{{ $c['name'] }}" value="{{ $val }}" placeholder="{{ $c['def'] }}" pattern="^#[0-9a-fA-F]{6}$" maxlength="7" autocomplete="off" spellcheck="false">
</div></div>
@endforeach
</div>
<label class="f">Login heading</label><input class="in" name="brand_login_heading" value="{{ $s->get('brand.login_heading') }}" placeholder="Leave blank for “Welcome to …”">
<label class="f">Login subtext</label><input class="in" name="brand_login_subtext" value="{{ $s->get('brand.login_subtext') }}" placeholder="One line under the heading"></div>

<div class="card" style="max-width:720px"><h3>Surfaces & text</h3>
<p class="fhint">Page, panels, text and borders across the app. Blank = design-system default.</p>
<div class="form-grid">
@foreach($surfaces as $sf)
@php $raw = $s->get('brand.'.$sf['key']); $val = ($raw !== null && $raw !== '') ? $raw : ''; @endphp
<div><label class="f">{{ $sf['label'] }}</label>
<div class="color-field" data-var="{{ $sf['var'] }}" data-default="{{ $sf['def'] }}">
<span class="color-btn" data-pickr title="Pick colour"></span>
<input class="in hex" type="text" name="{{ $sf['name'] }}" value="{{ $val }}" placeholder="{{ $sf['def'] }}" pattern="^#[0-9a-fA-F]{6}$" maxlength="7" autocomplete="off" spellcheck="false">
</div></div>
@endforeach
</div></div>

<div class="card" style="max-width:720px"><h3>Status colours</h3>
<p class="fhint">Success, warning, danger and info badges, dots and banners. Blank = design-system default.</p>
<div class="form-grid">
@foreach($statuses as $scc)
@php $raw = $s->get('brand.'.$scc['key']); $val = ($raw !== null && $raw !== '') ? $raw : ''; @endphp
<div><label class="f">{{ $scc['label'] }}</label>
<div class="color-field" data-var="{{ $scc['var'] }}" data-default="{{ $scc['def'] }}">
<span class="color-btn" data-pickr title="Pick colour"></span>
<input class="in hex" type="text" name="{{ $scc['name'] }}" value="{{ $val }}" placeholder="{{ $scc['def'] }}" pattern="^#[0-9a-fA-F]{6}$" maxlength="7" autocomplete="off" spellcheck="false">
</div></div>
@endforeach
</div>
<div id="contrast-warn" class="fhint" style="display:none;color:var(--dc-red)" role="alert">Warning: primary text on surface is below WCAG AA contrast (4.5:1). Consider a darker text or lighter surface.</div>
</div>

<div class="card" style="max-width:720px"><h3>Live preview</h3>
<p class="fhint">Updates as you pick — saving applies it everywhere: nav, buttons, links, forms, tables, badges, login, portal.</p>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px"><span class="btn brand">Primary action</span><span class="btn sec">Secondary</span><a href="#" onclick="return false">Sample link</a><span class="badge b-green">Paid</span><span class="badge b-amber">Pending</span><span class="badge b-red">Overdue</span><span class="badge b-blue">Sent</span></div>
<div style="display:flex;gap:8px;margin-bottom:12px"><input class="in" value="Sample input" style="margin:0;max-width:220px"><select class="in" style="margin:0;max-width:180px"><option>Select…</option></select></div>
<table class="tbl" style="margin:0"><thead><tr><th>Invoice</th><th style="text-align:right">Total</th><th>Status</th></tr></thead><tbody><tr><td>INV-0001</td><td style="text-align:right;font-variant-numeric:tabular-nums">₹12,500.00</td><td><span class="badge b-green">Paid</span></td></tr></tbody></table>
<div style="margin-top:12px"><button type="button" class="btn sec sm" id="brand-reset">Reset to defaults</button></div>
</div>

<div class="card" style="max-width:720px"><h3>Company & tax</h3>
<label class="f">Address</label><textarea class="in" name="company_address" rows="2">{{ $s->get('company.address') }}</textarea>
<div class="form-grid">
<div><label class="f">GSTIN</label><input class="in" name="company_gstin" value="{{ $s->get('company.gstin') }}"></div>
<div><label class="f">State (registered)</label><select class="in" name="company_state"><option value="">Select state</option>@foreach(indian_states() as $st)<option value="{{ $st }}" {{ $s->get('company.state')===$st?'selected':'' }}>{{ $st }}</option>@endforeach</select></div>
<div><label class="f">Phone</label><input class="in" type="tel" name="company_phone" value="{{ $s->get('company.phone') }}"></div>
</div>
<label class="f">Company email</label><input class="in" type="email" name="company_email" value="{{ $s->get('company.email') }}">
<label class="f">Website</label><input class="in" name="company_website" value="{{ $s->get('company.website') }}" placeholder="https://…"></div>

<div class="card" style="max-width:720px"><h3>Email sender</h3>
<p class="fhint">Used for invoice emails and password resets.</p>
<div class="form-grid">
<div><label class="f">From address</label><input class="in" type="email" name="mail_from_address" value="{{ $s->get('mail.from_address') }}" placeholder="hello@yourcompany.com"></div>
<div><label class="f">From name</label><input class="in" name="mail_from_name" value="{{ $s->get('mail.from_name') }}" placeholder="Your company"></div>
</div>
<div class="form-actions"><button class="btn" type="submit">Save branding</button><a class="btn sec" href="{{ route('settings.index') }}">Cancel</a></div></div>
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
    if(w){ w.style.display = contrast(ink,surf) < 4.5 ? 'block' : 'none'; }
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
