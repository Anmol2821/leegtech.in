const { chromium, devices } = require('playwright'); const fs = require('fs'); const path = require('path');
const D = require('path').join(__dirname, '..', '..', 'dist'); const pages = [];
(function walk(d) { for (const f of fs.readdirSync(d, { withFileTypes: true })) { const p = path.join(d, f.name); if (f.isDirectory()) walk(p); else if (f.name === 'index.html') pages.push('/' + path.relative(D, path.dirname(p)) + (path.dirname(p) === D ? '' : '/')); } })(D);
const profiles = { 'iPhone SE': devices['iPhone SE'], 'iPhone 15 Pro Max': devices['iPhone 15 Pro Max'], 'Pixel 7': devices['Pixel 7'], 'Galaxy S9+': devices['Galaxy S9+'], 'iPad Mini': devices['iPad Mini'], 'iPad Pro 11 landscape': devices['iPad Pro 11 landscape'], 'Laptop 1280': { viewport: { width: 1280, height: 800 } }, 'Desktop 1920': { viewport: { width: 1920, height: 1080 } } };
(async () => {
  const b = await chromium.launch(); const issues = [];
  for (const [name, dev] of Object.entries(profiles)) {
    const ctx = await b.newContext({ ...dev }); const p = await ctx.newPage();
    p.on('pageerror', e => issues.push(`${name} JS ${p.url()} ${e.message}`));
    p.on('console', m => { if (m.type() === 'error' && !/ERR_TUNNEL_CONNECTION_FAILED|ERR_NAME_NOT_RESOLVED|ERR_CONNECTION/.test(m.text())) issues.push(`${name} console ${p.url()} ${m.text()}`); }); // third-party tags may be blocked in the sandbox
    p.on('response', r => { if (r.status() >= 400 && r.url().startsWith('http://localhost')) issues.push(`${name} ${r.status()} ${r.url()}`); });
    for (const pg of pages) {
      await p.goto('http://localhost:8080' + pg, { waitUntil: 'load' });
      const r = await p.evaluate(() => {
        const out = []; const W = document.documentElement.clientWidth;
        if (document.documentElement.scrollWidth > W + 1) out.push('h-scroll ' + (document.documentElement.scrollWidth - W));
        document.querySelectorAll('main *').forEach(e => { const b = e.getBoundingClientRect(); if (b.width && b.right > W + 2 && !e.closest('.marquee,.logos')) out.push('overflow ' + e.tagName + '.' + (e.className.baseVal ?? e.className)); });
        const small = [...document.querySelectorAll('main p, main li, main a, main label')].filter(e => e.offsetParent && parseFloat(getComputedStyle(e).fontSize) < 12).length; if (small) out.push('tiny-text x' + small);
        return [...new Set(out)].slice(0, 4);
      });
      if (r.length) issues.push(`${name} ${pg} ${r.join(' | ')}`);
    }
    await ctx.close();
  }
  console.log(`${pages.length} pages x ${Object.keys(profiles).length} devices`); console.log(issues.join('\n') || 'NO ISSUES'); await b.close();
})();
