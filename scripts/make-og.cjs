// Generates 1200x630 Open Graph images into src/static/og/ using the pre-installed Playwright Chromium.
// Usage: node scripts/make-og.cjs
const { chromium } = require('playwright');
const fs = require('fs'); const path = require('path');
const root = path.join(__dirname, '..');
const font = fs.readFileSync(path.join(root, 'src/assets/fonts/nunito-latin.woff2')).toString('base64');
const mark = fs.readFileSync(path.join(root, 'src/assets/img/logo-mark.png')).toString('base64');
const items = [
  ['default', 'Protect Your Ideas. Brand. Business.', 'Patent · Trademark · Copyright · Design · Business & Legal Services', ''],
  ['patent', 'Patent Services', 'Search · Filing · FER · Hearings · Oppositions · PCT, US & UK', 'spot-patent'],
  ['design', 'Design Registration', 'India · United Kingdom · German Utility Models', 'why'],
  ['trademark-copyright', 'Trademark & Copyright', 'Search · Registration · Objections · Madrid · Copyright', 'spot-tm'],
  ['business-tax', 'Business, Tax & Compliance', 'Pvt Ltd · LLP · Startup India · MSME · GST · FSSAI · ISO', 'spot-startup'],
  ['legal', 'Legal Agreements', 'NDA · Employment · Licensing · Franchise · M&A · Legal Notices', 'spot-legal']
];
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 1 });
  for (const [slug, title, sub, img] of items) {
    const pic = img ? 'data:image/webp;base64,' + fs.readFileSync(path.join(root, `src/assets/img/${img}.webp`)).toString('base64') : '';
    await p.setContent(`<!doctype html><html><head><style>
      @font-face{font-family:N;src:url(data:font/woff2;base64,${font}) format('woff2');font-weight:200 1000}
      *{margin:0;box-sizing:border-box}body{width:1200px;height:630px;font-family:N;background:#fff;position:relative;overflow:hidden}
      .bg{position:absolute;inset:0;background:radial-gradient(520px 380px at 0% 0%,rgba(232,106,30,.18),transparent 70%),radial-gradient(560px 420px at 100% 100%,rgba(76,175,122,.2),transparent 70%)}
      .wrap{position:absolute;inset:0;padding:64px 72px;display:flex;flex-direction:column;justify-content:space-between;${pic ? 'width:720px;' : ''}}
      .brand{display:flex;align-items:center;gap:16px;font-weight:900;font-size:44px;letter-spacing:-1px}
      .brand img{height:70px}.o{color:#E86A1E}.g{color:#4CAF7A}
      h1{font-size:${pic ? 64 : 76}px;line-height:1.04;font-weight:900;color:#121212;letter-spacing:-2px}
      p{font-size:26px;color:#3a3a3a;font-weight:700;margin-top:18px;line-height:1.35}
      .url{font-size:24px;font-weight:900;color:#121212;display:flex;gap:18px;align-items:center}.url span{background:#E86A1E;color:#fff;padding:10px 20px;border-radius:999px;font-size:22px}
      .pic{position:absolute;right:0;top:0;bottom:0;width:430px;background:#faf7f3 url(${pic}) center/cover no-repeat;border-left:8px solid #E86A1E}
    </style></head><body><div class="bg"></div>${pic ? '<div class="pic"></div>' : ''}
      <div class="wrap"><div class="brand"><img src="data:image/png;base64,${mark}"><span><span class="o">Leeg</span><span class="g">Tech</span></span></div>
      <div><h1>${title}</h1><p>${sub}</p></div>
      <div class="url">leegtech.in <span>Free Consultation</span></div></div></body></html>`);
    await p.waitForTimeout(150);
    await p.screenshot({ path: path.join(root, `src/static/og/${slug}.jpg`), type: 'jpeg', quality: 84 });
  }
  await b.close();
  console.log('OG images written');
})();
