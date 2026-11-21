import { site } from '../data/site.mjs';
import { categories, allServices, serviceBySlug } from '../data/categories.mjs';
import { generalFaqs } from '../data/faqs.mjs';
import { esc, icon, waIcon, waLink, fmtDate } from './util.mjs';
import {
  breadcrumbs, secHead, faqList, serviceCard, ctaButtons, ctaBand, leadForm, stepsBlock, clientsStrip, usps
} from './components.mjs';
import { orgNode, websiteNode, founderNode, crumbsNode, faqNode, pageNode, serviceNode, articleNode, graph } from './schema.mjs';

const img = (name, alt, { w, h, cls = '', eager = false, sizes = '(min-width: 900px) 50vw, 100vw', sm = true } = {}) =>
  `<img class="${cls}" src="/assets/img/${name}.webp"${sm ? ` srcset="/assets/img/${name}-sm.webp 600w, /assets/img/${name}.webp ${w}w" sizes="${sizes}"` : ''} alt="${esc(alt)}" width="${w}" height="${h}" ${eager ? 'fetchpriority="high"' : 'loading="lazy"'} decoding="async">`;

const catImg = (c, { eager = false, cls = 'art' } = {}) => `<img class="${cls}" src="/assets/img/art/${c.image}.svg" alt="${esc(c.imageAlt)}" width="600" height="420" ${eager ? 'fetchpriority="high"' : 'loading="lazy"'} decoding="async">`;

const SERVICE_COUNT = allServices.length;
const base = () => [orgNode(), websiteNode()];

/* ---------------- HOME ---------------- */
export function home(posts) {
  const p = {
    path: '/', fullTitle: 'Leegtech | Patent, Trademark & Legal Services in India',
    description: 'Patent, trademark, copyright and design registration, company registration, GST, compliance and legal drafting across India. Free initial consultation.',
    section: 'home', bodyClass: 'home'
  };
  p.schema = graph(orgNode(true), websiteNode(), founderNode(), pageNode({ ...p, title: 'Leegtech | IP, Legal & Business Services' }), faqNode(generalFaqs, '/'));
  const marqueeItems = ['Patent Registration', 'Trademark Registration', 'Copyright Registration', 'Design Registration', 'Startup India', 'PCT Applications', 'International Trademark', 'USA Patent Filing', 'NDA Drafting', 'LLP Registration', 'MSME Registration', 'GST Registration', 'FSSAI Licence', 'Section 8 Company', 'Patent Search', 'Trademark Hearing', 'Opposition Filing'];
  const spotlight = [
    ['patent', 'Patent services', 'Protect Your Invention.<br>Build Without Borders.', ['Patent search', 'Provisional filing', 'Complete specification', 'PCT filing', 'FER response', 'Hearing & written submission', 'Opposition', 'Renewal']],
    ['trademark-copyright', 'Trademark services', 'Your Brand.<br>Protected. Recognised.', ['Trademark search', 'Registration', 'Examination', 'Opposition', 'Hearing', 'Madrid Protocol', 'Renewal']],
    ['business-tax', 'Business & compliance services', 'Start. Register. Grow.<br>Stay Compliant.', ['Pvt Ltd', 'LLP', 'OPC', 'Section 8', 'Startup India', 'MSME', 'GST', 'ITR & TDS', 'Shops & Establishment', 'FSSAI', 'ISO', 'Annual compliance']],
    ['legal', 'Legal services', 'Agreements That<br>Protect Your Business.', ['NDA', 'Contracts', 'Employment', 'Licensing', 'Franchise', 'Acquisition', 'Merger', 'Legal notices']]
  ];
  const body = `
<section class="hero">
  <div class="hero-bg" aria-hidden="true"></div>
  <div class="container hero-grid">
    <div class="hero-copy">
      <p class="pill"><span class="dot"></span>${esc(site.tagline)}</p>
      <h1>Protect Your <span class="t-o">Ideas.</span> <span class="t-ink">Brand.</span> <span class="t-g">Business.</span></h1>
      <p class="lead">From patents and trademarks to copyrights, designs, startups and legal solutions, Leegtech protects what you build and helps you build what's next. <strong>Build Bold, Protect Smart.</strong></p>
      ${ctaButtons('hero')}
      <ul class="trust">
        <li>${icon('check')} ${SERVICE_COUNT}+ IP &amp; legal services</li>
        <li>${icon('check')} Free initial consultation</li>
        <li>${icon('check')} Pan-India service</li>
      </ul>
    </div>
    <div class="hero-card">
      ${leadForm({ id: 'hero-form', title: 'Get Expert Advice. Free.', compact: true })}
    </div>
  </div>
</section>

<div class="marquee" aria-hidden="true"><div class="marquee-track">${[...marqueeItems, ...marqueeItems].map(t => `<span>✦ ${t}</span>`).join('')}</div></div>

<section class="section" id="services">
  <div class="container">
    ${secHead('Our services', 'Everything IP &amp; Legal. <span class="t-o">Under One Roof.</span>', 'Choose a practice area to see every service, the process, documents needed and answers to common questions.')}
    <div class="cat-grid">
      ${categories.map(c => `<a class="cat-card acc-${c.accent}" href="/${c.slug}/">
        <span class="cat-count">${c.services.length} services</span>
        <h3>${esc(c.name)}</h3>
        <p>${esc(c.tagline)}</p>
        <ul>${c.services.slice(0, 4).map(s => `<li>${esc(s.name.replace(/\s*\(.*\)$/, ''))}</li>`).join('')}</ul>
        <span class="svc-more">Explore ${esc(c.name)} ${icon('arrow')}</span>
      </a>`).join('')}
      <a class="cat-card cat-card-cta" href="/contact/" data-cta="services-grid">
        <h3>Not sure what you need?</h3>
        <p>Tell us about your idea, brand or business. We'll recommend the right protection in a free call.</p>
        <span class="btn btn-primary">Talk to an expert ${icon('arrow')}</span>
      </a>
    </div>
  </div>
</section>

<section class="section section-soft">
  <div class="container why-grid">
    <div class="why-media"><img src="/assets/img/art/hub.svg" alt="Leegtech brings patents, designs, trademarks, copyright, business and tax, and agreements together under one roof" width="520" height="660" loading="lazy" decoding="async"></div>
    <div>
      ${secHead('Why Leegtech', 'Your Ideas Deserve <span class="t-o">Expert Protection.</span>', '', 'left')}
      <div class="usp-grid">${usps.map(([ic, t, d]) => `<div class="usp"><span class="usp-ico">${icon(ic)}</span><div><h3>${esc(t)}</h3><p>${esc(d)}</p></div></div>`).join('')}</div>
      <a class="btn btn-primary" href="/contact/" data-cta="why">Book Free Consultation ${icon('arrow')}</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    ${secHead('Service spotlight', 'What We Do <span class="t-g">Best.</span>')}
    <div class="spots">
    ${spotlight.map(([slug, eyebrow, title, tags], i) => {
      const c = categories.find(x => x.slug === slug);
      return `<article class="spot${i % 2 ? ' spot-rev' : ''}">
        <div class="spot-media">${catImg(c)}</div>
        <div class="spot-copy">
          <p class="eyebrow">${esc(eyebrow)}</p>
          <h3>${title}</h3>
          <p>${esc(c.intro)}</p>
          <ul class="tags">${tags.map(t => `<li>${esc(t)}</li>`).join('')}</ul>
          <a class="link-arrow" href="/${slug}/">View ${esc(c.name.toLowerCase())} services ${icon('arrow')}</a>
        </div>
      </article>`;
    }).join('')}
    </div>
  </div>
</section>

<section class="section section-soft" id="how-it-works">
  <div class="container">
    ${secHead('How it works', 'Start Protecting Your IP <span class="t-o">Today.</span>')}
    ${stepsBlock()}
  </div>
</section>

<section class="stats">
  <div class="container stats-grid">
    <div><strong>${SERVICE_COUNT}+</strong><span>Services</span><small>IP, business &amp; legal</small></div>
    <div><strong>5</strong><span>Practice areas</span><small>Patent, design, TM, business, legal</small></div>
    <div><strong>1 day</strong><span>Response time</span><small>Within one working day</small></div>
    <div><strong>Pan-India</strong><span>Coverage</span><small>Plus international filings</small></div>
  </div>
</section>

<section class="section" id="about-us">
  <div class="container about-grid">
    <div class="about-media">${img('founder', `${site.founder.name}, Founder of Leegtech`, { w: 501, h: 516, sm: false, sizes: '(min-width: 900px) 40vw, 100vw' })}</div>
    <div>
      ${secHead('About us', 'Innovation Protected. <span class="t-g">Business Empowered.</span>', '', 'left')}
      <p>Leegtech is your trusted partner for intellectual property and business registration services. We help innovators, startups, researchers and enterprises protect their ideas through patents, designs, trademarks and copyrights, while also supporting company formation, tax registrations and business compliance.</p>
      <blockquote class="quote"><p>“At Leegtech, we believe that every great innovation deserves strong protection and every business deserves a solid foundation for growth. Our goal is to simplify complex legal, intellectual property and compliance processes so that innovators and entrepreneurs can focus on what they do best: creating and growing.”</p><footer><strong>${esc(site.founder.name)}</strong>, Founder, Leegtech</footer></blockquote>
      <a class="link-arrow" href="/about/">More about Leegtech ${icon('arrow')}</a>
    </div>
  </div>
</section>

<section class="section section-tight">
  <div class="container">${secHead('Trusted by', 'Our Clients &amp; Partners')}</div>
  ${clientsStrip()}
</section>

<section class="section section-soft" id="consultation">
  <div class="container consult-grid">
    <div>
      ${secHead('Free consultation', 'Get Expert Advice. <span class="t-o">For Free.</span>', 'Tell us about your idea, brand or business. Our experts will recommend the right service and guide you through the next steps, completely free.', 'left')}
      <ul class="checks">
        <li>${icon('check')} Free initial consultation</li>
        <li>${icon('check')} Service recommendation</li>
        <li>${icon('check')} Process and timeline overview</li>
        <li>${icon('check')} Fee transparency, no hidden costs</li>
        <li>${icon('check')} Expert team, fast turnaround</li>
      </ul>
      <ul class="contact-list">
        <li><a href="tel:${site.phone}">${icon('phone')} ${site.phoneDisplay}</a></li>
        <li><a href="mailto:${site.email}">${icon('mail')} ${site.email}</a></li>
        <li>${icon('pin')} ${esc(site.address.display)}</li>
      </ul>
      <a class="btn btn-wa" href="${waLink(site)}" target="_blank" rel="noopener">${waIcon()} WhatsApp Us Now</a>
    </div>
    <div class="form-card">${leadForm({ id: 'home-form' })}</div>
  </div>
</section>

<section class="section">
  <div class="container narrow">
    ${secHead('FAQ', 'Frequently Asked <span class="t-o">Questions.</span>')}
    ${faqList(generalFaqs)}
    <p class="center mt"><a class="link-arrow" href="/faq/">See all FAQs ${icon('arrow')}</a></p>
  </div>
</section>

${posts.length ? `<section class="section section-soft">
  <div class="container">
    ${secHead('Insights', 'From the <span class="t-g">Leegtech Blog</span>')}
    <div class="post-grid">${posts.slice(0, 3).map(postCard).join('')}</div>
  </div>
</section>` : ''}

${ctaBand()}`;
  return { p, body };
}

/* ---------------- CATEGORY ---------------- */
export function category(c) {
  const path = `/${c.slug}/`;
  const crumbs = [{ name: 'Home', path: '/' }, { name: 'Services', path: '/services/' }, { name: c.name, path }];
  const p = { path, title: c.metaTitle, description: c.metaDesc, section: c.slug, ogImage: `/og/${c.slug}.jpg`, primaryImage: `/assets/img/art/${c.image}.svg` };
  p.schema = graph(base(), pageNode({ ...p, title: c.h1 }, 'CollectionPage'), crumbsNode(crumbs), faqNode(c.faqs, path), {
    '@type': 'ItemList', name: `${c.name} services`,
    itemListElement: c.services.map((s, i) => ({ '@type': 'ListItem', position: i + 1, name: s.name, url: `${site.url}/${c.slug}/${s.slug}/` }))
  });
  const body = `
<section class="page-hero acc-${c.accent}">
  <div class="container ph-grid">
    <div>
      ${breadcrumbs(crumbs)}
      <p class="eyebrow">${esc(c.label)}</p>
      <h1>${esc(c.h1)}</h1>
      <p class="ph-tag">${esc(c.tagline)}</p>
      <p class="lead">${esc(c.intro)}</p>
      ${ctaButtons(`cat-${c.slug}`)}
    </div>
    <div class="ph-media">${catImg(c, { eager: true, sizes: '(min-width: 900px) 45vw, 100vw' })}</div>
  </div>
</section>

<section class="section">
  <div class="container">
    ${secHead(`${c.services.length} services`, `${esc(c.name)} services we offer`, 'Select a service for the full process, documents, timelines and FAQs.')}
    <div class="svc-grid">${c.services.map(s => serviceCard(s, c.label, c.slug, c.accent)).join('')}</div>
  </div>
</section>

<section class="section section-soft">
  <div class="container">
    ${secHead('Why it matters', `Why protect with ${esc(c.name.toLowerCase())}?`)}
    <div class="point-grid">${c.points.map(([t, d]) => `<div class="point"><span class="point-ico">${icon('check')}</span><h3>${esc(t)}</h3><p>${esc(d)}</p></div>`).join('')}</div>
  </div>
</section>

<section class="section">
  <div class="container">
    ${secHead('How it works', 'A simple, transparent process')}
    ${stepsBlock()}
  </div>
</section>

<section class="section section-soft">
  <div class="container narrow">
    ${secHead('FAQ', `${esc(c.name)}: common questions`)}
    ${faqList(c.faqs)}
  </div>
</section>

<section class="section" id="consultation">
  <div class="container consult-grid">
    <div>
      ${secHead('Free consultation', `Talk to a ${esc(c.name.toLowerCase())} expert`, 'Share your requirement and get a clear recommendation, process overview and fee quote. No obligation.', 'left')}
      <ul class="checks"><li>${icon('check')} Response within one working day</li><li>${icon('check')} Fee transparency, no hidden costs</li><li>${icon('check')} Confidential handling of your information</li></ul>
    </div>
    <div class="form-card">${leadForm({ id: 'cat-form' })}</div>
  </div>
</section>
${ctaBand()}`;
  return { p, body };
}

/* ---------------- SERVICE ---------------- */
export function service(s) {
  const c = categories.find(x => x.slug === s.cat);
  const path = s.url;
  const crumbs = [{ name: 'Home', path: '/' }, { name: c.name, path: `/${c.slug}/` }, { name: s.name, path }];
  const p = { path, title: s.metaTitle, description: s.metaDesc, section: c.slug, ogImage: `/og/${c.slug}.jpg`, dateModified: site.lastReviewed };
  p.schema = graph(base(), pageNode({ ...p, title: s.name }), crumbsNode(crumbs), serviceNode(s, c), faqNode(s.faqs, path));
  const related = (s.related || []).map(r => serviceBySlug[r]).filter(Boolean);
  const body = `
<section class="page-hero svc-hero acc-${c.accent}">
  <div class="container svc-hero-grid">
    <div>
      ${breadcrumbs(crumbs)}
      <p class="eyebrow">${esc(c.label)}</p>
      <h1>${esc(s.name)}</h1>
      <p class="lead">${esc(s.lead)}</p>
      ${ctaButtons(`svc-${s.slug}`)}
      <ul class="trust trust-sm"><li>${icon('check')} Free initial consultation</li><li>${icon('check')} Transparent fees</li><li>${icon('check')} Pan-India</li></ul>
    </div>
    <aside class="facts-card" aria-label="Key facts">
      <h2>Key facts</h2>
      <dl>${s.facts.map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v)}</dd></div>`).join('')}</dl>
      <p class="facts-note">Reviewed ${fmtDate(site.lastReviewed)}. Official fees are charged at actuals.</p>
    </aside>
  </div>
</section>

<div class="container svc-layout">
  <article class="svc-main">
    <nav class="toc" aria-label="On this page"><strong>On this page</strong><ul>
      <li><a href="#overview">Overview</a></li><li><a href="#who">Who needs it</a></li><li><a href="#included">What's included</a></li>
      <li><a href="#process">Process</a></li><li><a href="#documents">Documents</a></li><li><a href="#faq">FAQs</a></li></ul></nav>

    <section id="overview" class="prose">
      <figure class="svc-art">${catImg(c)}</figure>
      <h2>What is ${esc(s.name.replace(/\s*\(.*\)$/, ''))}?</h2>
      ${s.overview.map(t => `<p>${esc(t)}</p>`).join('')}
    </section>

    <section id="who" class="block">
      <h2>Who needs this service?</h2>
      <ul class="checks two">${s.whoFor.map(t => `<li>${icon('check')} ${esc(t)}</li>`).join('')}</ul>
    </section>

    <section id="included" class="block">
      <h2>What's included</h2>
      <ul class="incl">${s.included.map(t => `<li>${icon('shield')}<span>${esc(t)}</span></li>`).join('')}</ul>
    </section>

    <section id="process" class="block">
      <h2>How the process works</h2>
      ${stepsBlock(s.process)}
    </section>

    <section id="documents" class="block">
      <h2>Documents &amp; information required</h2>
      <ul class="docs">${s.documents.map(t => `<li>${icon('file')}<span>${esc(t)}</span></li>`).join('')}</ul>
      <p class="muted small">The exact checklist depends on your facts. We confirm it during the free consultation.</p>
    </section>

    <section id="faq" class="block">
      <h2>Frequently asked questions</h2>
      ${faqList(s.faqs)}
    </section>

    <p class="disclaimer">This page is general information about ${esc(s.name)} and is not legal advice. Laws, rules and official fees change; we confirm the current position for your matter before filing. Last reviewed: ${fmtDate(site.lastReviewed)}.</p>
  </article>

  <aside class="svc-side">
    <div class="side-sticky">
      ${leadForm({ selected: s.slug, id: 'svc-form', title: 'Get a free consultation', compact: true })}
      <div class="side-contact">
        <a href="tel:${site.phone}">${icon('phone')} ${site.phoneDisplay}</a>
        <a href="${waLink(site, `Hi Leegtech! I need help with ${s.name}.`)}" target="_blank" rel="noopener">${waIcon()} WhatsApp about this service</a>
      </div>
    </div>
  </aside>
</div>

${related.length ? `<section class="section section-soft">
  <div class="container">
    ${secHead('Related', 'Related services')}
    <div class="svc-grid">${related.map(r => { const rc = categories.find(x => x.slug === r.cat); return serviceCard(r, rc.label, rc.slug, rc.accent); }).join('')}</div>
  </div>
</section>` : ''}
${ctaBand()}`;
  return { p, body };
}

/* ---------------- SERVICES HUB ---------------- */
export function servicesHub() {
  const path = '/services/';
  const crumbs = [{ name: 'Home', path: '/' }, { name: 'Services', path }];
  const p = { path, title: 'All IP, Legal & Business Services', description: `Browse all ${SERVICE_COUNT}+ Leegtech services: patents, designs, trademarks, copyright, company registration, tax, compliance and legal agreements.`, section: 'services' };
  p.schema = graph(base(), pageNode({ ...p }, 'CollectionPage'), crumbsNode(crumbs));
  const body = `
<section class="page-hero">
  <div class="container">
    ${breadcrumbs(crumbs)}
    <h1>All Services</h1>
    <p class="lead">${SERVICE_COUNT}+ intellectual property, business and legal services, all under one roof. Pick a service to see the process, documents and FAQs.</p>
  </div>
</section>
${categories.map(c => `<section class="section section-tight">
  <div class="container">
    <div class="hub-head"><h2><a href="/${c.slug}/">${esc(c.name)}</a></h2><a class="link-arrow" href="/${c.slug}/">Overview ${icon('arrow')}</a></div>
    <div class="svc-grid">${c.services.map(s => serviceCard(s, c.label, c.slug, c.accent)).join('')}</div>
  </div>
</section>`).join('')}
${ctaBand()}`;
  return { p, body };
}

/* ---------------- ABOUT ---------------- */
export function about() {
  const path = '/about/';
  const crumbs = [{ name: 'Home', path: '/' }, { name: 'About Us', path }];
  const p = { path, title: 'About Leegtech: IP, Legal & Business Services', description: `Leegtech, founded by ${site.founder.name}, helps innovators, startups and businesses across India protect their ideas and grow.`, section: 'about', primaryImage: '/assets/img/founder.webp' };
  p.schema = graph(base(), founderNode(), pageNode({ ...p, title: 'About Leegtech' }, 'AboutPage'), crumbsNode(crumbs));
  const body = `
<section class="page-hero">
  <div class="container ph-grid">
    <div>
      ${breadcrumbs(crumbs)}
      <p class="eyebrow">About us</p>
      <h1>Innovation Protected. <span class="t-g">Business Empowered.</span></h1>
      <p class="lead">Leegtech is your trusted partner for intellectual property and business registration services. We help innovators, startups, researchers and enterprises protect their ideas through patents, designs, trademarks and copyrights, while also supporting company formation, tax registrations and business compliance.</p>
      <p>With a focus on expertise, reliability and timely execution, we make innovation protection and business growth simple and accessible.</p>
    </div>
    <div class="ph-media portrait">${img('founder', `${site.founder.name}, Founder of Leegtech`, { w: 501, h: 516, sm: false, eager: true, sizes: '(min-width: 900px) 40vw, 100vw' })}</div>
  </div>
</section>

<section class="section">
  <div class="container narrow">
    <blockquote class="quote quote-lg"><p>“At Leegtech, we believe that every great innovation deserves strong protection and every business deserves a solid foundation for growth. Our goal is to simplify complex legal, intellectual property and compliance processes so that innovators and entrepreneurs can focus on what they do best: creating and growing.</p><p>We are committed to delivering professional, transparent and high-quality services that help our clients transform ideas into valuable assets and opportunities into successful businesses.”</p><footer><strong>${esc(site.founder.name)}</strong>, Founder, Leegtech</footer></blockquote>
  </div>
</section>

<section class="section section-soft">
  <div class="container">
    ${secHead('What we stand for', 'Why clients choose Leegtech')}
    <div class="usp-grid usp-3">${usps.map(([ic, t, d]) => `<div class="usp"><span class="usp-ico">${icon(ic)}</span><div><h3>${esc(t)}</h3><p>${esc(d)}</p></div></div>`).join('')}</div>
  </div>
</section>

<section class="section">
  <div class="container">
    ${secHead('Practice areas', 'What we do')}
    <div class="cat-grid">${categories.map(c => `<a class="cat-card acc-${c.accent}" href="/${c.slug}/"><span class="cat-count">${c.services.length} services</span><h3>${esc(c.name)}</h3><p>${esc(c.tagline)}</p><span class="svc-more">Explore ${icon('arrow')}</span></a>`).join('')}</div>
  </div>
</section>

<section class="section section-tight">
  <div class="container">${secHead('Trusted by', 'Our Clients &amp; Partners')}</div>
  ${clientsStrip()}
</section>

<section class="section section-soft">
  <div class="container narrow">
    ${secHead('Company details', 'Leegtech at a glance')}
    <dl class="kv">
      <div><dt>Legal name</dt><dd>${esc(site.legalName)}</dd></div>
      <div><dt>Founder</dt><dd>${esc(site.founder.name)}</dd></div>
      <div><dt>Office</dt><dd>${esc(site.address.display)}</dd></div>
      <div><dt>Registered address</dt><dd>${esc(site.registeredAddress)}</dd></div>
      <div><dt>Phone / WhatsApp</dt><dd><a href="tel:${site.phone}">${site.phoneDisplay}</a></dd></div>
      <div><dt>Email</dt><dd><a href="mailto:${site.email}">${site.email}</a></dd></div>
      <div><dt>Service area</dt><dd>Pan-India, with international filings</dd></div>
    </dl>
  </div>
</section>
${ctaBand()}`;
  return { p, body };
}

/* ---------------- CONTACT ---------------- */
export function contact() {
  const path = '/contact/';
  const crumbs = [{ name: 'Home', path: '/' }, { name: 'Contact', path }];
  const p = { path, title: 'Contact Leegtech: Book a Free Consultation', description: `Book a free consultation with Leegtech for patents, trademarks, copyright, design, company registration and legal drafting. Call ${site.phoneDisplay} or WhatsApp us.`, section: 'contact' };
  p.schema = graph(base(), pageNode({ ...p, title: 'Contact Leegtech' }, 'ContactPage'), crumbsNode(crumbs));
  const maps = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(site.address.display)}`;
  const body = `
<section class="page-hero">
  <div class="container">
    ${breadcrumbs(crumbs)}
    <h1>Book Your Free Consultation</h1>
    <p class="lead">Tell us about your idea, brand or business. We respond within one working day with a clear recommendation, process overview and fee quote.</p>
  </div>
</section>
<section class="section section-top0">
  <div class="container consult-grid">
    <div class="form-card">${leadForm({ id: 'contact-form', title: 'Send us your requirement' })}</div>
    <div class="contact-side">
      <div class="contact-card">
        <h2>Talk to us directly</h2>
        <a class="cc-row" href="tel:${site.phone}">${icon('phone')}<span><strong>Call</strong>${site.phoneDisplay}</span></a>
        <a class="cc-row" href="${waLink(site)}" target="_blank" rel="noopener">${waIcon()}<span><strong>WhatsApp</strong>Chat with our team</span></a>
        <a class="cc-row" href="mailto:${site.email}">${icon('mail')}<span><strong>Email</strong>${site.email}</span></a>
        <a class="cc-row" href="${maps}" target="_blank" rel="noopener">${icon('pin')}<span><strong>Office</strong>${esc(site.address.display)}</span></a>
        ${site.hoursDisplay ? `<p class="cc-row">${icon('clock')}<span><strong>Hours</strong>${esc(site.hoursDisplay)}</span></p>` : ''}
      </div>
      <div class="contact-card">
        <h2>What happens next?</h2>
        ${stepsBlock([['We review your enquiry', 'A specialist reads your requirement.'], ['Free consultation call', 'We discuss your goals, options and timelines.'], ['Clear quote', 'You receive a transparent fee quote before any work starts.']])}
      </div>
    </div>
  </div>
</section>`;
  return { p, body };
}

/* ---------------- FAQ ---------------- */
export function faqPage() {
  const path = '/faq/';
  const crumbs = [{ name: 'Home', path: '/' }, { name: 'FAQ', path }];
  const all = [...generalFaqs, ...categories.flatMap(c => c.faqs)];
  const p = { path, title: 'FAQ: Patents, Trademarks & Business Registration', description: 'Clear answers to common questions on patents, trademarks, copyright, design registration, company registration, GST, compliance and agreements in India.', section: 'faq' };
  p.schema = graph(base(), pageNode({ ...p, title: 'Frequently Asked Questions' }), crumbsNode(crumbs), faqNode(all, path));
  const body = `
<section class="page-hero">
  <div class="container">
    ${breadcrumbs(crumbs)}
    <h1>Frequently Asked Questions</h1>
    <p class="lead">Clear answers about IP protection, business registration and legal agreements in India.</p>
  </div>
</section>
<section class="section section-top0">
  <div class="container narrow">
    <ul class="faq-toc"><li><a href="#general">General</a></li>${categories.map(c => `<li><a href="#${c.slug}-faqs">${esc(c.name)}</a></li>`).join('')}</ul>
    <h2 class="faq-group" id="general">General questions</h2>
    ${faqList(generalFaqs, 'h3', { ids: true })}
    ${categories.map(c => `<h2 class="faq-group" id="${c.slug}-faqs">${esc(c.name)} questions</h2>${faqList(c.faqs, 'h3', { ids: true })}<p class="faq-more"><a class="link-arrow" href="/${c.slug}/">See ${esc(c.name.toLowerCase())} services ${icon('arrow')}</a></p>`).join('')}
    <p class="disclaimer">These answers are general information, not legal advice, and do not guarantee any outcome. See our <a href="/disclaimer/">disclaimer</a>.</p>
  </div>
</section>
${ctaBand('Have more questions?', 'Talk to our IP &amp; legal experts. Protect your ideas. Protect your brand. Protect your business.')}`;
  return { p, body };
}

/* ---------------- BLOG ---------------- */
export const postCard = (post) => `<a class="post-card" href="/blog/${post.slug}/">
  <span class="post-meta">${fmtDate(post.date)} · ${post.readMins} min read</span>
  <h3>${esc(post.title)}</h3>
  <p>${esc(post.description)}</p>
  <span class="svc-more">Read article ${icon('arrow')}</span>
</a>`;

export function blogIndex(posts) {
  const path = '/blog/';
  const crumbs = [{ name: 'Home', path: '/' }, { name: 'Blog', path }];
  const p = { path, title: 'Blog: IP, Patent, Trademark & Business Law Guides', description: 'Practical guides on patents, trademarks, copyright, design registration, startups and business compliance in India from the Leegtech team.', section: 'blog' };
  p.schema = graph(base(), pageNode({ ...p, title: 'Leegtech Blog' }, 'CollectionPage'), crumbsNode(crumbs), {
    '@type': 'Blog', '@id': `${site.url}/blog/#blog`, name: 'Leegtech Blog', url: `${site.url}/blog/`, publisher: { '@id': `${site.url}/#organization` },
    blogPost: posts.map(x => ({ '@id': `${site.url}/blog/${x.slug}/#article` }))
  });
  const body = `
<section class="page-hero">
  <div class="container">
    ${breadcrumbs(crumbs)}
    <h1>Leegtech Blog</h1>
    <p class="lead">Practical, plain-language guides on protecting your ideas, brand and business in India.</p>
  </div>
</section>
<section class="section section-top0">
  <div class="container"><div class="post-grid">${posts.map(postCard).join('')}</div></div>
</section>
${ctaBand()}`;
  return { p, body };
}

export function blogPost(post, posts) {
  const path = `/blog/${post.slug}/`;
  const crumbs = [{ name: 'Home', path: '/' }, { name: 'Blog', path: '/blog/' }, { name: post.title, path }];
  const p = { path, title: post.seoTitle || post.title, description: post.description, section: 'blog', ogType: 'article', article: post, ogImage: post.category ? `/og/${post.category}.jpg` : undefined };
  p.schema = graph(base(), pageNode({ ...p, dateModified: post.updated }), crumbsNode(crumbs), { ...articleNode(post), image: site.url + (p.ogImage || '/og/default.jpg') });
  const c = categories.find(x => x.slug === post.category);
  const more = posts.filter(x => x.slug !== post.slug).slice(0, 3);
  const body = `
<article>
  <header class="page-hero post-hero">
    <div class="container narrow">
      ${breadcrumbs(crumbs)}
      <h1>${esc(post.title)}</h1>
      <p class="post-meta">By ${esc(site.name)} · Published ${fmtDate(post.date)}${post.updated !== post.date ? ` · Updated ${fmtDate(post.updated)}` : ''} · ${post.readMins} min read</p>
    </div>
  </header>
  <div class="container post-layout">
    <div class="prose post-body">${post.html}</div>
    <aside class="post-side"><div class="side-sticky">${leadForm({ id: 'post-form', title: 'Need help with this?', compact: true, selected: c ? '' : '' })}</div></aside>
  </div>
</article>
${c ? `<section class="section section-soft"><div class="container">${secHead('Related services', `${esc(c.name)} services`)}<div class="svc-grid">${c.services.slice(0, 3).map(s => serviceCard(s, c.label, c.slug, c.accent)).join('')}</div></div></section>` : ''}
${more.length ? `<section class="section"><div class="container">${secHead('Keep reading', 'More from the blog')}<div class="post-grid">${more.map(postCard).join('')}</div></div></section>` : ''}
${ctaBand()}`;
  return { p, body };
}

/* ---------------- POLICIES ---------------- */
export function policyPage(doc) {
  const crumbs = [{ name: 'Home', path: '/' }, { name: doc.title, path: doc.path }];
  const p = { path: doc.path, title: doc.title, description: doc.description, section: 'legal-docs' };
  p.schema = graph(base(), pageNode({ ...p }), crumbsNode(crumbs));
  const body = `
<section class="page-hero">
  <div class="container narrow">
    ${breadcrumbs(crumbs)}
    <h1>${esc(doc.title)}</h1>
    <p class="post-meta">${esc(doc.meta)}</p>
  </div>
</section>
<section class="section section-top0">
  <div class="container narrow prose legal">${doc.html}</div>
</section>`;
  return { p, body };
}

/* ---------------- THANK YOU / 404 ---------------- */
export function thankYou() {
  const p = { path: '/thank-you/', title: 'Thank You: We Have Received Your Enquiry', description: 'Thank you for contacting Leegtech. Our team will get back to you within one working day.', noindex: true, section: 'thanks', bodyClass: 'thanks' };
  p.schema = graph(base(), pageNode({ ...p }));
  const body = `
<section class="section thanks-hero">
  <div class="container narrow center">
    <span class="big-check">${icon('check')}</span>
    <h1>Thank you! Your enquiry has been received.</h1>
    <p class="lead">Our team will contact you within one working day. For a faster response, continue the conversation on WhatsApp.</p>
    <div class="btn-row center-row">
      <a class="btn btn-wa btn-lg" id="ty-wa" href="${waLink(site)}" target="_blank" rel="noopener">${waIcon()} Continue on WhatsApp</a>
      <a class="btn btn-ghost btn-lg" href="/">Back to home</a>
    </div>
    <p class="muted mt">You can also call us on <a href="tel:${site.phone}">${site.phoneDisplay}</a> or email <a href="mailto:${site.email}">${site.email}</a>.</p>
  </div>
</section>
<section class="section section-soft">
  <div class="container">${secHead('While you wait', 'Explore our services')}
    <div class="cat-grid">${categories.map(c => `<a class="cat-card acc-${c.accent}" href="/${c.slug}/"><h3>${esc(c.name)}</h3><p>${esc(c.tagline)}</p><span class="svc-more">Explore ${icon('arrow')}</span></a>`).join('')}</div>
  </div>
</section>`;
  return { p, body };
}

export function notFound() {
  const p = { path: '/404.html', title: 'Page Not Found', description: 'The page you are looking for could not be found.', noindex: true };
  p.schema = graph(base());
  const body = `
<section class="section thanks-hero">
  <div class="container narrow center">
    <p class="eyebrow">Error 404</p>
    <h1>We couldn't find that page.</h1>
    <p class="lead">It may have moved. Try one of these instead:</p>
    <div class="btn-row center-row"><a class="btn btn-primary" href="/">Home</a><a class="btn btn-ghost" href="/services/">All services</a><a class="btn btn-ghost" href="/contact/">Contact us</a></div>
  </div>
</section>`;
  return { p, body };
}

/* ---------------- DISCLAIMER ---------------- */
export function disclaimer() {
  const path = '/disclaimer/';
  const crumbs = [{ name: 'Home', path: '/' }, { name: 'Disclaimer', path }];
  const p = { path, title: 'Disclaimer', description: 'Leegtech website disclaimer: general information only, no guarantee of outcomes, no solicitation, and no affiliation with any government office.', section: 'legal-docs' };
  p.schema = graph(base(), pageNode({ ...p }), crumbsNode(crumbs));
  const sec = [
    ['General information only', 'The content on this website, including service pages, FAQs and blog articles, is provided for general information. It is not legal, tax or professional advice and should not be relied on as a substitute for advice on your specific facts. Laws, rules, official fees and procedures change; always confirm the current position before acting.'],
    ['No solicitation or advertisement', 'This website is not intended to solicit work or to advertise legal services. Nothing on it is an invitation to create a lawyer–client relationship. In keeping with the rules of the Bar Council of India, visitors access this website and contact Leegtech on their own initiative to obtain information about our services. Where a step in a matter must by law be carried out by an advocate or by a registered patent or trademark agent, it is carried out only by a person who is so qualified.'],
    ['No lawyer–client relationship', 'Using this website, sending an email or WhatsApp message, or submitting an enquiry form does not create a lawyer–client or other professional relationship. An engagement begins only when the scope of work and fees are agreed in writing.'],
    ['No guarantee of outcome', 'Leegtech does not guarantee that any patent, trademark, copyright, design, registration, licence, certificate or approval will be granted, registered or issued, or that it will be obtained within any particular time. Those decisions are made by the relevant government authorities and depend on the facts of each matter, objections, oppositions and applicable law. Timelines mentioned on this website are indicative only.'],
    ['No government affiliation', 'Leegtech is a private company. We are not affiliated with, endorsed by or acting on behalf of any government department or office, including the Office of the Controller General of Patents, Designs and Trade Marks (IP India), the Copyright Office, the Ministry of Corporate Affairs, DPIIT, the Ministry of MSME, the GST authorities, the Income Tax Department or FSSAI. Many government registrations can be filed directly by applicants on official portals. Our fees are professional fees for our assistance and are separate from official government fees, which are charged at actuals.'],
    ['Third-party names and logos', 'Names, trademarks and logos of clients, partners, institutions and government bodies shown or mentioned on this website belong to their respective owners. Their appearance does not imply endorsement.'],
    ['External links', 'Links to external websites are provided for convenience. Leegtech is not responsible for the content, availability or privacy practices of those websites.'],
    ['Limitation of liability', 'To the extent permitted by law, Leegtech is not liable for any loss arising from reliance on information on this website. Our obligations for paid services are set out in our Terms & Conditions and the written engagement for each matter.']
  ];
  const body = `
<section class="page-hero">
  <div class="container narrow">
    ${breadcrumbs(crumbs)}
    <h1>Disclaimer</h1>
    <p class="post-meta">Last updated: ${fmtDate(site.lastReviewed)}</p>
  </div>
</section>
<section class="section section-top0">
  <div class="container narrow prose legal">
    ${sec.map(([h, t]) => `<h2 id="${h.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/-$/, '')}">${esc(h)}</h2><p>${esc(t)}</p>`).join('')}
    <p>See also our <a href="/terms-and-conditions/">Terms &amp; Conditions</a>, <a href="/privacy-policy/">Privacy Policy</a> and <a href="/refund-policy/">Refund Policy</a>. Questions: <a href="mailto:${site.email}">${site.email}</a>.</p>
  </div>
</section>`;
  return { p, body };
}
