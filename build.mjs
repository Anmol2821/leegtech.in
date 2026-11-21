// Leegtech static site build. Usage: node build.mjs
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { site } from './src/data/site.mjs';
import { categories, allServices } from './src/data/categories.mjs';
import { generalFaqs } from './src/data/faqs.mjs';
import { layout } from './src/lib/layout.mjs';
import { esc, stripTags } from './src/lib/util.mjs';
import * as P from './src/lib/pages.mjs';
import * as TOOLS from './src/lib/tools.mjs';

const ROOT = path.dirname(new URL(import.meta.url).pathname);
const SRC = path.join(ROOT, 'src');
const OUT = path.join(ROOT, 'dist');

fs.rmSync(OUT, { recursive: true, force: true });
fs.mkdirSync(OUT, { recursive: true });

const copyDir = (from, to) => {
  fs.mkdirSync(to, { recursive: true });
  for (const f of fs.readdirSync(from, { withFileTypes: true })) {
    const a = path.join(from, f.name), b = path.join(to, f.name);
    f.isDirectory() ? copyDir(a, b) : fs.copyFileSync(a, b);
  }
};
copyDir(path.join(SRC, 'static'), OUT);
copyDir(path.join(SRC, 'assets'), path.join(OUT, 'assets'));

// Fingerprint CSS and JS for long-term caching.
const fingerprint = (rel) => {
  const file = path.join(OUT, rel);
  let content = fs.readFileSync(file, 'utf8');
  if (rel.endsWith('.css')) content = content.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\s*\n\s*/g, '\n').replace(/\n+/g, '\n');
  const hash = crypto.createHash('md5').update(content).digest('hex').slice(0, 10);
  const out = rel.replace(/(\.\w+)$/, `.${hash}$1`);
  fs.writeFileSync(path.join(OUT, out), content);
  fs.rmSync(file);
  return '/' + out;
};
const assets = { css: fingerprint('assets/css/site.css'), js: fingerprint('assets/js/site.js') };

// ---------- Blog ----------
const blogDir = path.join(SRC, 'content/blog');
const posts = fs.readdirSync(blogDir).filter(f => f.endsWith('.html')).map(f => {
  const raw = fs.readFileSync(path.join(blogDir, f), 'utf8');
  const m = raw.match(/^---\n([\s\S]*?)\n---\n([\s\S]*)$/);
  const meta = Object.fromEntries(m[1].split('\n').map(l => { const i = l.indexOf(':'); return [l.slice(0, i).trim(), l.slice(i + 1).trim()]; }));
  let html = m[2].trim();
  // Wrap tables & add ids to h2 for deep links
  html = html.replace(/<h2>([^<]+)<\/h2>/g, (x, t) => `<h2 id="${t.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')}">${t}</h2>`);
  // Responsive tables: copy header labels onto each cell for stacked mobile layout
  html = html.replace(/<table>([\s\S]*?)<\/table>/g, (t) => {
    const heads = [...t.matchAll(/<th>([\s\S]*?)<\/th>/g)].map(m => stripTags(m[1]));
    return t.replace(/<tr>([\s\S]*?)<\/tr>/g, (row, inner) => { let i = 0; return '<tr>' + inner.replace(/<td>/g, () => `<td data-label="${heads[i++] || ''}">`) + '</tr>'; });
  });
  const words = stripTags(html).split(' ').length;
  return { ...meta, html, words, readMins: Math.max(1, Math.round(words / 220)) };
}).sort((a, b) => b.date.localeCompare(a.date) || a.title.localeCompare(b.title));

// ---------- Policies ----------
function policyToHtml(text) {
  const seen = {};
  const lines = text.split('\n');
  let html = '', para = [], list = null, listType = '';
  const flushPara = () => { if (para.length) { html += `<p>${esc(para.join(' '))}</p>\n`; para = []; } };
  const flushList = () => { if (list) { html += `<${listType}>${list.map(li => `<li>${esc(li)}</li>`).join('')}</${listType}>\n`; list = null; } };
  for (const raw of lines) {
    const l = raw.trim();
    if (!l) { flushPara(); flushList(); continue; }
    let m;
    if ((m = l.match(/^(\d+)\.\s+([A-Z][A-Z0-9 ,&’'()\/-]+)$/))) {
      flushPara(); flushList();
      let id = m[2].toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
      if (seen[id]) id += '-' + (++seen[id]); else seen[id] = 1;
      html += `<h2 id="${id}">${m[1]}. ${esc(m[2].charAt(0) + m[2].slice(1).toLowerCase())}</h2>\n`;
    } else if ((m = l.match(/^(\d+\.\d+)\s+(.+)$/))) {
      flushPara(); flushList(); html += `<h3>${m[1]} ${esc(m[2])}</h3>\n`;
    } else if ((m = l.match(/^●\s*(.+)$/))) {
      flushPara(); if (!list || listType !== 'ul') { flushList(); list = []; listType = 'ul'; } list.push(m[1]);
    } else if ((m = l.match(/^\d+\.\s+([a-z].+)$/))) {
      flushPara(); if (!list || listType !== 'ol') { flushList(); list = []; listType = 'ol'; } list.push(m[1]);
    } else if (list && !/[.:;]$/.test(list[list.length - 1]) ) {
      list[list.length - 1] += ' ' + l;
    } else {
      flushList(); para.push(l);
      if (/[.:]$/.test(l) && l.length < 75) flushPara();
    }
  }
  flushPara(); flushList();
  return html;
}
const policyRaw = fs.readFileSync(path.join(SRC, 'content/policies.txt'), 'utf8');
const sections = [
  { key: 'PRIVACY POLICY', end: 'End of Privacy Policy', path: '/privacy-policy/', title: 'Privacy Policy', description: 'How Leegtech collects, uses, stores and protects your personal information when you use our website and services.' },
  { key: 'TERMS & CONDITIONS', end: 'End of Terms & Conditions', path: '/terms-and-conditions/', title: 'Terms & Conditions', description: 'The terms that govern your use of the Leegtech website and the purchase of our IP, legal and business services.' },
  { key: 'REFUND, CANCELLATION & SERVICE DELIVERY POLICY', end: 'End of Refund, Cancellation & Service Delivery Policy', path: '/refund-policy/', title: 'Refund, Cancellation & Service Delivery Policy', description: 'Leegtech\'s policy on cancellations, refunds, statutory fees, third-party expenses and service delivery.' }
];
const policyDocs = sections.map(s => {
  const start = policyRaw.indexOf(s.key + '\n');
  const end = policyRaw.indexOf(s.end, start);
  let body = policyRaw.slice(start + s.key.length + 1, end).trim();
  const metaLines = body.split('\n').slice(0, 2);
  body = body.split('\n').slice(2).join('\n');
  let html = policyToHtml(body);
  if (s.path === '/privacy-policy/') html = html.replace('<h2 id="cookies">', '<h2 id="cookies">');
  return { ...s, meta: metaLines.join(' · '), html };
});

// ---------- Render ----------
const pages = [];
const write = (urlPath, html) => {
  const file = urlPath.endsWith('.html') ? path.join(OUT, urlPath) : path.join(OUT, urlPath, 'index.html');
  fs.mkdirSync(path.dirname(file), { recursive: true });
  const min = html.split('\n').map(l => l.trim()).filter(Boolean).join('\n');
  fs.writeFileSync(file, min);
};
const render = ({ p, body }, opts = {}) => {
  write(p.path, layout(p, body, assets));
  if (!p.noindex && opts.sitemap !== false) pages.push({ path: p.path, priority: opts.priority ?? 0.6, lastmod: opts.lastmod || site.lastReviewed, title: p.title || p.fullTitle, description: p.description });
};

render(P.home(posts), { priority: 1.0 });
render(P.servicesHub(), { priority: 0.8 });
for (const c of categories) render(P.category(c, posts), { priority: 0.9 });
for (const s of allServices) render(P.service(s, posts), { priority: 0.8 });
render(P.about(), { priority: 0.6 });
render(P.gwalior(posts), { priority: 0.7 });
render(TOOLS.toolsHub(), { priority: 0.7 });
render(TOOLS.classFinder(), { priority: 0.8 });
render(TOOLS.patentCalc(), { priority: 0.8 });
render(P.contact(), { priority: 0.7 });
render(P.faqPage(), { priority: 0.6 });
render(P.blogIndex(posts), { priority: 0.6 });
for (const post of posts) render(P.blogPost(post, posts), { priority: 0.7, lastmod: post.updated });
for (const d of policyDocs) render(P.policyPage(d), { priority: 0.2 });
render(P.disclaimer(), { priority: 0.2 });
render(P.thankYou());
render(P.notFound());

// ---------- SEO / AI files ----------
fs.writeFileSync(path.join(OUT, 'sitemap.xml'), `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${pages.map(x => `  <url><loc>${site.url}${x.path}</loc><lastmod>${x.lastmod}</lastmod><priority>${x.priority.toFixed(1)}</priority></url>`).join('\n')}
</urlset>
`);

if (process.env.SITE_PREVIEW) fs.writeFileSync(path.join(OUT, '_headers'), '/*\n  X-Robots-Tag: noindex, nofollow\n');
fs.writeFileSync(path.join(OUT, 'robots.txt'), process.env.SITE_PREVIEW ? 'User-agent: *\nDisallow: /\n' : `# Leegtech robots.txt
User-agent: *
Allow: /
Disallow: /thank-you/

# AI assistants and search engines are welcome to read and cite this site.
User-agent: GPTBot
Allow: /
User-agent: OAI-SearchBot
Allow: /
User-agent: ChatGPT-User
Allow: /
User-agent: ClaudeBot
Allow: /
User-agent: Claude-SearchBot
Allow: /
User-agent: Claude-User
Allow: /
User-agent: PerplexityBot
Allow: /
User-agent: Google-Extended
Allow: /
User-agent: Applebot-Extended
Allow: /

Sitemap: ${site.url}/sitemap.xml
`);

const llms = `# ${site.name}

> ${site.description}

${site.name} (${site.legalName}) is based in Gwalior, Madhya Pradesh, India and serves clients across India, with international patent, design, trademark and copyright filings. Founder: ${site.founder.name}.
Contact: ${site.phoneDisplay} (phone/WhatsApp), ${site.email}. Office: ${site.address.display}. A free initial consultation is offered.

## Practice areas
${categories.map(c => `- [${c.name}](${site.url}/${c.slug}/): ${c.tagline} ${c.services.length} services.`).join('\n')}

## Services
${categories.map(c => `### ${c.name}\n${c.services.map(s => `- [${s.name}](${site.url}/${c.slug}/${s.slug}/): ${s.short}`).join('\n')}`).join('\n\n')}

## Free tools
- [Trademark Class Finder](${site.url}/tools/trademark-class-finder/): find which of the 45 Nice classes a product or service falls in.
- [Patent Deadline Calculator](${site.url}/tools/patent-deadline-calculator/): Indian patent and PCT deadlines from a priority date.

## Guides
${posts.map(x => `- [${x.title}](${site.url}/blog/${x.slug}/): ${x.description}`).join('\n')}

## Company
- [About](${site.url}/about/)
- [Gwalior office](${site.url}/gwalior/)
- [Contact / free consultation](${site.url}/contact/)
- [FAQ](${site.url}/faq/)
- [Privacy Policy](${site.url}/privacy-policy/)
- [Terms & Conditions](${site.url}/terms-and-conditions/)
- [Refund Policy](${site.url}/refund-policy/)
- [Disclaimer](${site.url}/disclaimer/): general information only; no guarantee of outcomes; not affiliated with any government office.

## Optional
- [Full text of all service pages](${site.url}/llms-full.txt)
`;
fs.writeFileSync(path.join(OUT, 'llms.txt'), llms);

const full = [`# ${site.name}: full service reference\n\n${site.description}\nContact: ${site.phoneDisplay}, ${site.email}, ${site.address.display}.\nInformation is general guidance, not legal advice. Last reviewed ${site.lastReviewed}.\n`,
  ...categories.map(c => `\n## ${c.h1}\nURL: ${site.url}/${c.slug}/\n${c.intro}\n\n${c.services.map(s => `### ${s.name}\nURL: ${site.url}/${c.slug}/${s.slug}/\n${s.lead}\n\n${s.overview.join('\n\n')}\n\nWho needs it: ${s.whoFor.join('; ')}.\nWhat's included: ${s.included.join('; ')}.\nProcess: ${s.process.map(([t, d], i) => `${i + 1}) ${t}: ${d}`).join(' ')}\nDocuments: ${s.documents.join('; ')}.\nKey facts:\n${s.facts.map(([k, v]) => `- ${k}: ${v}`).join('\n')}\nFAQs:\n${s.faqs.map(([q, a]) => `Q: ${q}\nA: ${a}`).join('\n')}\n`).join('\n')}`),
  `\n## General FAQs\n${generalFaqs.map(([q, a]) => `Q: ${q}\nA: ${a}`).join('\n')}\n`
].join('');
fs.writeFileSync(path.join(OUT, 'llms-full.txt'), full);

fs.writeFileSync(path.join(OUT, 'site.webmanifest'), JSON.stringify({
  name: `${site.name}: IP, Legal & Business Services`, short_name: site.name, start_url: '/', display: 'standalone',
  background_color: '#ffffff', theme_color: '#E86A1E',
  icons: [{ src: '/icon-192.png', sizes: '192x192', type: 'image/png' }, { src: '/icon-512.png', sizes: '512x512', type: 'image/png' }]
}, null, 2));

fs.mkdirSync(path.join(OUT, 'blog'), { recursive: true });
fs.writeFileSync(path.join(OUT, 'blog/rss.xml'), `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>
<title>Leegtech Blog</title><link>${site.url}/blog/</link><description>Guides on IP, business and legal matters in India.</description><language>en-in</language>
<atom:link href="${site.url}/blog/rss.xml" rel="self" type="application/rss+xml"/>
${posts.map(x => `<item><title>${esc(x.title)}</title><link>${site.url}/blog/${x.slug}/</link><guid>${site.url}/blog/${x.slug}/</guid><pubDate>${new Date(x.date + 'T00:00:00+05:30').toUTCString()}</pubDate><description>${esc(x.description)}</description></item>`).join('\n')}
</channel></rss>
`);

// IndexNow key file is optional; see README.
console.log(`Built ${pages.length} indexable pages (+ thank-you, 404) into dist/. Services: ${allServices.length}. Posts: ${posts.length}.`);
