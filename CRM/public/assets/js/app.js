// Standalone CRM UI helpers — vanilla JS, no dependencies.
(function () {
  // Snippet library: drop a boilerplate paragraph into a proposal textarea.
  document.addEventListener('change', function (e) {
    var sel = e.target.closest ? e.target.closest('.snippet-pick') : null;
    if (!sel || !sel.value) return;
    var body = sel.options[sel.selectedIndex].getAttribute('data-body') || '';
    var form = sel.closest('form');
    var ta = form ? form.querySelector('textarea[name="' + sel.getAttribute('data-field') + '"]') : null;
    if (ta && body) ta.value = (ta.value.trim() ? ta.value.replace(/\s+$/, '') + "\n" : '') + body;
    sel.value = '';
  });
  // Central motion gate: every JS-driven flourish checks this (CSS-only
  // motion is covered by the prefers-reduced-motion kill-switch in app.css).
  function motionOK() {
    try { return !window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
    catch (e) { return true; }
  }
  // Mobile nav (pill tabs collapse under the hamburger)
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-nav-toggle]')) {
      // Desktop: the same hamburger collapses the sidebar to an icon rail
      // (like the template's Toggle Sidebar); mobile: off-canvas drawer.
      if (window.innerWidth > 1024) {
        var rail = document.body.classList.toggle('side-min');
        try { localStorage.setItem('siderail', rail ? '1' : '0'); } catch (x) {}
      } else document.body.classList.toggle('nav-open');
    }
    if (e.target.closest('.dc-tr a, .dc-rl a')) document.body.classList.remove('nav-open');
    // Grouped nav: tap the parent to expand its submenu
    var dt = e.target.closest('[data-drop-toggle]');
    if (dt) {
      var wrap = dt.closest('.has-drop');
      var was = wrap.classList.contains('open');
      document.querySelectorAll('.has-drop.open').forEach(function (o) { o.classList.remove('open'); });
      if (!was) wrap.classList.add('open');
      e.preventDefault();
      return;
    }
    if (!e.target.closest('.has-drop')) document.querySelectorAll('.has-drop.open').forEach(function (o) { o.classList.remove('open'); });
  });
  // Hover menus: keep the panel open while the pointer crosses the trigger gap.
  document.querySelectorAll('.has-drop').forEach(function (wrap) {
    var hoverTimer = null;
    wrap.addEventListener('mouseenter', function () {
      if (hoverTimer) { clearTimeout(hoverTimer); hoverTimer = null; }
      wrap.classList.add('hovering');
    });
    wrap.addEventListener('mouseleave', function () {
      if (hoverTimer) clearTimeout(hoverTimer);
      hoverTimer = setTimeout(function () { wrap.classList.remove('hovering'); hoverTimer = null; }, 220);
    });
  });
  // Auto-dismiss flash messages
  setTimeout(function () {
    document.querySelectorAll('[data-flash]').forEach(function (el) {
      el.style.transition = 'opacity .5s'; el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 550);
    });
  }, 5000);

  // Animated counters: stat + hero numbers count up on load (skipped for reduced motion).
  try {
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      document.querySelectorAll('.stat b').forEach(function (el) {
        if (el.querySelector('*')) return; // rich content (badges): leave alone
        var txt = (el.textContent || '').trim();
        var m = txt.match(/^(-?[^\d]*)([\d,]+(?:\.\d+)?)(.*)$/);
        if (!m) return;
        var target = parseFloat(m[2].replace(/,/g, ''));
        if (!isFinite(target)) return;
        var dec = (m[2].split('.')[1] || '').length;
        var pre = m[1], suf = m[3];
        var t0 = null, dur = 750;
        function fmt(n) { return n.toLocaleString('en-US', {minimumFractionDigits: dec, maximumFractionDigits: dec}); }
        function step(t) {
          if (!t0) t0 = t;
          var k = Math.min(1, (t - t0) / dur);
          var e = 1 - Math.pow(1 - k, 3);
          el.textContent = pre + fmt(target * e) + suf;
          if (k < 1) requestAnimationFrame(step);
          else el.textContent = txt; // settle on the exact server-rendered value
        }
        requestAnimationFrame(step);
      });
    }
  } catch (e) {}

  // Designed confirm dialog (replaces every native confirm()).
  // Any form with [data-confirm] pauses here; optional tone/title/label via
  // data-confirm-tone="danger|brand", data-confirm-title, data-confirm-ok.
  // Any link with [data-confirm-link="formId"] confirms then submits that form.
  var pendingConfirm = null;
  function openConfirm(msg, opts) {
    var modal = document.getElementById('confirm-modal');
    if (!modal) return false;
    opts = opts || {};
    var tone = opts.tone || (/^\s*(delete|remove|decline)/i.test(msg || '') ? 'danger' : 'brand');
    var card = modal.querySelector('.confirm-card');
    var title = modal.querySelector('#confirm-title');
    var ok = modal.querySelector('#confirm-ok');
    card.classList.toggle('tone-brand', tone !== 'danger');
    title.textContent = opts.title || (tone === 'danger' ? 'Are you sure?' : 'Please confirm');
    modal.querySelector('#confirm-msg').textContent = msg || 'Are you sure?';
    ok.textContent = opts.ok || (tone === 'danger' ? 'Delete' : 'Confirm');
    ok.className = tone === 'danger' ? 'btn danger' : 'btn brand';
    modal.classList.add('open');
    return true;
  }
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches || !f.matches('[data-confirm]')) return;
    if (f.dataset.confirmed === '1') { delete f.dataset.confirmed; return; } // second pass: let it through
    e.preventDefault();
    pendingConfirm = { form: f };
    if (!openConfirm(f.getAttribute('data-confirm'), { tone: f.getAttribute('data-confirm-tone'), title: f.getAttribute('data-confirm-title'), ok: f.getAttribute('data-confirm-ok') })) {
      if (confirm(f.getAttribute('data-confirm') || 'Are you sure?')) { f.dataset.confirmed = '1'; f.submit(); }
    }
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('#confirm-ok')) return;
    var modal = document.getElementById('confirm-modal');
    var job = pendingConfirm;
    pendingConfirm = null;
    if (modal) modal.classList.remove('open');
    if (!job) return;
    if (job.bulk) { bulkRun(job.bulk.bar, job.bulk.payload); return; }
    var form = job.form;
    if (!form) return;
    var methodInput = form.querySelector('input[name=_method]');
    if (methodInput && methodInput.value === 'DELETE') ajaxDelete(form);
    else if (form.hasAttribute('data-ajax-post')) ajaxPostFlow(form, form.getAttribute('data-refresh'));
    else { form.dataset.confirmed = '1'; form.submit(); }
  });
  // Toasts: every action confirmation (saves, deletes, mark-as-read, drags…)
  // lands as a floating bottom-right alert that clears itself after 3s, so
  // nothing has to reload the page just to show feedback.
  function toastDismiss(el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s, transform .4s';
      el.style.opacity = '0';
      el.style.transform = 'translateY(8px)';
      setTimeout(function () { el.remove(); }, 450);
    }, 3000);
  }
  function ajaxFlash(msg, isErr) {
    var host = document.getElementById('toasts');
    if (!host) return;
    var div = document.createElement('div');
    div.className = isErr ? 'err' : 'alert';
    div.textContent = msg;
    host.appendChild(div);
    toastDismiss(div);
  }
  window.crmFlash = ajaxFlash;
  // Server-rendered flashes (shown right after a redirect) ride along as toasts
  // too, so confirmations look the same whether or not the action was AJAX.
  // Validation error lists (with a <ul>) stay inline — they need to be read.
  function adoptFlashes() {
    var page = document.querySelector('.content');
    var host = document.getElementById('toasts');
    if (!page || !host) return;
    Array.prototype.forEach.call(page.children, function (el) {
      if (!el || !el.classList) return;
      var ok = el.classList.contains('alert') && el.hasAttribute('data-flash');
      var bad = el.classList.contains('err') && !el.querySelector('ul');
      if (!ok && !bad) return;
      host.appendChild(el);
      toastDismiss(el);
    });
  }
  adoptFlashes();
  function ajaxDelete(form) {
    var tokenInput = form.querySelector('input[name=_token]');
    var token = tokenInput ? tokenInput.value : '';
    var row = form.closest('tr, .file-tile, .kan-card');
    var refresh = form.getAttribute('data-refresh');
    if (row) row.classList.add('row-pending');
    fetch(form.action, {
      method: 'POST',
      headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token},
      body: new URLSearchParams({_token: token, _method: 'DELETE'})
    }).then(function (res) { return res.text().then(function (html) { return {ok: res.ok, url: res.url, html: html}; }); })
    .then(function (out) {
      var tmp = document.createElement('div');
      tmp.innerHTML = out.html;
      var err = tmp.querySelector('.err');
      if (!out.ok || err) {
        if (row) row.classList.remove('row-pending');
        ajaxFlash(err ? err.innerText.trim().slice(0, 220) : 'Could not delete. Please try again.', true);
        return;
      }
      var flash = tmp.querySelector('[data-flash]') || tmp.querySelector('.alert');
      ajaxFlash(flash ? flash.innerText.trim().slice(0, 220) : 'Deleted.');
      if (refresh) {
        // Pages that show counters/empty states around the list ask for the
        // whole region back so the tallies stay in step with the rows.
        if (row) row.classList.remove('row-pending');
        refreshSelectors(refresh);
        return;
      }
      if (!row) {
        // Show page etc: follow where the server went (usually the index).
        setTimeout(function () {
          if (out.url && out.url !== window.location.href) window.location = out.url;
          else window.location.reload();
        }, 900);
        return;
      }
      row.classList.remove('row-pending');
      row.classList.add('row-out');
      setTimeout(function () {
        var host = row.tagName === 'TR' ? row.closest('table') : row.parentElement;
        row.remove();
        // Last item gone: reload so the proper empty state renders.
        if (host) {
          if (row.tagName === 'TR' && host.querySelectorAll('tr').length <= 1) window.location.reload();
          else if (row.tagName !== 'TR' && !host.querySelector('tr, .file-tile, .fm-folder, .cat-row, .kan-card')) window.location.reload();
        }
      }, 340);
    }).catch(function () {
      if (row) row.classList.remove('row-pending');
      ajaxFlash('Could not delete. Please try again.', true);
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      if (e.target.closest && e.target.closest('[data-modal-close]')) return; // let Cancel activate normally
      var modal = document.getElementById('confirm-modal');
      if (modal && modal.classList.contains('open')) {
        e.preventDefault();
        var ok = document.getElementById('confirm-ok');
        if (ok) ok.click();
      }
    }
  });

  // Dynamic invoice item rows
  var items = document.getElementById('items');
  var addBtn = document.getElementById('add-item');
  function rowIndex() { return items ? items.querySelectorAll('.item-row').length : 0; }
  function defaultRate() {
    var c = document.querySelector('#gst-defaults [name=gst_rate]:checked, [name=gst_rate]:checked');
    return c ? c.value : '18';
  }
  function rateOptions(sel) {
    return ['0', '5', '12', '18', '28'].map(function (r) {
      return '<option value="' + r + '"' + (parseFloat(sel) === parseFloat(r) ? ' selected' : '') + '>' + r + '%</option>';
    }).join('');
  }
  if (items && addBtn) {
    function escAttr(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    // One row builder for the blank button AND the catalog picker.
    // prefill: {description, qty, unit, hsn_code, rate, tax_rate}
    function addItemRow(prefill) {
      var p = prefill || {};
      var i = rowIndex() + Math.floor(Math.random() * 100000);
      var row = document.createElement('div');
      row.className = 'item-row';
      var tax = (p.tax_rate === 0 || p.tax_rate) ? String(p.tax_rate) : defaultRate();
      row.innerHTML = '<input class="in" name="items[' + i + '][description]" placeholder="Service / item description" required value="' + escAttr(p.description || '') + '">' +
        '<div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><div style="flex:0 0 80px"><label class="f">Qty</label><input class="in" name="items[' + i + '][qty]" type="number" step="0.01" value="' + escAttr(p.qty || 1) + '" style="margin-bottom:0"></div>' +
        '<div style="flex:0 0 80px"><label class="f">Unit</label><input class="in" name="items[' + i + '][unit]" value="' + escAttr(p.unit || 'NOS') + '" list="unit-list" style="margin-bottom:0"></div>' +
        '<div style="flex:0 0 110px"><label class="f">HSN/SAC</label><input class="in" name="items[' + i + '][hsn_code]" placeholder="9983" value="' + escAttr(p.hsn_code || '') + '" style="margin-bottom:0"></div>' +
        '<div style="flex:1;min-width:110px"><label class="f">Rate (<span class="rate-cur">Rs.</span>)</label><input class="in" name="items[' + i + '][rate]" type="number" step="0.01" value="' + escAttr(p.rate || 0) + '" style="margin-bottom:0"></div>' +
        '<div style="flex:0 0 70px"><label class="f">Disc%</label><input class="in" name="items[' + i + '][discount_percent]" type="number" step="0.01" min="0" max="100" value="' + escAttr(p.discount_percent || 0) + '" style="margin-bottom:0"></div>' +
        '<div style="flex:0 0 90px"><label class="f">GST%</label><select class="in" name="items[' + i + '][tax_rate]" style="margin-bottom:0">' + rateOptions(tax) + '</select></div>' +
        (items.dataset.billing === '1' ? '<div style="flex:0 0 100px"><label class="f">Bills</label><select class="in" name="items[' + i + '][billing]" style="margin-bottom:0"><option value="once">One-time</option><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></div>' : '') +
        '<button type="button" class="row-del" data-remove style="margin-bottom:10px">Remove</button></div>';
      items.appendChild(row);
      var unitIn = row.querySelector('input[list]');
      if (unitIn) enhanceCombobox(unitIn);
      row.querySelector('input').focus();
      recalcInvoice();
    }
    addBtn.addEventListener('click', function () { addItemRow({}); });
    // Drop untouched blank lines on submit so a leftover empty row never
    // blocks the save (server strips them too; all-blank still fails min:1).
    var invForm = items.closest('form');
    if (invForm && !invForm.dataset.rowsBound) {
      invForm.dataset.rowsBound = '1';
      invForm.addEventListener('submit', function () {
        var rows = Array.prototype.slice.call(invForm.querySelectorAll('.item-row'));
        var filled = rows.filter(function (row) {
          var d = row.querySelector('input[name$="[description]"]');
          return d && d.value.trim() !== '';
        });
        if (filled.length) {
          rows.forEach(function (row) {
            var d = row.querySelector('input[name$="[description]"]');
            if (!d || d.value.trim() === '') row.remove();
          });
        }
      });
    }
    items.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-remove]');
      if (btn && items.querySelectorAll('.item-row:not(.fixed)').length > 1) {
        btn.closest('.item-row').remove();
        recalcInvoice();
      }
    });
    items.addEventListener('input', recalcInvoice);
    items.addEventListener('change', recalcInvoice);
    document.querySelectorAll('[name=gst_rate]').forEach(function (el) { el.addEventListener('change', recalcInvoice); });
    recalcInvoice();
    // Catalog picker: type to filter active products, pick one to append a
    // prefilled line. Server-rendered JSON, no extra requests, no-JS hides it.
    (function catalogPicker() {
      var input = document.getElementById('catalog-search');
      var pop = document.getElementById('catalog-pop');
      var raw = document.getElementById('catalog-data');
      if (!input || !pop || !raw) return;
      var catalog = [];
      try { catalog = JSON.parse(raw.textContent || '[]'); } catch (e) { catalog = []; }
      // Client price lists: {product_id: rate} overrides for the billed client.
      var clientPrices = {};
      var ownerForm = input.closest('form');
      var clientSel = ownerForm ? ownerForm.querySelector('select[name=client_id]') : null;
      function loadClientPrices() {
        clientPrices = {};
        if (!clientSel || !clientSel.value) return;
        fetch('/clients/' + encodeURIComponent(clientSel.value) + '/prices', {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
          .then(function (res) { return res.ok ? res.json() : {}; })
          .then(function (d) { clientPrices = d && typeof d === 'object' ? d : {}; })
          .catch(function () { clientPrices = {}; });
      }
      if (clientSel) { loadClientPrices(); clientSel.addEventListener('change', loadClientPrices); }
      function escH(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
      function close() { pop.classList.remove('open'); pop.innerHTML = ''; }
      function money(n) { return 'Rs.' + Number(n || 0).toLocaleString('en-IN', {maximumFractionDigits: 2}); }
      input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        if (q.length < 1) { close(); return; }
        var hits = catalog.filter(function (p) {
          return (p.name + ' ' + (p.sku || '')).toLowerCase().indexOf(q) > -1;
        }).slice(0, 8);
        if (!hits.length) { pop.innerHTML = '<div class="sp-none">No catalog match.</div>'; pop.classList.add('open'); return; }
        pop.innerHTML = hits.map(function (p) {
          var thumb = p.image_path ? '<img src="/storage/' + escH(p.image_path) + '" alt="" loading="lazy">' : '';
          return '<button type="button" data-id="' + p.id + '">' + thumb + '<span><b>' + escH(p.name) + '</b><span class="hint"> ' + money(p.rate) + (p.sku ? ' · ' + escH(p.sku) : '') + '</span></span></button>';
        }).join('');
        pop.classList.add('open');
      });
      pop.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-id]');
        if (!b) return;
        var found = null;
        for (var k = 0; k < catalog.length; k++) {
          if (String(catalog[k].id) === b.getAttribute('data-id')) { found = catalog[k]; break; }
        }
        if (!found) return;
        var special = clientPrices[found.id] != null ? parseFloat(clientPrices[found.id]) : null;
        var prefill = {description: found.name, qty: 1, unit: found.unit || 'NOS', hsn_code: found.hsn_code || '', rate: special !== null && !isNaN(special) ? special : (found.rate || 0), tax_rate: found.tax_rate};
        // Fill the untouched starter row instead of stacking a ghost line:
        // one empty row + a pick means "use this row".
        var rows = items.querySelectorAll('.item-row');
        var lone = null;
        if (rows.length === 1) {
          var d0 = rows[0].querySelector('input[name$="[description]"]');
          if (d0 && !d0.value.trim()) lone = rows[0];
        }
        if (lone) {
          Object.keys(prefill).forEach(function (k) {
            var f = lone.querySelector('[name$="[' + k + ']"]');
            if (!f) return;
            if (k === 'tax_rate' && f.tagName === 'SELECT') {
              // Catalog rates come back as "18.00" — match numerically.
              var want = parseFloat(prefill[k]);
              for (var o = 0; o < f.options.length; o++) {
                if (parseFloat(f.options[o].value) === want) { f.selectedIndex = o; break; }
              }
            } else {
              f.value = prefill[k] == null ? '' : prefill[k];
            }
          });
          recalcInvoice();
        } else {
          addItemRow(prefill);
        }
        input.value = '';
        close();
      });
      document.addEventListener('click', function (e) {
        if (!e.target.closest('#catalog-search') && !e.target.closest('#catalog-pop')) close();
      });
      input.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    })();
  }

  // Invoice currency: line rates are typed in the invoice currency —
  // relabel every rate field and the FX hint when it changes.
  var curSel = document.getElementById('inv-currency');
  if (curSel) {
    var fxIn = document.getElementById('inv-fx');
    var codeEl = document.getElementById('inv-cur-code');
    curSel.addEventListener('change', function () {
      var c = curSel.value || 'INR';
      document.querySelectorAll('.rate-cur').forEach(function (el) { el.textContent = c === 'INR' ? 'Rs.' : c; });
      if (codeEl) codeEl.textContent = c;
      if (fxIn) { fxIn.disabled = (c === 'INR'); if (c === 'INR') fxIn.value = '1'; }
    });
    curSel.dispatchEvent(new Event('change'));
  }

  function recalcInvoice() {
    var box = document.getElementById('inv-total');
    if (!box || !items) return;
    var sub = 0, gst = 0;
    var fallback = defaultRate();
    items.querySelectorAll('.item-row').forEach(function (r) {
      var q = parseFloat((r.querySelector('[name$="[qty]"]') || {}).value || '0');
      var rt = parseFloat((r.querySelector('[name$="[rate]"]') || {}).value || '0');
      var dp = parseFloat((r.querySelector('[name$="[discount_percent]"]') || {}).value || '0');
      dp = Math.max(0, Math.min(100, isNaN(dp) ? 0 : dp));
      var amt = q * rt * (1 - dp / 100);
      sub += amt;
      var rsel = r.querySelector('[name$="[tax_rate]"]');
      gst += amt * (rsel ? parseFloat(rsel.value || fallback) : parseFloat(fallback)) / 100;
    });
    var discEl = document.querySelector('[name=discount]');
    var disc = discEl ? Math.max(0, Math.min(parseFloat(discEl.value || '0'), sub)) : 0;
    if (discEl && !discEl.dataset.bound) { discEl.dataset.bound = '1'; discEl.addEventListener('input', recalcInvoice); }
    if (sub > 0 && disc > 0) gst = gst * (sub - disc) / sub; // pro-rata preview
    var taxable = sub - disc, total = taxable + gst;
    var fmt = function (n) { return 'Rs.' + n.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };
    box.innerHTML = 'Subtotal ' + fmt(sub) + (disc ? ' - discount ' + fmt(disc) : '') + ' + GST ' + fmt(gst) + ' = <b>' + fmt(total) + '</b>';
  }

  // Export + generic modal popups
  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-modal-open]');
    if (opener) {
      var m = document.getElementById(opener.getAttribute('data-modal-open'));
      if (m) m.classList.add('open');
      return;
    }
    if (e.target.closest('[data-modal-close]') || (e.target.classList && e.target.classList.contains('modal-bg'))) {
      var modal = e.target.closest('.modal');
      if (modal) modal.classList.remove('open');
    }
  });
  // Spotlight palette: centered overlay search (button, Cmd/Ctrl+K, "/").
  var spot = document.getElementById('spot');
  var spotInput = document.getElementById('spot-input');
  var spotResults = document.getElementById('spot-results');
  var spotTimer = null, spotHot = -1, spotLinks = [];
  function spotEsc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
  function openSpot() {
    if (!spot || !spotInput) return;
    spot.classList.add('open');
    spotInput.value = '';
    spotResults.innerHTML = '';
    spotHot = -1;
    setTimeout(function () { spotInput.focus(); }, 30);
  }
  function closeSpot() { if (spot) spot.classList.remove('open'); spotHot = -1; }
  function toggleSpot() { (spot && spot.classList.contains('open')) ? closeSpot() : openSpot(); }
  function spotPaint() {
    spotLinks = spot ? Array.from(spot.querySelectorAll('a.sp-i')) : [];
    spotLinks.forEach(function (a, i) { a.classList.toggle('hot', i === spotHot); });
    if (spotHot >= 0 && spotLinks[spotHot]) spotLinks[spotHot].scrollIntoView({block: 'nearest'});
  }
  function spotRender(data, q) {
    if (!spot) return;
    var h = '';
    if (!data.groups || !data.groups.length) {
      h = '<div class="sp-none">No matches for &ldquo;' + spotEsc(q) + '&rdquo;</div>';
    } else {
      data.groups.forEach(function (g) {
        h += '<div class="sp-g">' + spotEsc(g.label) + ' (' + g.items.length + ')</div>';
        g.items.slice(0, 6).forEach(function (it) {
          h += '<a class="sp-i" href="' + spotEsc(it.url) + '"><span>' + spotEsc(it.title) + '</span>' + (it.sub ? '<small>' + spotEsc(it.sub) + '</small>' : '') + '</a>';
        });
      });
      h += '<a class="spot-all" href="' + spot.getAttribute('data-search-url') + '?q=' + encodeURIComponent(q) + '"><span>See all ' + data.total + ' results</span><span>&rarr;</span></a>';
    }
    spotResults.innerHTML = h;
    spotHot = -1;
    spotPaint();
  }
  if (spot && spotInput) {
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-spot-open]')) { e.preventDefault(); openSpot(); }
      if (e.target.closest('[data-spot-close]')) closeSpot();
    });
    spotInput.addEventListener('input', function () {
      clearTimeout(spotTimer);
      var q = spotInput.value.trim();
      if (q.length < 2) { spotResults.innerHTML = ''; spotHot = -1; return; }
      spotTimer = setTimeout(function () {
        spotResults.innerHTML = '<div class="sp-load"><span class="spin"></span>Searching…</div>';
        fetch(spot.getAttribute('data-search-url') + '?live=1&q=' + encodeURIComponent(q), {headers: {'Accept': 'application/json'}})
          .then(function (res) { return res.ok ? res.json() : null; })
          .then(function (data) { if (data && spotInput.value.trim() === q) spotRender(data, q); })
          .catch(function () {});
      }, 220);
    });
    spotInput.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); spotHot = Math.min(spotHot + 1, spotLinks.length - 1); spotPaint(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); spotHot = Math.max(spotHot - 1, -1); spotPaint(); }
      else if (e.key === 'Enter' && spotHot >= 0 && spotLinks[spotHot]) { e.preventDefault(); window.location = spotLinks[spotHot].href; }
      else if (e.key === 'Escape') closeSpot();
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { document.querySelectorAll('.modal.open').forEach(function (m) { m.classList.remove('open'); }); closeSpot(); }
    // Cmd/Ctrl+K toggles spotlight; "/" opens it (old topbar input is gone).
    if ((e.key === 'k' || e.key === 'K') && (e.metaKey || e.ctrlKey)) {
      e.preventDefault();
      toggleSpot();
      return;
    }
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
      var ae = document.activeElement || {};
      if (!/^(INPUT|TEXTAREA|SELECT)$/.test(ae.tagName || '')) {
        e.preventDefault();
        openSpot();
      }
    }
  });
  // Beautiful file pickers: wrap native inputs, show chosen file names
  document.querySelectorAll('input[type=file]').forEach(function (input) {
    if (input.closest('.file-field')) return;
    if (input.closest('.fm-upload')) return; // toolbar upload keeps its native button
    var wrap = document.createElement('label');
    wrap.className = 'file-field';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    var btn = document.createElement('span');
    btn.className = 'file-btn';
    btn.textContent = input.hasAttribute('multiple') ? 'Choose files' : 'Choose file';
    var nm = document.createElement('span');
    nm.className = 'file-name';
    nm.textContent = 'No file chosen';
    wrap.appendChild(btn);
    wrap.appendChild(nm);
    input.addEventListener('change', function () {
      var names = Array.from(input.files || []).map(function (f) { return f.name; }).join(', ');
      nm.textContent = names || 'No file chosen';
      wrap.classList.toggle('has-file', !!(names));
    });
  });
  // Combobox enhancer: swaps the native <datalist> popup for a styled one.
  // The datalist itself stays in the DOM so rows added later can reuse it.
  function enhanceCombobox(input) {
    if (!input || input.dataset.combo) return;
    input.dataset.combo = '1';
    var dl = document.getElementById(input.getAttribute('list'));
    if (!dl) return;
    var opts = Array.from(dl.querySelectorAll('option')).map(function (o) { return o.value || o.textContent; });
    input.setAttribute('autocomplete', 'off');
    input.removeAttribute('list');
    var pop = document.createElement('div');
    pop.className = 'combo-pop';
    var parent = input.parentElement;
    if (parent && getComputedStyle(parent).position === 'static') parent.style.position = 'relative';
    input.after(pop);
    // Anchor under the input itself (not the whole parent block).
    function place() {
      try { pop.style.top = (input.offsetTop + input.offsetHeight - 8) + 'px'; } catch (e) {}
    }
    var hot = -1, current = [];
    function close() { pop.classList.remove('open'); hot = -1; }
    function pick(v) {
      input.value = v; close();
      input.dispatchEvent(new Event('input', {bubbles: true}));
      input.dispatchEvent(new Event('change', {bubbles: true}));
    }
    function render() {
      place();
      var q = input.value.trim().toLowerCase();
      current = opts.filter(function (v) { return v.toLowerCase().indexOf(q) !== -1; }).slice(0, 8);
      if (!current.length || (current.length === 1 && current[0].toLowerCase() === q)) { close(); return; }
      pop.innerHTML = '';
      current.forEach(function (v, i) {
        var b = document.createElement('button');
        b.type = 'button'; b.textContent = v;
        b.addEventListener('mousedown', function (e) { e.preventDefault(); pick(v); });
        b.addEventListener('mousemove', function () { hot = i; paint(); });
        pop.appendChild(b);
      });
      hot = -1;
      pop.classList.add('open');
    }
    function paint() {
      Array.from(pop.children).forEach(function (b, i) { b.classList.toggle('hot', i === hot); });
      if (hot >= 0 && pop.children[hot]) pop.children[hot].scrollIntoView({block: 'nearest'});
    }
    input.addEventListener('focus', render);
    input.addEventListener('input', render);
    input.addEventListener('blur', function () { setTimeout(close, 150); });
    input.addEventListener('keydown', function (e) {
      if (!pop.classList.contains('open')) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); hot = Math.min(hot + 1, current.length - 1); paint(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); hot = Math.max(hot - 1, 0); paint(); }
      else if (e.key === 'Enter' && hot >= 0) { e.preventDefault(); pick(current[hot]); }
      else if (e.key === 'Escape') close();
    });
  }
  document.querySelectorAll('input[list]').forEach(enhanceCombobox);
  // Themed <select> dropdowns: swap the OS option popup for an in-app panel.
  // The native <select> stays in the DOM (value, form submit, no-JS fallback);
  // only the pointer is intercepted, so dynamic rows/filters keep working.
  (function () {
    var fine = true;
    try { fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches; } catch (e) {}
    if (!fine) return; // touch devices keep their native picker
    var pop = null, cur = null, list = [], hot = -1;
    function can(sel) {
      return sel && !sel.disabled && !sel.multiple && (sel.size || 0) <= 1
        && !sel.hasAttribute('data-native') && sel.getAttribute('data-ui-sel') !== 'off';
    }
    function label(o) { return (o.label || o.textContent || '').replace(/\s+/g, ' ').trim(); }
    function flat(sel) {
      var out = [];
      Array.prototype.forEach.call(sel.children, function (n) {
        if (n.tagName === 'OPTGROUP') Array.prototype.forEach.call(n.children, function (o) { out.push({ el: o, group: n.label }); });
        else if (n.tagName === 'OPTION') out.push({ el: n, group: '' });
      });
      return out;
    }
    function close() {
      if (pop && pop.parentNode) pop.parentNode.removeChild(pop);
      pop = null; cur = null; list = []; hot = -1;
    }
    function paint() {
      Array.prototype.forEach.call(pop.querySelectorAll('button'), function (b, i) {
        var on = i === hot;
        b.classList.toggle('hot', on);
        if (on) b.scrollIntoView({ block: 'nearest' });
      });
    }
    function move(d) { if (list.length) { hot = (hot + d + list.length) % list.length; paint(); } }
    function commit(i) {
      var o = list[i];
      if (!o || o.el.disabled || !cur) return;
      cur.value = o.el.value;
      cur.dispatchEvent(new Event('input', { bubbles: true }));
      cur.dispatchEvent(new Event('change', { bubbles: true }));
      close();
    }
    function open(sel) {
      close();
      cur = sel; list = flat(sel);
      pop = document.createElement('div');
      pop.className = 'combo-pop sel-pop open';
      pop.setAttribute('role', 'listbox');
      var lastGroup = null;
      list.forEach(function (o, i) {
        if (o.group && o.group !== lastGroup) {
          var g = document.createElement('div');
          g.className = 'combo-group'; g.textContent = o.group;
          pop.appendChild(g); lastGroup = o.group;
        }
        var b = document.createElement('button');
        b.type = 'button'; b.setAttribute('role', 'option');
        b.setAttribute('data-sel-idx', i);
        b.className = o.el.selected ? 'on' : '';
        b.disabled = !!o.el.disabled;
        b.setAttribute('aria-selected', o.el.selected ? 'true' : 'false');
        var t = document.createElement('span');
        t.textContent = label(o.el);
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('class', 'sel-tick');
        svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2.6'); svg.setAttribute('stroke-linecap', 'round'); svg.setAttribute('stroke-linejoin', 'round');
        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', 'm5 12 5 5 9-10');
        svg.appendChild(path);
        b.appendChild(t); b.appendChild(svg);
        if (o.el.selected && hot < 0) hot = i;
        b.addEventListener('mousedown', function (e) { e.preventDefault(); commit(i); });
        b.addEventListener('mousemove', function () { hot = i; paint(); });
        pop.appendChild(b);
      });
      document.body.appendChild(pop);
      var r = sel.getBoundingClientRect();
      var w = Math.max(r.width, 170);
      pop.style.width = w + 'px';
      pop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 8 - w)) + 'px';
      pop.style.top = (r.bottom + 6) + 'px';
      var ph = pop.offsetHeight;
      if (r.bottom + 6 + ph > window.innerHeight - 8 && r.top - 6 - ph > 8) pop.style.top = (r.top - 6 - ph) + 'px';
      if (hot < 0) hot = 0;
      paint();
      try { sel.focus({ preventScroll: true }); } catch (e) { sel.focus(); }
    }
    document.addEventListener('mousedown', function (e) {
      var t = e.target;
      if (!t || !t.closest) { if (pop) close(); return; }
      if (pop && t.closest('.sel-pop')) { if (!t.closest('button')) e.preventDefault(); return; }
      var sel = t.closest('select');
      if (can(sel)) {
        e.preventDefault();
        if (cur === sel && pop) close(); else open(sel);
        return;
      }
      if (pop) close();
    }, true);
    document.addEventListener('keydown', function (e) {
      if (!cur || document.activeElement !== cur) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
      else if (e.key === 'Home') { e.preventDefault(); hot = 0; paint(); }
      else if (e.key === 'End') { e.preventDefault(); hot = list.length - 1; paint(); }
      else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); commit(hot); }
      else if (e.key === 'Escape' || e.key === 'Tab') close();
    }, true);
    window.addEventListener('resize', close);
    window.addEventListener('scroll', function (e) {
      if (pop && (e.target === pop || (e.target.closest && e.target.closest('.sel-pop')))) return;
      close();
    }, true);
  })();
  // Payment form: picking an invoice fills client + balance
  var invSel = document.getElementById('pay-invoice');
  if (invSel) {
    invSel.addEventListener('change', function () {
      var opt = invSel.selectedOptions[0];
      if (!opt || !opt.value) return;
      var clientSel = document.getElementById('pay-client');
      if (clientSel && opt.dataset.client) clientSel.value = opt.dataset.client;
      var amt = document.getElementById('pay-amount');
      if (amt && opt.dataset.balance) amt.value = opt.dataset.balance;
    });
  }
  // Drag failures ride the same bottom-right toasts as everything else.
  function dragError(msg) {
    ajaxFlash(msg || 'Something went wrong. Please try again.', true);
  }
  function moveFetch(url, payload) {
    var token = document.querySelector('meta[name=csrf-token]');
    var ctrl = ('AbortController' in window) ? new AbortController() : null;
    var timer = null;
    if (ctrl) timer = setTimeout(function () { ctrl.abort(); }, 20000);
    return fetch(url, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : ''},
      body: JSON.stringify(payload), signal: ctrl ? ctrl.signal : undefined
    }).then(function (res) {
      if (timer) clearTimeout(timer);
      if (res.status === 419) throw new Error('Session expired — refresh the page and try again.');
      if (!res.ok) throw new Error('move failed');
      return res.json();
    }).catch(function (err) {
      if (timer) clearTimeout(timer);
      throw (err && err.name === 'AbortError') ? new Error('Request timed out — please try again.') : err;
    });
  }
  // Leads kanban: HTML5 drag-drop between stage columns (progressive enhancement;
  // the per-card stage <select> still works with JS disabled). Events are delegated
  // on document so a seamless board refresh (region swap) keeps the gesture working
  // on the freshly swapped DOM.
  function kanbanEl() { return document.querySelector('.kanban'); }
  // Links inside cards must not start their own native link-drag - the whole card
  // is the drag source, from any grab point. Re-run after every board swap.
  function kanbanMarkLinks(root) {
    (root || document).querySelectorAll('.kan-card a').forEach(function (a) { a.setAttribute('draggable', 'false'); });
  }
  kanbanMarkLinks(document);
  var dragged = null, kanDropped = false, kanPin = null;
  // Lock the board's own scroll while dragging: native auto-scroll fights
  // the gesture on wide boards and feels like the page hangs.
  function kanLock() {
    var kan = kanbanEl();
    if (!kan) return;
    var x = kan.scrollLeft;
    kanPin = function () { kan.scrollLeft = x; };
    kan.addEventListener('scroll', kanPin);
  }
  function kanUnlock() {
    var kan = kanbanEl();
    if (kan && kanPin) kan.removeEventListener('scroll', kanPin);
    kanPin = null;
  }
  // Stage order + labels read from the DOM so they stay in sync with the server
  // render (columns are emitted in stage order).
  function kanStages() {
    var out = [];
    document.querySelectorAll('.kanban .kan-col[data-stage]').forEach(function (c) {
      var s = c.getAttribute('data-stage');
      if (s && out.indexOf(s) === -1) out.push(s);
    });
    return out;
  }
  function kanStageLabel(stage) {
    var col = document.querySelector('.kanban .kan-col[data-stage="' + stage + '"]');
    var b = col ? col.querySelector('.kan-head b') : null;
    return b ? b.textContent.trim() : stage;
  }
  // Recompute each column header count from the live DOM.
  function kanUpdateCounts() {
    document.querySelectorAll('.kanban .kan-col[data-stage]').forEach(function (col) {
      var list = col.querySelector('.kan-list'), n = col.querySelector('.kan-head .n');
      if (list && n) n.textContent = list.querySelectorAll('.kan-card').length;
    });
  }
  // Keep a column's "Drop leads here" placeholder in sync with its card count.
  function kanSyncEmpty(col) {
    if (!col) return;
    var list = col.querySelector('.kan-list');
    if (!list) return;
    var empty = list.querySelector('.kan-empty');
    if (list.querySelector('.kan-card')) { if (empty) empty.remove(); }
    else if (!empty) {
      var div = document.createElement('div');
      div.className = 'kan-empty';
      div.textContent = 'Drop leads here';
      list.appendChild(div);
    }
  }
  function kanArrowForm(moveUrl, token, stage, arrow) {
    return '<form method="POST" action="' + moveUrl + '" style="display:inline">'
      + '<input type="hidden" name="_token" value="' + token + '">'
      + '<input type="hidden" name="stage" value="' + stage + '">'
      + '<button class="kan-arrow" title="Move to ' + kanStageLabel(stage) + '">' + arrow + '</button>'
      + '</form>';
  }
  // Bring a moved card's own controls in line with its new stage (stage select,
  // prev/next arrows, convert link) so the board never needs a re-render.
  function kanRefreshCard(card, stage) {
    var meta = document.querySelector('meta[name=csrf-token]');
    var tk = meta ? meta.content : '';
    var id = card.getAttribute('data-id') || '';
    var sel = card.querySelector('.kan-move select');
    if (sel) sel.value = stage;
    var order = kanStages(), idx = order.indexOf(stage);
    var prev = idx > 0 ? order[idx - 1] : null;
    var next = idx >= 0 && idx < order.length - 1 ? order[idx + 1] : null;
    var nav = card.querySelector('.kan-nav');
    if (nav) {
      var form = card.querySelector('form.kan-move');
      var moveUrl = form ? form.getAttribute('action') : ('/leads/' + id + '/move');
      var html = '';
      if (prev) html += kanArrowForm(moveUrl, tk, prev, '‹');
      if (next) html += kanArrowForm(moveUrl, tk, next, '›');
      nav.innerHTML = html;
    }
    var links = card.querySelector('.kan-links');
    if (links) {
      var conv = links.querySelector('form');
      var closed = stage === 'won' || stage === 'lost';
      if (conv) conv.style.display = closed ? 'none' : '';
      else if (!closed) {
        links.insertAdjacentHTML('beforeend', ' · <form method="POST" action="/leads/' + id
          + '/convert" style="display:inline" data-confirm="Convert this lead to a client?" data-confirm-tone="brand"'
          + ' data-confirm-title="Convert lead?" data-confirm-ok="Convert"><input type="hidden" name="_token" value="' + tk
          + '"><button class="link-btn" type="submit">Convert</button></form>');
      }
    }
  }
  // The funnel above the board tracks stage totals across all leads; a drag moves
  // one lead between stages, so adjust those two counts in place using the same
  // width formula the server render uses.
  function kanUpdateFunnel(from, to) {
    if (!from || !to || from === to) return;
    var counts = {};
    document.querySelectorAll('.funnel [data-stage]').forEach(function (bar) {
      counts[bar.getAttribute('data-stage')] = parseInt(bar.textContent, 10) || 0;
    });
    counts[from] = Math.max(0, (counts[from] || 0) - 1);
    counts[to] = (counts[to] || 0) + 1;
    var total = 0;
    Object.keys(counts).forEach(function (k) { total += counts[k]; });
    total = Math.max(1, total);
    document.querySelectorAll('.funnel [data-stage]').forEach(function (bar) {
      var s = bar.getAttribute('data-stage'), c = counts[s] || 0;
      bar.style.width = (c > 0 ? Math.max(9, Math.round(c / total * 1000) / 10) : 0) + '%';
      bar.textContent = c;
      bar.title = kanStageLabel(s) + ': ' + c;
    });
    document.querySelectorAll('.funnel-legend [data-stage]').forEach(function (sp) {
      var s = sp.getAttribute('data-stage'), c = counts[s] || 0;
      if (sp.lastChild) sp.lastChild.nodeValue = kanStageLabel(s) + ' (' + c + ')';
    });
  }
  document.addEventListener('dragstart', function (e) {
    if (!kanbanEl()) return;
    var card = e.target.closest('.kan-card');
    if (!card) return;
    dragged = card; kanDropped = false;
    kanLock();
    card.classList.add('drag');
    try { e.dataTransfer.setData('text/plain', card.dataset.id || ''); e.dataTransfer.effectAllowed = 'move'; } catch (err) {}
  });
  document.addEventListener('dragend', function () {
    if (dragged) dragged.classList.remove('drag');
    dragged = null; kanDropped = false;
    kanUnlock();
    document.querySelectorAll('.kan-list.over,.kan-col.over').forEach(function (l) { l.classList.remove('over'); });
  });
  // Whole columns accept drops (head, gaps and list alike).
  document.addEventListener('dragenter', function (e) {
    if (!dragged || !e.target.closest('.kan-col[data-stage]')) return;
    e.preventDefault();
  });
  document.addEventListener('dragover', function (e) {
    var col = e.target.closest('.kan-col[data-stage]');
    if (!col || !dragged) return;
    e.preventDefault();
    try { e.dataTransfer.dropEffect = 'move'; } catch (err) {}
    if (!col.classList.contains('over')) col.classList.add('over');
  });
  document.addEventListener('dragleave', function (e) {
    var col = e.target.closest('.kan-col[data-stage]');
    if (!col) return;
    // Only clear when the pointer actually left the column, not a child card.
    if (e.relatedTarget && col.contains(e.relatedTarget)) return;
    col.classList.remove('over');
  });
  // Move a card with an optimistic in-place update — used by the drag gesture,
  // the per-card stage <select> and the ‹ › arrow buttons alike. Nothing
  // re-renders, so there is no flash and the drag gesture state stays intact.
  function kanMoveCard(card, toStage, fromStage, moveUrl) {
    var from = card.parentElement;
    if (!fromStage && from) fromStage = from.getAttribute('data-drop');
    if (!toStage || fromStage === toStage) return;
    var col = document.querySelector('.kanban .kan-col[data-stage="' + toStage + '"]');
    var toList = col ? col.querySelector('.kan-list') : null;
    if (!toList) return;
    toList.appendChild(card);
    if (motionOK()) {
      card.classList.remove('landed');
      void card.offsetWidth; // restart the settle animation
      card.classList.add('landed');
      setTimeout(function () { card.classList.remove('landed'); }, 400);
    }
    var fromCol = from ? from.closest('.kan-col') : null;
    kanUpdateCounts();
    kanSyncEmpty(fromCol);
    kanSyncEmpty(col);
    var token = document.querySelector('meta[name=csrf-token]');
    moveFetch(moveUrl + (moveUrl.indexOf('?') > -1 ? '&' : '?') + 'ajax=1',
      {stage: toStage, _token: token ? token.content : ''})
      .then(function () {
        kanRefreshCard(card, toStage);
        kanUpdateFunnel(fromStage, toStage);
      })
      .catch(function (err) {
        if (from) from.appendChild(card);
        if (motionOK() && card) {
          card.classList.remove('shake');
          void card.offsetWidth;
          card.classList.add('shake');
          setTimeout(function () { card.classList.remove('shake'); }, 400);
        }
        kanUpdateCounts();
        kanSyncEmpty(fromCol);
        kanSyncEmpty(col);
        var sel = card.querySelector('.kan-move select');
        if (sel) sel.value = fromStage || '';
        dragError(err && err.message ? err.message : 'Could not move the lead. Please try again.');
      });
  }
  document.addEventListener('drop', function (e) {
    var col = e.target.closest('.kan-col[data-stage]');
    if (!col || !dragged || kanDropped) return;
    e.preventDefault();
    col.classList.remove('over');
    var stage = col.getAttribute('data-stage');
    var id = dragged.getAttribute('data-id');
    if (!id || !stage) return;
    var from = dragged.parentElement;
    var fromStage = from ? from.getAttribute('data-drop') : null;
    if (fromStage === stage) return; // dropped back where it started
    kanDropped = true;
    kanMoveCard(dragged, stage, fromStage, '/leads/' + id + '/move');
  });
  // Tasks calendar: drag an event pill onto another day to reschedule it (the
  // edit form date field remains the no-JS fallback). Events are delegated on
  // document so a seamless calendar refresh (filter / month nav / quick-add)
  // keeps the gesture working on the freshly swapped DOM.
  function calEl() { return document.querySelector('.cal-big'); }
  var calDrag = null, calDropped = false;
  document.addEventListener('dragstart', function (e) {
    if (!calEl()) return;
    var ev = e.target.closest('.ev[data-id]');
    if (!ev) return;
    calDrag = ev; calDropped = false;
    ev.classList.add('drag');
    try { e.dataTransfer.setData('text/plain', ev.dataset.id || ''); e.dataTransfer.effectAllowed = 'move'; } catch (err) {}
  });
  document.addEventListener('dragend', function () {
    if (calDrag) calDrag.classList.remove('drag');
    calDrag = null; calDropped = false;
    document.querySelectorAll('.cal-d.over').forEach(function (d) { d.classList.remove('over'); });
  });
  document.addEventListener('dragenter', function (e) {
    if (!calDrag || !e.target.closest('.cal-d.day[data-date]')) return;
    e.preventDefault();
  });
  document.addEventListener('dragover', function (e) {
    var day = e.target.closest('.cal-d.day[data-date]');
    if (!day || !calDrag) return;
    e.preventDefault();
    try { e.dataTransfer.dropEffect = 'move'; } catch (err) {}
    if (!day.classList.contains('over')) day.classList.add('over');
  });
  document.addEventListener('dragleave', function (e) {
    var day = e.target.closest('.cal-d.day[data-date]');
    if (!day) return;
    if (e.relatedTarget && day.contains(e.relatedTarget)) return;
    day.classList.remove('over');
  });
  // Swap one day cell's pills for freshly rendered markup (the date-number link
  // stays put) so a move settles in place and the grid never re-renders.
  function calDayPills(date, html) {
    if (!date) return;
    var day = document.querySelector('.cal-d.day[data-date="' + date + '"]');
    if (!day) return;
    day.querySelectorAll('.ev, .hint').forEach(function (n) { n.remove(); });
    var dnum = day.querySelector('.dnum');
    if (dnum) dnum.insertAdjacentHTML('afterend', html);
    else day.insertAdjacentHTML('beforeend', html);
  }
  document.addEventListener('drop', function (e) {
    var day = e.target.closest('.cal-d.day[data-date]');
    if (!day || !calDrag || calDropped) return;
    e.preventDefault();
    day.classList.remove('over');
    var date = day.getAttribute('data-date');
    var id = calDrag.getAttribute('data-id');
    if (!id || !date) return;
    var ev = calDrag, fromDay = ev.closest('.cal-d.day');
    var fromDate = fromDay ? fromDay.getAttribute('data-date') : null;
    if (fromDate === date) return; // dropped back where it started
    calDropped = true;
    // Optimistic: park the pill on the target day, then re-render both days from
    // the server once it confirms (pill order, counts and "+N more" included).
    var ref = day.querySelector('.dnum');
    if (ref) ref.after(ev); else day.appendChild(ev);
    var statusSel = document.querySelector('form[data-ajax-filter] select[name=status]');
    var url = '/tasks/' + id + '/move?ajax=1' + (statusSel && statusSel.value ? '&status=' + encodeURIComponent(statusSel.value) : '');
    var token = document.querySelector('meta[name=csrf-token]');
    moveFetch(url, {due_date: date, _token: token ? token.content : ''})
      .then(function (data) {
        if (data && data.from) calDayPills(data.from.date, data.from.html);
        if (data && data.to) calDayPills(data.to.date, data.to.html);
      })
      .catch(function (err) {
        var back = fromDay ? fromDay.querySelector('.dnum') : null;
        if (back) back.after(ev); else if (fromDay) fromDay.appendChild(ev);
        dragError(err && err.message ? err.message : 'Could not move the task. Please try again.');
      });
  });
  // Month navigation (← Today →) swaps just the calendar region — no full
  // reload — and keeps the filter form's month field in sync with the view.
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-cal-nav]');
    if (!a || !calEl()) return;
    e.preventDefault();
    regionSwap('tasks-list', a.href, true).then(function (done) {
      if (!done) return;
      var input = document.querySelector('form[data-ajax-filter] input[name=month]');
      if (input) {
        var m = new URL(a.href, window.location.origin).searchParams.get('month');
        input.value = m || '';
      }
    });
  });
  // Documents explorer: folder browsing without full page reloads.
  // Sidebar, crumbs, search and pager swap the whole explorer fragment;
  // uploads, downloads and deletes keep their native behaviour.
  (function explorer() {
    function layout() { return document.querySelector('.fm-layout'); }
    if (!layout()) return;
    function swap(url, push) {
      var root = layout();
      if (!root) { window.location = url; return; }
      var searchInput = root.querySelector('.fm-side input[name=q]');
      var refocus = searchInput && document.activeElement === searchInput;
      root.classList.add('fm-loading');
      fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}})
        .then(function (res) { if (!res.ok) throw new Error('load failed'); return res.text(); })
        .then(function (html) {
          var tmp = document.createElement('div');
          tmp.innerHTML = html;
          var fresh = tmp.querySelector('.fm-layout');
          if (!fresh) { window.location = url; return; }
          root.replaceWith(fresh);
          if (motionOK()) fresh.classList.add('swap-in');
          if (push !== false) history.pushState({fm: 1}, '', url);
          var si = document.querySelector('.fm-side input[name=q]');
          if (si && refocus) { si.focus(); si.setSelectionRange(si.value.length, si.value.length); }
        })
        .catch(function () { window.location = url; });
    }
    document.addEventListener('click', function (e) {
      if (!layout()) return;
      var a = e.target.closest('.fm-layout a');
      if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
      var href = a.getAttribute('href');
      if (!href || href.charAt(0) === '#') return;
      var url = new URL(href, window.location.origin);
      if (url.origin !== window.location.origin || url.pathname !== '/documents') return;
      if (url.href === window.location.href) { e.preventDefault(); return; }
      e.preventDefault();
      swap(url.href, true);
    });
    document.addEventListener('submit', function (e) {
      var f = e.target;
      if (!f.matches || !f.matches('.fm-side form')) return;
      if (!layout()) return;
      e.preventDefault();
      var params = new URLSearchParams(new FormData(f)).toString();
      swap(f.action + (params ? '?' + params : ''), true);
    });
    window.addEventListener('popstate', function () {
      if (layout()) swap(window.location.href, false);
    });
  })();
  // ---------- seamless lists: filters, pagination, quick-adds, toggles, actions.
  // Markers (all progressive enhancement — everything works natively without JS):
  //   [data-ajax-region="name"]  swappable list container
  //   form[data-ajax-filter][data-region]  GET filter swapping that region
  //   form[data-ajax-add][data-region]     quick-add POST, then refreshes region
  //   form[data-ajax-toggle]               rowcheck done/undo flip, no reload
  //   form[data-ajax-post][data-refresh]   one-click POST; flash + refresh selectors
  //   ("page" = the .page container; entrance animations replay softly on swap)
  function parseFlash(html) {
    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    var err = tmp.querySelector('.err');
    if (err) return {ok: false, msg: err.innerText.trim().slice(0, 220)};
    var fl = tmp.querySelector('[data-flash]') || tmp.querySelector('.alert');
    return {ok: true, msg: fl ? fl.innerText.trim().slice(0, 220) : ''};
  }
  function formAction(f) { return f.getAttribute('action') || window.location.href; }
  function regionEl(name) {
    return name === 'page' ? document.querySelector('.page') : document.querySelector('[data-ajax-region="' + name + '"]');
  }
  function regionSwap(name, url, push) {
    var region = regionEl(name);
    if (!region) { window.location = url; return Promise.resolve(false); }
    region.classList.add('ajax-busy');
    if (motionOK()) region.classList.add('swap-out');
    return fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}})
      .then(function (res) { if (!res.ok) throw new Error('load'); return res.text(); })
      .then(function (html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        var sel = name === 'page' ? '.page' : '[data-ajax-region="' + name + '"]';
        var fresh = tmp.querySelector(sel);
        if (!fresh) { window.location = url; return false; }
        var swap = function () {
          region.replaceWith(fresh);
          if (motionOK()) fresh.classList.add('swap-in');
          kanbanMarkLinks(fresh); // board cards' links must stay non-draggable after a swap
          if (push) history.pushState({ajax: 1}, '', url);
        };
        // Let the outgoing fade read before swapping in the fresh region.
        // Callers (calendar nav) act on the swapped DOM, so resolve after it.
        if (!motionOK()) { swap(); return true; }
        return new Promise(function (resolve) { setTimeout(function () { swap(); resolve(true); }, 130); });
      })
      .catch(function () { window.location = url; return false; });
  }
  function refreshSelectors(list) {
    var jobs = (list || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean).map(function (sel) {
      return regionSwap(sel === 'page' ? 'page' : sel.replace(/^\[data-ajax-region="(.+)"\]$/, '$1'), window.location.href, false);
    });
    return Promise.all(jobs);
  }
  function ajaxPostFlow(form, refresh) {
    var fd = new FormData(form);
    form.classList.add('ajax-busy');
    var spinBtn = form.querySelector('button[type=submit],button.btn,input[type=submit]');
    if (spinBtn) spinBtn.classList.add('is-loading');
    var doneSpin = function () { if (spinBtn) spinBtn.classList.remove('is-loading'); };
    fetch(formAction(form), {method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}})
      .then(function (res) { return res.text().then(function (html) { return {status: res.status, html: html}; }); })
      .then(function (out) {
        form.classList.remove('ajax-busy'); doneSpin();
        var p = parseFlash(out.html);
        var done = function () { if (p.msg) ajaxFlash(p.msg, !p.ok); };
        if (p.ok && refresh) refreshSelectors(refresh).then(done);
        else done();
      })
      .catch(function () { form.classList.remove('ajax-busy'); doneSpin(); ajaxFlash('Something went wrong. Please try again.', true); });
  }
  document.addEventListener('click', function (e) {
    var pg = e.target.closest('[data-ajax-region] nav.pager a');
    if (!pg || document.querySelector('.fm-layout')) return;
    e.preventDefault();
    regionSwap(pg.closest('[data-ajax-region]').getAttribute('data-ajax-region'), pg.href, true);
  });
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches) return;
    if (document.querySelector('.fm-layout') && (f.closest('.fm-layout'))) return; // explorer owns its own
    if (f.matches('form[data-ajax-filter]')) {
      e.preventDefault();
      var name = f.getAttribute('data-region') || 'list';
      var url = new URL(formAction(f), window.location.origin);
      new FormData(f).forEach(function (v, k) { url.searchParams.set(k, v); });
      url.searchParams.delete('page');
      regionSwap(name, url.href, true);
    }
    else if (f.matches('form[data-ajax-add]')) {
      e.preventDefault();
      var addRegion = f.getAttribute('data-region');
      f.classList.add('ajax-busy');
      fetch(formAction(f), {method: 'POST', body: new FormData(f), headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}})
        .then(function (res) { return res.text().then(function (html) { return {html: html}; }); })
        .then(function (out) {
          f.classList.remove('ajax-busy');
          var p = parseFlash(out.html);
          if (!p.ok) { ajaxFlash(p.msg || 'Could not save. Please try again.', true); return; }
          f.reset();
          ajaxFlash(p.msg || 'Saved.');
          if (addRegion) regionSwap(addRegion, window.location.href, false);
        })
        .catch(function () { f.classList.remove('ajax-busy'); ajaxFlash('Could not save. Please try again.', true); });
    }
    else if (f.matches('form[data-ajax-toggle]')) {
      e.preventDefault();
      var btn = f.querySelector('.rowcheck');
      var was = btn && btn.classList.contains('done');
      if (btn) { btn.classList.toggle('done'); btn.innerHTML = was ? '' : '✓'; }
      fetch(formAction(f), {method: 'POST', body: new FormData(f), headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}})
        .then(function (res) { if (!res.ok) throw new Error('toggle'); })
        .catch(function () {
          if (btn) { btn.classList.toggle('done'); btn.innerHTML = was ? '✓' : ''; }
          ajaxFlash('Could not update. Please try again.', true);
        });
    }
    else if (f.matches('form[data-ajax-post]')) {
      if (f.matches('[data-confirm]') && f.dataset.confirmed !== '1') return; // confirm modal drives it
      e.preventDefault();
      ajaxPostFlow(f, f.getAttribute('data-refresh'));
    }
    // Kanban card moves: the per-card stage <select> and the ‹ › arrows post to
    // the same move endpoint — route them through the same in-place update.
    else if (f.closest('.kan-card')) {
      var stageEl = f.querySelector('input[name=stage], select[name=stage]');
      if (!stageEl) return; // not a move (the convert form goes via the confirm modal)
      e.preventDefault();
      kanMoveCard(f.closest('.kan-card'), stageEl.value, null, f.getAttribute('action'));
    }
  });
  // Notifications: mark-as-read (a single row or all of them) without a reload.
  // The endpoint answers with the new unread count so the bell badge, the rows
  // and both "mark all" affordances stay in sync everywhere.
  function notesSetUnread(count) {
    var badge = document.querySelector('.bell-badge');
    if (count === 0) { if (badge) badge.remove(); return; }
    if (!badge) {
      var pill = document.querySelector('button[data-drop-toggle]');
      if (!pill) return;
      badge = document.createElement('span');
      badge.className = 'bell-badge';
      pill.appendChild(badge);
    }
    badge.textContent = count > 9 ? '9+' : count;
  }
  // Strip one row's unread styling: the "new" badge, the highlighted icon and
  // its ✓ button.
  function notesClearRow(row) {
    if (!row) return;
    var badge = row.querySelector('.badge.b-blue');
    if (badge) badge.remove();
    var ic = row.querySelector('.mi-ic svg');
    if (ic) ic.style.color = '#5b5648';
    var rf = row.querySelector('form[data-notes-read]');
    if (rf) rf.remove();
  }
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches || !f.matches('form[data-notes-read]')) return;
    e.preventDefault();
    var token = document.querySelector('meta[name=csrf-token]');
    f.classList.add('ajax-busy');
    fetch(f.action + (f.action.indexOf('?') > -1 ? '&' : '?') + 'ajax=1', {
      method: 'POST',
      body: new FormData(f),
      headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : ''}
    })
      .then(function (res) {
        if (res.status === 419) throw new Error('Session expired — refresh the page and try again.');
        if (!res.ok) throw new Error('read failed');
        return res.json();
      })
      .then(function (data) {
        f.classList.remove('ajax-busy');
        var unread = data && typeof data.unread === 'number' ? data.unread : null;
        if (unread !== null) notesSetUnread(unread);
        // A per-row form clears just its row; a "mark all" form clears them all.
        var row = f.closest('.mi-row');
        if (row) notesClearRow(row); else document.querySelectorAll('.mi-row').forEach(notesClearRow);
        if (unread === 0) {
          document.querySelectorAll('[data-notes-all]').forEach(function (a) { a.style.display = 'none'; });
          document.querySelectorAll('.drop-notes .note-item.unread').forEach(function (a) { a.classList.remove('unread'); });
        }
        if (data && data.msg) ajaxFlash(data.msg);
      })
      .catch(function (err) {
        f.classList.remove('ajax-busy');
        ajaxFlash(err && err.message ? err.message : 'Could not mark as read. Please try again.', true);
      });
  });
  window.addEventListener('popstate', function () {
    if (document.querySelector('.fm-layout')) return; // explorer owns it
    document.querySelectorAll('[data-ajax-region]').forEach(function (r) {
      regionSwap(r.getAttribute('data-ajax-region'), window.location.href, false);
    });
  });
  // ---------- bulk select + actions (leads, clients, tasks, projects).
  // Checkbox column + floating bar per list card. Deletes confirm through the
  // shared modal; stage/status posts go straight through, then the region
  // swaps so counts, badges and empty states stay truthful.
  function bulkPaint(card) {
    var bar = card ? card.querySelector('[data-bulk-bar]') : null;
    if (!bar) return;
    var boxes = card ? Array.from(card.querySelectorAll('.bulk-check')) : [];
    var n = boxes.filter(function (c) { return c.checked; }).length;
    bar.hidden = n === 0;
    var count = bar.querySelector('[data-bulk-count]');
    if (count) count.textContent = n;
    var all = card.querySelector('[data-bulk-all]');
    if (all) { all.checked = n > 0 && n === boxes.length; all.indeterminate = n > 0 && n < boxes.length; }
    selPaint(card);
  }
  function bulkRun(bar, payload) {
    var token = document.querySelector('meta[name=csrf-token]');
    bar.classList.add('ajax-busy');
    fetch(bar.getAttribute('data-bulk-url'), {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : ''},
      body: JSON.stringify(payload)
    })
      .then(function (res) { return res.json().then(function (j) { return {status: res.status, json: j}; }); })
      .then(function (out) {
        bar.classList.remove('ajax-busy');
        if (out.status === 419) { ajaxFlash('Session expired — refresh the page and try again.', true); return; }
        if (!out.json || out.json.ok !== true) { ajaxFlash('Bulk action failed. Please try again.', true); return; }
        var done = out.json.done || 0;
        var errs = out.json.errors || [];
        var msg = done + ' item' + (done === 1 ? '' : 's') + ' updated.';
        if (errs.length) msg += ' Skipped: ' + errs.join('; ');
        ajaxFlash(msg, errs.length > 0 && done === 0);
        var region = bar.getAttribute('data-region');
        var repaint = function () { if (bar.isConnected) bulkPaint(bar.closest('.card')); };
        if (region) regionSwap(region, window.location.href, false).then(repaint);
        else repaint();
      })
      .catch(function () { bar.classList.remove('ajax-busy'); ajaxFlash('Bulk action failed. Please try again.', true); });
  }
  document.addEventListener('change', function (e) {
    if (!e.target.matches) return;
    if (e.target.matches('[data-bulk-all]')) {
      var card = e.target.closest('.card');
      var on = e.target.checked;
      if (card) card.querySelectorAll('.bulk-check').forEach(function (c) { c.checked = on; });
      bulkPaint(card);
    } else if (e.target.matches('.bulk-check')) {
      bulkPaint(e.target.closest('.card'));
    }
  });
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-bulk-apply]');
    if (!btn) return;
    var bar = btn.closest('[data-bulk-bar]');
    if (!bar) return;
    var ids = Array.from((bar.closest('.card') || document).querySelectorAll('.bulk-check:checked')).map(function (c) { return parseInt(c.value, 10); }).filter(function (v) { return v > 0; });
    if (!ids.length) return;
    var action = btn.getAttribute('data-bulk-apply');
    var payload = {action: action, ids: ids};
    if (action !== 'delete') {
      var sel = bar.querySelector('select[data-bulk-value]');
      if (!sel || !sel.value) { ajaxFlash('Pick a value first.', true); return; }
      payload[sel.getAttribute('name')] = sel.value;
      bulkRun(bar, payload);
      return;
    }
    pendingConfirm = {bulk: {bar: bar, payload: payload}};
    openConfirm('Delete ' + ids.length + ' selected item' + (ids.length === 1 ? '' : 's') + '? This moves them to Trash.', {tone: 'danger', title: 'Bulk delete?', ok: 'Delete'});
  });
})();

// Theme: OS follows automatically via CSS; a stored choice wins.
// Restores before first interaction; any [data-theme-toggle] flips it.
(function theme() {
  function current() {
    var t = document.documentElement.getAttribute('data-theme');
    if (t) return t;
    try {
      if (matchMedia('(prefers-color-scheme: dark)').matches) return 'dark';
    } catch (e) {}
    return 'light';
  }
  try {
    var s = localStorage.getItem('theme');
    if (s === 'dark' || s === 'light') document.documentElement.setAttribute('data-theme', s);
  } catch (e) {}
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-theme-toggle]')) return;
    var next = current() === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('theme', next); } catch (err) {}
  });
})();

// Sidebar icon-rail persists across page loads (desktop only).
(function rail() {
  try {
    if (localStorage.getItem('siderail') === '1' && window.innerWidth > 1024) document.body.classList.add('side-min');
  } catch (e) {}
})();

// Rows per page: every paginated table shares the footer picker (?per_page=).
document.addEventListener('change', function (e) {
  if (!e.target.matches || !e.target.matches('[data-per-page]')) return;
  var u = new URL(window.location.href);
  u.searchParams.set('per_page', e.target.value);
  u.searchParams.delete('page');
  window.location = u.toString();
});
// Template-style "N of M row(s) selected" footer on bulk tables (leads,
// clients, tasks, projects, products). Auto-inserted per card, so every
// current and future bulk table gets it with zero view edits.
function selEnsure(card) {
  if (!card || !card.querySelector('[data-bulk-bar]')) return null;
  var foot = card.querySelector('.sel-foot');
  if (!foot) {
    foot = document.createElement('div');
    foot.className = 'tbl-foot sel-foot';
    var pg = card.querySelector('.pg-foot');
    if (pg) pg.before(foot);
    else {
      var sc = card.querySelector('.tbl-scroll');
      if (sc && sc.parentElement === card) sc.after(foot);
      else card.appendChild(foot);
    }
  }
  return foot;
}
function selPaint(card) {
  var foot = selEnsure(card);
  if (!foot) return;
  var boxes = card.querySelectorAll('.bulk-check');
  var n = 0;
  boxes.forEach(function (c) { if (c.checked) n++; });
  foot.innerHTML = '<span>' + n + ' of ' + boxes.length + ' row(s) selected.</span>';
  foot.style.display = boxes.length ? '' : 'none';
}
var selQueued = false;
function selRepaintAll() {
  if (selQueued) return;
  selQueued = true;
  if (window.requestAnimationFrame) {
    requestAnimationFrame(function () {
      selQueued = false;
      document.querySelectorAll('.card').forEach(selPaint);
    });
  } else {
    selQueued = false;
    document.querySelectorAll('.card').forEach(selPaint);
  }
}
document.addEventListener('DOMContentLoaded', selRepaintAll);
if (document.readyState !== 'loading') selRepaintAll();
try {
  new MutationObserver(selRepaintAll).observe(document.body, {childList: true, subtree: true});
} catch (e) {}

(function sideGroups() {
  var KEY = 'sidegroups';
  function read() {
    try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; }
    catch (e) { return {}; }
  }
  function write(s) {
    try { localStorage.setItem(KEY, JSON.stringify(s)); } catch (e) {}
  }
  function apply() {
    var saved = read();
    document.querySelectorAll('[data-side-group]').forEach(function (g) {
      var name = g.getAttribute('data-side-group');
      var hasActive = !!g.querySelector('a.active');
      var open = hasActive ? true : (name in saved ? !!saved[name] : true);
      g.classList.toggle('open', open);
      var btn = g.querySelector('[data-side-toggle]');
      if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-side-toggle]') : null;
    if (!btn) return;
    var g = btn.closest('[data-side-group]');
    if (!g) return;
    var open = !g.classList.contains('open');
    g.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    var saved = read();
    saved[g.getAttribute('data-side-group')] = open;
    write(saved);
  });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply);
  else apply();
})();
(function () {
  var audioCtx = null;
  function ensureAudio() {
    var Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    if (!audioCtx) {
      try { audioCtx = new Ctx(); } catch (e) { return null; }
    }
    if (audioCtx.state === 'suspended') { try { audioCtx.resume(); } catch (e) {} }
    return audioCtx;
  }
  function tone(ctx, freq, start, duration, volume) {
    var osc = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.setValueAtTime(freq, start);
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(volume, start + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start(start);
    osc.stop(start + duration + 0.02);
  }
  function playChime() {
    var ctx = ensureAudio();
    if (!ctx) return;
    var now = ctx.currentTime;
    tone(ctx, 880, now, 0.16, 0.12);
    tone(ctx, 1318.5, now + 0.13, 0.22, 0.09);
  }
  document.addEventListener('pointerdown', function () { ensureAudio(); }, { once: true, passive: true });

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', function (e) {
      var data = e.data || {};
      if (data.type !== 'crm-push') return;
      playChime();
      if (window.crmFlash) window.crmFlash((data.title || 'New notification') + (data.body ? ' � ' + data.body : ''));
    });
  }

  var box = document.querySelector('[data-push-settings]');
  if (!box) return;
  var statusEl = box.querySelector('[data-push-status]');
  var enableBtn = box.querySelector('[data-push-enable]');
  var disableBtn = box.querySelector('[data-push-disable]');
  var testBtn = box.querySelector('[data-push-test]');
  var csrf = box.getAttribute('data-csrf') || '';
  var swUrl = box.getAttribute('data-sw-url');
  var supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

  function setStatus(text, err) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.style.color = err ? 'var(--danger)' : '';
  }
  function api(url, options) {
    options = options || {};
    options.credentials = 'same-origin';
    options.headers = Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, options.headers || {});
    return fetch(url, options).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok) throw new Error(data.message || (data.errors ? Object.values(data.errors)[0][0] : 'Request failed.'));
        return data;
      });
    });
  }
  function keyToBase64(buffer) {
    var bytes = new Uint8Array(buffer);
    var out = '';
    for (var i = 0; i < bytes.length; i++) out += String.fromCharCode(bytes[i]);
    return btoa(out).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }
  function urlBase64ToUint8Array(base64) {
    var padding = '='.repeat((4 - base64.length % 4) % 4);
    var raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }
  function saveSubscription(sub) {
    var json = sub.toJSON();
    json.contentEncoding = 'aes128gcm';
    return api(box.getAttribute('data-subscribe-url'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(json)
    });
  }
  function paint(sub) {
    enableBtn.style.display = sub ? 'none' : '';
    disableBtn.style.display = sub ? '' : 'none';
    testBtn.style.display = sub ? '' : 'none';
    setStatus(sub ? 'On for this device � system notifications use your device sound.' : 'Off for this device.');
  }
  function registerWorker() {
    return navigator.serviceWorker.register(swUrl, { updateViaCache: 'none' }).then(function (reg) {
      return navigator.serviceWorker.ready.then(function () { return reg; });
    });
  }
  function refresh() {
    if (!supported) {
      setStatus('This browser does not support push notifications.', true);
      enableBtn.style.display = 'none';
      disableBtn.style.display = 'none';
      testBtn.style.display = 'none';
      return;
    }
    navigator.serviceWorker.getRegistration().then(function (reg) {
      if (!reg) { paint(null); return null; }
      return reg.pushManager.getSubscription().then(function (sub) {
        paint(sub);
        if (sub && Notification.permission === 'granted') return saveSubscription(sub).catch(function () {});
        return null;
      });
    });
  }
  enableBtn.addEventListener('click', function () {
    if (!supported) return;
    if (!window.isSecureContext) {
      setStatus('Push needs HTTPS (or localhost). Open the site over a secure connection.', true);
      return;
    }
    enableBtn.disabled = true;
    Notification.requestPermission().then(function (permission) {
      if (permission !== 'granted') {
        throw new Error('Notification permission was not granted.');
      }
      return registerWorker();
    }).then(function (reg) {
      return api(box.getAttribute('data-public-key-url'), { method: 'GET' }).then(function (data) {
        if (!data.publicKey) throw new Error('Push key is missing on the server.');
        return reg.pushManager.getSubscription().then(function (old) {
          var opts = { userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(data.publicKey) };
          if (!old) return reg.pushManager.subscribe(opts);
          return old.unsubscribe().then(function () { return reg.pushManager.subscribe(opts); });
        });
      });
    }).then(function (sub) {
      return saveSubscription(sub).then(function () {
        paint(sub);
        setStatus('Enabled. Send a test to check sound and picture.');
        if (window.crmFlash) window.crmFlash('Push notifications enabled.');
      });
    }).catch(function (err) {
      setStatus(err && err.message ? err.message : 'Could not enable push notifications.', true);
    }).then(function () {
      enableBtn.disabled = false;
    });
  });
  disableBtn.addEventListener('click', function () {
    navigator.serviceWorker.getRegistration().then(function (reg) {
      return reg ? reg.pushManager.getSubscription() : null;
    }).then(function (sub) {
      if (!sub) return null;
      return api(box.getAttribute('data-unsubscribe-url'), {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ endpoint: sub.endpoint })
      }).then(function () { return sub.unsubscribe(); });
    }).then(function () {
      paint(null);
      setStatus('Push notifications are off on this device.');
    }).catch(function (err) {
      setStatus(err && err.message ? err.message : 'Could not disable push notifications.', true);
    });
  });
  testBtn.addEventListener('click', function () {
    testBtn.disabled = true;
    api(box.getAttribute('data-test-url'), { method: 'POST' }).then(function () {
      setStatus('Test sent. Check your notifications.');
    }).catch(function (err) {
      setStatus(err && err.message ? err.message : 'Could not send the test.', true);
    }).then(function () {
      testBtn.disabled = false;
    });
  });
  refresh();
})();
