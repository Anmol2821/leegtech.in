/* Leegtech site script: navigation, lead forms, attribution, consent and conversion tracking. */
(function () {
  'use strict';
  var LT = window.LT || {};
  var ls = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  };
  var ss = {
    get: function (k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} },
    del: function (k) { try { sessionStorage.removeItem(k); } catch (e) {} }
  };
  window.dataLayer = window.dataLayer || [];
  function push(obj) { window.dataLayer.push(obj); }
  function fbq() { if (window.fbq) window.fbq.apply(null, arguments); }

  /* ---------- Navigation ---------- */
  var burger = document.querySelector('.nav-burger');
  if (burger) {
    burger.addEventListener('click', function () {
      var open = document.body.classList.toggle('nav-open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      burger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    });
  }
  document.querySelectorAll('.menu-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item = btn.closest('.nav-item');
      var open = item.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.body.classList.contains('nav-open')) { burger.click(); burger.focus(); }
  });

  /* ---------- Attribution (UTM / click IDs) ---------- */
  var KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'];
  var params = new URLSearchParams(location.search);
  var attr = {};
  try { attr = JSON.parse(ls.get('lt_attr') || '{}'); } catch (e) { attr = {}; }
  var hasNew = KEYS.some(function (k) { return params.get(k); });
  if (hasNew) {
    attr = {};
    KEYS.forEach(function (k) { if (params.get(k)) attr[k] = params.get(k).slice(0, 200); });
    attr.landing_page = location.pathname + location.search;
    attr.ts = Date.now();
  }
  if (!attr.landing_page) attr.landing_page = location.pathname;
  if (!attr.referrer && document.referrer && document.referrer.indexOf(location.host) === -1) attr.referrer = document.referrer.slice(0, 300);
  ls.set('lt_attr', JSON.stringify(attr));

  /* ---------- Click tracking ---------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a');
    if (!a) return;
    var href = a.getAttribute('href') || '';
    var where = location.pathname;
    if (href.indexOf('tel:') === 0) { push({ event: 'click_call', link_url: href, page_path: where }); fbq('track', 'Contact', { method: 'phone' }); }
    else if (href.indexOf('wa.me') > -1) { push({ event: 'click_whatsapp', link_url: href, page_path: where }); fbq('track', 'Contact', { method: 'whatsapp' }); }
    else if (href.indexOf('mailto:') === 0) { push({ event: 'click_email', link_url: href, page_path: where }); fbq('track', 'Contact', { method: 'email' }); }
    else if (a.dataset.cta) { push({ event: 'cta_click', cta_location: a.dataset.cta, page_path: where }); }
  });

  /* ---------- Lead forms ---------- */
  var phoneRe = /^[+]?[0-9 ()-]{10,16}$/;
  var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  function validate(form) {
    var ok = true;
    form.querySelectorAll('[required]').forEach(function (el) {
      var field = el.closest('.field');
      var v = (el.value || '').trim();
      var good = el.type === 'checkbox' ? el.checked : !!v;
      if (good && el.name === 'phone') good = phoneRe.test(v) && v.replace(/\D/g, '').length >= 10;
      if (good && el.type === 'email') good = emailRe.test(v);
      if (field) field.classList.toggle('invalid', !good);
      if (el.type === 'checkbox') { var ce = form.querySelector('.err-consent'); if (ce) ce.classList.toggle('show', !good); }
      if (!good && ok) { ok = false; el.focus(); }
    });
    return ok;
  }
  document.querySelectorAll('form.lead-form').forEach(function (form) {
    // Fill hidden attribution fields
    KEYS.concat(['landing_page', 'referrer']).forEach(function (k) {
      var el = form.querySelector('input[name="' + k + '"]');
      if (el && attr[k]) el.value = attr[k];
    });
    var pg = form.querySelector('input[name="page_url"]');
    if (pg) pg.value = (location.origin + location.pathname + location.search).slice(0, 500);
    var started = false;
    form.addEventListener('input', function (e) {
      if (!started) { started = true; push({ event: 'form_start', form_id: form.id, page_path: location.pathname }); }
      var f = e.target.closest('.field');
      if (f && f.classList.contains('invalid')) f.classList.remove('invalid');
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!validate(form)) return;
      var btn = form.querySelector('button[type=submit]');
      var label = btn.innerHTML;
      btn.disabled = true; btn.textContent = 'Sending…';
      var lead = {
        id: Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10)
      };
      var data = new FormData(form);
      data.set('event_id', lead.id);
      var g = function (k) { return (data.get(k) || '').toString().trim(); };
      lead.name = g('name'); lead.email = g('email').toLowerCase(); lead.phone = g('phone').replace(/[^\d+]/g, '');
      lead.service = g('service'); lead.stage = g('startup_status'); lead.city = g('city');
      lead.message = g('message').slice(0, 600); lead.page = location.pathname;
      // Meta Pixel: Lead on submit (eventID lets Meta de-duplicate if a server event is added later).
      fbq('track', 'Lead', { content_name: lead.service || 'Consultation' }, { eventID: lead.id });
      push({ event: 'generate_lead_submit', form_id: form.id, service: lead.service });
      var done = false;
      var go = function () {
        if (done) return; done = true;
        ss.set('lt_lead', JSON.stringify(lead));
        location.href = '/thank-you/';
      };
      // Save the enquiry first; never block the WhatsApp hand-off for more than a few seconds.
      setTimeout(go, 4000);
      try {
        fetch('/api/lead.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(data).toString(), keepalive: true, credentials: 'same-origin' })
          .then(function (r) { push({ event: r.ok ? 'lead_saved' : 'lead_save_failed', status: r.status }); go(); }, go);
      } catch (err) { go(); }
    });
  });

  /* ---------- Thank-you page: fire conversion once ---------- */
  if (document.body.classList.contains('thanks')) {
    var raw = ss.get('lt_lead');
    if (raw) {
      ss.del('lt_lead');
      var lead = {};
      try { lead = JSON.parse(raw); } catch (e) {}
      var ud = {};
      if (lead.email) ud.email = lead.email;
      if (lead.phone) { var d = lead.phone.replace(/\D/g, ''); ud.phone_number = lead.phone.indexOf('+') === 0 ? '+' + d : (d.length === 12 && d.indexOf('91') === 0 ? '+' + d : '+91' + d.replace(/^0+/, '')); }
      push({ event: 'generate_lead', lead_service: lead.service || '', transaction_id: lead.id, user_data: ud });
      if (window.gtag) {
        window.gtag('set', 'user_data', ud);
        window.gtag('event', 'generate_lead', { service: lead.service || '' });
        if (LT.ads) window.gtag('event', 'conversion', { send_to: LT.ads, transaction_id: lead.id });
      }
      var lines = ['Hi Leegtech! I would like a free consultation.', '', 'Name: ' + lead.name, 'Phone: ' + lead.phone, 'Email: ' + lead.email];
      if (lead.service) lines.push('Service: ' + lead.service);
      if (lead.stage) lines.push('Startup status: ' + lead.stage);
      if (lead.city) lines.push('City: ' + lead.city);
      if (lead.message) lines.push('Requirement: ' + lead.message);
      lines.push('', '(Sent from leegtech.in' + (lead.page ? ' ' + lead.page : '') + ')');
      var waUrl = 'https://wa.me/' + (LT.wa || '') + '?text=' + encodeURIComponent(lines.join('\n'));
      var wa = document.getElementById('ty-wa');
      if (wa) wa.href = waUrl;
      var note = document.getElementById('ty-status');
      if (note) note.hidden = false;
      setTimeout(function () { location.href = waUrl; }, 1200);
    }
  }

  /* ---------- Consent banner ---------- */
  var banner = document.getElementById('consent');
  if (banner) {
    var choice = ls.get('lt_consent');
    if (!choice) banner.hidden = false;
    banner.addEventListener('click', function (e) {
      var b = e.target.closest('[data-consent]');
      if (!b) return;
      var v = b.getAttribute('data-consent');
      ls.set('lt_consent', v);
      banner.hidden = true;
      if (v === 'granted') {
        if (window.gtag) window.gtag('consent', 'update', { ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted', analytics_storage: 'granted' });
        fbq('consent', 'grant');
        push({ event: 'consent_granted' });
      }
    });
  }
})();
