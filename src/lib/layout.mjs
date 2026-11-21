import { site } from '../data/site.mjs';
import { categories } from '../data/categories.mjs';
import { esc, icon, waIcon, waLink } from './util.mjs';

const T = site.tracking;
const SOCIAL_ICON = {
  LinkedIn: '<svg class="ico" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z"/></svg>',
  Instagram: '<svg class="ico" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5.5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.6" cy="6.4" r="1.1" fill="currentColor" stroke="none"/></svg>'
};
const trackingOn = !!(T.gtmId || T.ga4Id || T.googleAdsId || T.metaPixelId);

function trackingHead() {
  if (!trackingOn) return '';
  const gtagBoot = `window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
var c=null;try{c=localStorage.getItem('lt_consent');}catch(e){}
gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',functionality_storage:'granted',security_storage:'granted',wait_for_update:500});
if(c==='granted'){gtag('consent','update',{ad_storage:'granted',ad_user_data:'granted',ad_personalization:'granted',analytics_storage:'granted'});}
gtag('set','ads_data_redaction',true);gtag('set','url_passthrough',true);`;
  let out = `<script>${gtagBoot}</script>\n`;
  if (T.gtmId) {
    out += `<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','${T.gtmId}');</script>\n`;
  } else if (T.ga4Id || T.googleAdsId) {
    const first = T.ga4Id || T.googleAdsId;
    out += `<script async src="https://www.googletagmanager.com/gtag/js?id=${first}"></script>\n<script>gtag('js',new Date());${T.ga4Id ? `gtag('config','${T.ga4Id}');` : ''}${T.googleAdsId ? `gtag('config','${T.googleAdsId}',{allow_enhanced_conversions:true});` : ''}</script>\n`;
  }
  if (T.metaPixelId) {
    out += `<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
if(c!=='granted'){fbq('consent','revoke');}fbq('init','${T.metaPixelId}');fbq('track','PageView');</script>\n`;
  }
  return out;
}

function head(p, assets) {
  const url = site.url + p.path;
  const title = p.fullTitle || (p.title ? `${p.title} | ${site.name}` : `${site.name} | ${site.tagline}`);
  const ogImg = site.url + (p.ogImage || '/og/default.jpg');
  const robots = (p.noindex || process.env.SITE_PREVIEW) ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
  const v = [
    T.googleSiteVerification && `<meta name="google-site-verification" content="${esc(T.googleSiteVerification)}">`,
    T.bingSiteVerification && `<meta name="msvalidate.01" content="${esc(T.bingSiteVerification)}">`,
    T.facebookDomainVerification && `<meta name="facebook-domain-verification" content="${esc(T.facebookDomainVerification)}">`
  ].filter(Boolean).join('\n');
  return `<!doctype html>
<html lang="en-IN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>${esc(title)}</title>
<meta name="description" content="${esc(p.description)}">
<meta name="robots" content="${robots}">
<link rel="canonical" href="${url}">
<link rel="alternate" hreflang="en-IN" href="${url}">
<link rel="alternate" hreflang="x-default" href="${url}">
<meta name="theme-color" content="#E86A1E">
<meta name="author" content="${esc(site.name)}">
<meta property="og:type" content="${p.ogType || 'website'}">
<meta property="og:site_name" content="${esc(site.name)}">
<meta property="og:locale" content="en_IN">
<meta property="og:title" content="${esc(p.ogTitle || title)}">
<meta property="og:description" content="${esc(p.description)}">
<meta property="og:url" content="${url}">
<meta property="og:image" content="${ogImg}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="${esc(p.ogTitle || title)}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${esc(p.ogTitle || title)}">
<meta name="twitter:description" content="${esc(p.description)}">
<meta name="twitter:image" content="${ogImg}">
${p.article ? `<meta property="article:published_time" content="${p.article.date}T00:00:00+05:30">\n<meta property="article:modified_time" content="${p.article.updated}T00:00:00+05:30">` : ''}
${v}
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" href="/favicon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="alternate" type="application/rss+xml" title="Leegtech Blog" href="/blog/rss.xml">
<link rel="preload" href="/assets/fonts/nunito-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="${assets.css}">
${p.preloadImage ? `<link rel="preload" as="image" href="${p.preloadImage}" fetchpriority="high">` : ''}
${trackingHead()}<script type="application/ld+json">${JSON.stringify(p.schema).replace(/</g, '\\u003c')}</script>
</head>`;
}

const brand = `<a class="brand" href="/" aria-label="Leegtech home"><img src="/assets/img/logo-mark.webp" alt="" width="28" height="40"><span class="brand-word" aria-hidden="true"><span class="b-o">Leeg</span><span class="b-g">Tech</span></span></a>`;

function header(p) {
  const active = p.section || '';
  const items = categories.map(c => `
      <li class="nav-item has-menu${active === c.slug ? ' is-active' : ''}">
        <a class="nav-link" href="/${c.slug}/"${active === c.slug ? ' aria-current="page"' : ''}>${esc(c.navName)}</a>
        <button class="menu-toggle" aria-expanded="false" aria-label="Show ${esc(c.navName)} services">${icon('chevron')}</button>
        <div class="mega">
          <div class="mega-head"><a href="/${c.slug}/"><strong>${esc(c.name)} services</strong><span>${esc(c.tagline)}</span></a></div>
          <ul class="mega-list">${c.services.map(s => `<li><a href="/${c.slug}/${s.slug}/">${esc(s.name)}</a></li>`).join('')}</ul>
        </div>
      </li>`).join('');
  return `<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
  <div class="container hdr">
    ${brand}
    <nav class="main-nav" id="main-nav" aria-label="Main">
      <ul class="nav-list">${items}
        <li class="nav-item${active === 'blog' ? ' is-active' : ''}"><a class="nav-link" href="/blog/">Blog</a></li>
        <li class="nav-item${active === 'about' ? ' is-active' : ''}"><a class="nav-link" href="/about/">About Us</a></li>
        <li class="nav-item nav-only-mobile${active === 'contact' ? ' is-active' : ''}"><a class="nav-link" href="/contact/">Contact</a></li>
      </ul>
      <div class="nav-mobile-cta">
        <a class="btn btn-primary btn-block" href="/contact/" data-cta="nav-mobile">Book Free Consultation</a>
        <a class="btn btn-wa btn-block" href="${waLink(site)}" target="_blank" rel="noopener">${waIcon()} WhatsApp Us</a>
      </div>
    </nav>
    <div class="hdr-actions">
      <a class="hdr-phone" href="tel:${site.phone}">${icon('phone')}<span>${site.phoneDisplay}</span></a>
      <a class="btn btn-primary btn-sm hdr-cta" href="/contact/" data-cta="header">Free Consultation</a>
      <button class="nav-burger" aria-controls="main-nav" aria-expanded="false" aria-label="Open menu">${icon('menu', 'i-open')}${icon('close', 'i-close')}</button>
    </div>
  </div>
</header>`;
}

function footer() {
  const s = site.social;
  const socials = Object.entries({ LinkedIn: s.linkedin, Instagram: s.instagram, Facebook: s.facebook, YouTube: s.youtube, X: s.x }).filter(([, v]) => v);
  const year = new Date().getFullYear();
  return `<footer class="site-footer">
  <div class="container">
    <div class="ft-top">
      <div class="ft-brand">
        ${brand}
        <p>${esc(site.description)}</p>
        <ul class="ft-contact">
          <li><a href="tel:${site.phone}">${icon('phone')}${site.phoneDisplay}</a></li>
          <li><a href="mailto:${site.email}">${icon('mail')}${site.email}</a></li>
          <li><span>${icon('pin')}${esc(site.address.display)}</span></li>
          ${site.hoursDisplay ? `<li><span>${icon('clock')}${esc(site.hoursDisplay)}</span></li>` : ''}
        </ul>
        ${socials.length ? `<ul class="ft-social">${socials.map(([n, u]) => `<li><a href="${esc(u)}" rel="noopener me" target="_blank" aria-label="Leegtech on ${n}">${SOCIAL_ICON[n] || ''}<span>${n}</span></a></li>`).join('')}</ul>` : ''}
      </div>
      ${categories.map(c => `<div class="ft-col">
        <h2 class="ft-h"><a href="/${c.slug}/">${esc(c.name)}</a></h2>
        <ul>${c.services.slice(0, 6).map(sv => `<li><a href="/${c.slug}/${sv.slug}/">${esc(sv.name.replace(/\s*\(.*\)$/, ''))}</a></li>`).join('')}
        <li><a class="ft-more" href="/${c.slug}/">All ${esc(c.name)} services →</a></li></ul>
      </div>`).join('')}
    </div>
    <div class="ft-mid">
      <ul class="ft-links">
        <li><a href="/about/">About Us</a></li>
        <li><a href="/gwalior/">Gwalior Office</a></li>
        <li><a href="/services/">All Services</a></li>
        <li><a href="/blog/">Blog</a></li>
        <li><a href="/tools/">Free Tools</a></li>
        <li><a href="/faq/">FAQ</a></li>
        <li><a href="/contact/">Contact</a></li>
        <li><a href="/privacy-policy/">Privacy Policy</a></li>
        <li><a href="/terms-and-conditions/">Terms &amp; Conditions</a></li>
        <li><a href="/refund-policy/">Refund Policy</a></li>
        <li><a href="/disclaimer/">Disclaimer</a></li>
      </ul>
    </div>
    <div class="ft-bottom">
      <p>© ${year} ${esc(site.legalName)}. All rights reserved.</p>
      <p class="ft-disclaimer">Leegtech is a private company and is not affiliated with, or endorsed by, any government office, including the Indian Patent Office, the Trade Marks Registry, the Copyright Office or the Ministry of Corporate Affairs. Information on this website is general information, not legal advice, and is not a solicitation or advertisement of legal services. We do not guarantee the grant, registration or approval of any application; decisions rest with the relevant authorities. Official government fees are charged at actuals. <a href="/disclaimer/">Read the full disclaimer</a>.</p>
    </div>
  </div>
</footer>
<div class="mobile-bar" role="navigation" aria-label="Quick contact">
  <a href="tel:${site.phone}">${icon('phone')}<span>Call</span></a>
  <a href="${waLink(site)}" target="_blank" rel="noopener" class="mb-wa">${waIcon()}<span>WhatsApp</span></a>
  <a href="/contact/" class="mb-cta" data-cta="mobile-bar">${icon('arrow')}<span>Free Consult</span></a>
</div>
<a class="float-wa" href="${waLink(site)}" target="_blank" rel="noopener" aria-label="Chat with Leegtech on WhatsApp">${waIcon()}</a>
${trackingOn ? `<div class="consent" id="consent" hidden>
  <p>We use cookies to understand site usage and measure our ads. You can accept or decline non-essential cookies. <a href="/privacy-policy/#cookies">Privacy Policy</a></p>
  <div class="consent-btns"><button class="btn btn-ghost btn-sm" data-consent="denied">Decline</button><button class="btn btn-primary btn-sm" data-consent="granted">Accept</button></div>
</div>` : ''}`;
}

export function layout(p, body, assets) {
  const noscriptGtm = T.gtmId ? `<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=${T.gtmId}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>` : '';
  return `${head(p, assets)}
<body class="${p.bodyClass || ''}">
${noscriptGtm}
${header(p)}
<main id="main">
${body}
</main>
${footer()}
<script>window.LT=${JSON.stringify({ ads: T.googleAdsId && T.googleAdsLeadLabel ? `${T.googleAdsId}/${T.googleAdsLeadLabel}` : '', meta: !!T.metaPixelId, tracking: trackingOn, wa: site.whatsapp })};</script>
<script src="${assets.js}" defer></script>
</body>
</html>`;
}
