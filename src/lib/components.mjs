import { site } from '../data/site.mjs';
import { categories } from '../data/categories.mjs';
import { esc, icon, waIcon, waLink } from './util.mjs';
import fs from 'node:fs';
const CLIENT_SIZES = JSON.parse(fs.readFileSync(new URL('../data/client-sizes.json', import.meta.url), 'utf8'));

export const breadcrumbs = (items) => `<nav class="crumbs" aria-label="Breadcrumb"><ol>${items.map((it, i) =>
  i === items.length - 1 ? `<li aria-current="page">${esc(it.name)}</li>` : `<li><a href="${it.path}">${esc(it.name)}</a></li>`).join('')}</ol></nav>`;

export const secHead = (eyebrow, title, sub = '', align = 'center') => `<div class="sec-head sec-${align}">
  ${eyebrow ? `<p class="eyebrow">${esc(eyebrow)}</p>` : ''}
  <h2>${title}</h2>
  ${sub ? `<p class="sec-sub">${sub}</p>` : ''}
</div>`;

export const faqId = (q) => 'q-' + q.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 70);
export const faqList = (faqs, headingTag = 'h3', { open = false, ids = false } = {}) => `<div class="faq-list">${faqs.map(([q, a, link], i) => `
  <details class="faq"${ids ? ` id="${faqId(q)}"` : ''}${open && i === 0 ? ' open' : ''}>
    <summary><${headingTag} class="faq-q">${esc(q)}</${headingTag}><span class="faq-ico" aria-hidden="true"></span></summary>
    <div class="faq-a"><p>${esc(a)}</p>${link ? `<p class="faq-more"><a href="${link}">Learn more ${icon('arrow')}</a></p>` : ''}</div>
  </details>`).join('')}</div>`;

const SVC_ICON = {
  'patent-search': 'eye', 'provisional-patent-application': 'clock', 'patent-registration': 'bulb', 'fer-response': 'doc', 'patent-hearing': 'scale',
  'patent-renewal': 'clock', 'patent-amendment': 'pen', 'pre-grant-opposition': 'scale', 'post-grant-opposition': 'scale', 'pct-application': 'globe',
  'us-patent-application': 'globe', 'uk-patent-application': 'globe', 'design-registration': 'pen', 'uk-design-registration': 'globe', 'germany-utility-model': 'bulb',
  'trademark-search': 'eye', 'trademark-registration': 'tag', 'trademark-objection-reply': 'doc', 'trademark-opposition': 'scale', 'trademark-hearing': 'scale',
  'trademark-renewal': 'clock', 'international-trademark-registration': 'globe', 'copyright-registration': 'file', 'canada-copyright-registration': 'globe',
  'private-limited-company-registration': 'building', 'llp-registration': 'team', 'one-person-company-registration': 'briefcase', 'section-8-company-registration': 'shield',
  'startup-india-registration': 'rocket', 'msme-udyam-registration': 'briefcase', 'gst-registration': 'file', 'income-tax-tds-return': 'doc',
  'shop-and-establishment-registration': 'building', 'fssai-registration': 'check', 'iso-certification': 'star', 'annual-compliance': 'clock',
  'non-disclosure-agreement': 'lock', 'employment-agreement': 'team', 'licensing-agreement': 'tag', 'franchise-agreement': 'briefcase', 'vendor-agreement': 'briefcase',
  'contract-drafting': 'pen', 'merger-agreement': 'building', 'acquisition-agreement': 'briefcase', 'trademark-coexistence-agreement': 'shield', 'legal-notice': 'mail'
};
export const svcIcon = (slug) => SVC_ICON[slug] || 'shield';
export const serviceCard = (s, catLabel, catSlug, accent) => `<a class="svc-card acc-${accent}" href="/${catSlug}/${s.slug}/">
  <span class="svc-top"><span class="svc-ico">${icon(svcIcon(s.slug))}</span><span class="svc-cat">${esc(catLabel)}</span></span>
  <h3>${esc(s.name)}</h3>
  <p>${esc(s.short)}</p>
  <span class="svc-more">Learn more ${icon('arrow')}</span>
</a>`;

export const ctaButtons = (cta = 'section', light = false) => `<div class="btn-row">
  <a class="btn btn-primary btn-lg" href="/contact/" data-cta="${cta}">Book Free Consultation ${icon('arrow')}</a>
  <a class="btn ${light ? 'btn-wa' : 'btn-wa'} btn-lg" href="${waLink(site)}" target="_blank" rel="noopener">${waIcon()} WhatsApp Us</a>
</div>`;

export const ctaBand = (title = 'Your Idea Is Worth Protecting.', text = 'Patent it. Trademark it. Copyright it. Register it. Get free expert advice from Leegtech\'s IP team.') => `<section class="cta-band">
  <div class="container cta-inner">
    <p class="eyebrow eyebrow-light">Protect your IP today</p>
    <h2>${title}</h2>
    <p>${text}</p>
    ${ctaButtons('cta-band', true)}
    <p class="cta-contact"><a href="tel:${site.phone}">${icon('phone')} ${site.phoneDisplay}</a><span aria-hidden="true">·</span><a href="mailto:${site.email}">${icon('mail')} ${site.email}</a></p>
  </div>
</section>`;

export const serviceOptions = (selected = '') => categories.map(c => `<optgroup label="${esc(c.name)}">${c.services.map(s => `<option value="${esc(s.name)}"${s.slug === selected ? ' selected' : ''}>${esc(s.name)}</option>`).join('')}</optgroup>`).join('') + `<option value="Not sure, need advice"${selected === 'not-sure' ? ' selected' : ''}>Not sure, need advice</option>`;

export const leadForm = ({ selected = '', id = 'lead', title = 'Book Your Free Consultation', compact = false } = {}) => `<form class="lead-form${compact ? ' compact' : ''}" name="consultation" method="POST" action="/thank-you/" data-netlify="true" netlify-honeypot="company_website" id="${id}" novalidate>
  <input type="hidden" name="form-name" value="consultation">
  <p class="hp" aria-hidden="true"><label>Leave this empty <input name="company_website" tabindex="-1" autocomplete="off"></label></p>
  <div class="lf-head"><h2 class="lf-title">${esc(title)}</h2><p>Your enquiry is sent to us on WhatsApp. No obligation.</p></div>
  <div class="lf-grid">
    <div class="field"><label for="${id}-name">Full name <span aria-hidden="true">*</span></label><input id="${id}-name" name="name" type="text" autocomplete="name" required placeholder="Your full name"><span class="err">Please enter your name</span></div>
    <div class="field"><label for="${id}-phone">Phone / WhatsApp <span aria-hidden="true">*</span></label><input id="${id}-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="+91 98765 43210" pattern="^[+]?[0-9 ()-]{10,16}$"><span class="err">Please enter a valid phone number</span></div>
    <div class="field"><label for="${id}-email">Email address <span aria-hidden="true">*</span></label><input id="${id}-email" name="email" type="email" autocomplete="email" required placeholder="you@company.com"><span class="err">Please enter a valid email</span></div>
    <div class="field"><label for="${id}-service">Service you need <span aria-hidden="true">*</span></label><select id="${id}-service" name="service" required><option value="">Select a service</option>${serviceOptions(selected)}</select><span class="err">Please select a service</span></div>
    <div class="field"><label for="${id}-stage">Are you a startup?</label><select id="${id}-stage" name="startup_status"><option value="">Select (optional)</option><option>Yes, DPIIT recognised</option><option>Yes, early stage</option><option>No, established business</option><option>Individual / inventor</option></select></div>
    <div class="field"><label for="${id}-city">City</label><input id="${id}-city" name="city" type="text" autocomplete="address-level2" placeholder="Your city"></div>
    <div class="field field-full"><label for="${id}-msg">Tell us about your requirement <span class="opt">(optional)</span></label><textarea id="${id}-msg" name="message" rows="3" placeholder="Describe your invention, brand, business or legal need briefly..."></textarea></div>
  </div>
  <input type="hidden" name="utm_source"><input type="hidden" name="utm_medium"><input type="hidden" name="utm_campaign"><input type="hidden" name="utm_term"><input type="hidden" name="utm_content"><input type="hidden" name="gclid"><input type="hidden" name="fbclid"><input type="hidden" name="landing_page"><input type="hidden" name="referrer"><input type="hidden" name="page">
  <label class="consent-check"><input type="checkbox" name="consent" value="yes" required> <span>I agree to be contacted by Leegtech on WhatsApp, phone or email about my enquiry, and I accept the <a href="/privacy-policy/" target="_blank">Privacy Policy</a>.</span></label>
  <span class="err err-consent">Please accept to continue</span>
  <button class="btn btn-wa btn-lg btn-block" type="submit">${waIcon()} Send Enquiry on WhatsApp</button>
  <p class="lf-note">${icon('lock')} Your information is private and used only to respond to your enquiry. Submitting this form does not create a lawyer–client relationship.</p>
</form>`;

export const steps = [
  ['Tell us what you need', 'Call, WhatsApp or book online. Describe your IP or legal requirement in a free consultation call.'],
  ['Get expert advice', 'Our team reviews your case and recommends the right service, form and strategy for your situation.'],
  ['We handle the filing', 'Document preparation, application filing and prosecution handled by our team from start to end.'],
  ['Stay protected', 'Receive your registration certificate, tracking updates and ongoing support for renewals and enforcement.']
];

export const stepsBlock = (list = steps) => `<ol class="steps">${list.map(([t, d], i) => `<li class="step"><span class="step-n">${String(i + 1).padStart(2, '0')}</span><h3>${esc(t)}</h3><p>${esc(d)}</p></li>`).join('')}</ol>`;

export const clientsStrip = (n = 27) => {
  const imgs = Array.from({ length: n }, (_, i) => {
    const k = `client-${String(i + 1).padStart(2, '0')}`; const [w, h] = CLIENT_SIZES[k];
    return `<img src="/assets/img/clients/${k}.webp" alt="Leegtech client logo ${i + 1}" loading="lazy" decoding="async" width="${Math.round(w * 64 / h)}" height="64">`;
  }).join('');
  return `<div class="logos" role="region" aria-label="Clients and partners"><div class="logos-track">${imgs}<span aria-hidden="true" class="logos-dup">${imgs.replace(/alt="[^"]*"/g, 'alt=""')}</span></div></div>`;
};

export const usps = [
  ['shield', 'End-to-end IP services', 'From ideation to registration to enforcement, we handle the complete IP lifecycle of your innovation.'],
  ['globe', 'International filing capability', 'PCT applications, US and UK patents, and Madrid trademarks: global IP protection handled seamlessly.'],
  ['team', 'Experienced team', 'Professionals experienced in patents, trademarks, copyright, company law and business compliance.'],
  ['briefcase', 'Complete business support', 'Beyond IP, we handle company formation, tax registrations, GST, FSSAI, ISO and annual compliance.'],
  ['clock', 'Fast turnaround', 'We respect deadlines. IP filings are time-critical, and we make sure your documents are filed right and on time.'],
  ['eye', 'Accessible & transparent', 'Clear communication, no hidden fees and direct access to your legal team. Serving clients across India.']
];
