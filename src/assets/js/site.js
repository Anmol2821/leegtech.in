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
    var pg = form.querySelector('input[name="page"]');
    if (pg) pg.value = location.pathname;
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
      var data = new FormData(form);
      var body = new URLSearchParams(data).toString();
      var lead = {
        name: (data.get('name') || '').toString(), email: (data.get('email') || '').toString().trim().toLowerCase(),
        phone: (data.get('phone') || '').toString().replace(/[^\d+]/g, ''), service: (data.get('service') || '').toString(),
        id: Date.now() + '-' + Math.random().toString(36).slice(2, 8)
      };
      fetch('/', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); })
        .then(function () {
          ss.set('lt_lead', JSON.stringify(lead));
          push({ event: 'generate_lead_submit', form_id: form.id, service: lead.service });
          location.href = '/thank-you/';
        })
        .catch(function () {
          btn.disabled = false; btn.innerHTML = label;
          var st = form.querySelector('.form-status');
          if (!st) { st = document.createElement('p'); st.className = 'form-status bad'; st.setAttribute('role', 'alert'); btn.insertAdjacentElement('afterend', st); }
          var wa = 'https://wa.me/' + (LT.wa || '') + '?text=' + encodeURIComponent('Hi Leegtech! I am ' + lead.name + '. I need help with ' + (lead.service || 'IP/legal services') + '. Phone: ' + lead.phone);
          st.innerHTML = 'Sorry, something went wrong. Please try again, or <a href="' + wa + '" target="_blank" rel="noopener">message us on WhatsApp</a>.';
        });
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
      fbq('track', 'Lead', { content_name: lead.service || 'Consultation' }, { eventID: lead.id });
      var wa = document.getElementById('ty-wa');
      if (wa && lead.name) {
        wa.href = 'https://wa.me/' + (LT.wa || '') + '?text=' + encodeURIComponent('Hi Leegtech! I just submitted an enquiry. Name: ' + lead.name + (lead.service ? '. Service: ' + lead.service : '') + '.');
      }
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
