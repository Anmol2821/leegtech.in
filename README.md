# leegtech.in

Multi-page static website for Leegtech: IP, legal and business services. Built with a dependency-free Node build script and deployed on Netlify.

## Build

```bash
node build.mjs        # writes the site to dist/
python3 -m http.server 8080 -d dist   # preview locally at http://localhost:8080
```

Netlify runs `node build.mjs` and publishes `dist/` (see `netlify.toml`).

## Where things live

| What | File |
|---|---|
| Phone, email, address, social links, **tracking IDs** | `src/data/site.mjs` |
| Practice areas (Patent, Design, TM & Copyright, Business & Tax, Legal) | `src/data/categories.mjs` |
| Service pages (content, facts, FAQs) | `src/data/services-*.mjs` |
| General FAQs | `src/data/faqs.mjs` |
| Blog posts | `src/content/blog/*.html` (front matter + HTML) |
| Privacy, Terms, Refund policies | `src/content/policies.txt` |
| Page templates | `src/lib/pages.mjs`, `src/lib/layout.mjs`, `src/lib/components.mjs` |
| Structured data (JSON-LD) | `src/lib/schema.mjs` |
| Styles / script | `src/assets/css/site.css`, `src/assets/js/site.js` |
| Social share images | `src/static/og/` (regenerate: `NODE_PATH=$(npm root -g) node scripts/make-og.cjs`) |

### Add a blog post
Create `src/content/blog/my-post.html`:

```
---
title: Full article title
seoTitle: Shorter title for Google (optional, under ~55 chars)
slug: my-post
date: 2026-12-01
updated: 2026-12-01
description: 120–160 character summary.
category: patent   # patent | design | trademark-copyright | business-tax | legal
---
<p>Article HTML…</p>
<h2>Section</h2>
```

It is added automatically to the blog index, sitemap, RSS feed and `llms.txt`.

### Add a service
Add an object to the right `src/data/services-*.mjs` file. A page is generated at `/<category>/<slug>/`, and it is added to menus, footer, sitemap, structured data and the enquiry form.

## Leads
Enquiry forms are handled by **Netlify Forms** (form name `consultation`). Each submission includes UTM parameters, `gclid`/`fbclid`, landing page and referrer. Set up email notifications in Netlify → Forms → Notifications.

## Ads & analytics
Fill IDs in `src/data/site.mjs → tracking` and rebuild:

- `gtmId` (recommended) **or** `ga4Id` + `googleAdsId` + `googleAdsLeadLabel`
- `metaPixelId`
- Site verification tokens for Google Search Console, Bing and Meta

Events pushed to `dataLayer`: `generate_lead` (on `/thank-you/`, once per submission, with `user_data` for enhanced conversions), `click_call`, `click_whatsapp`, `click_email`, `cta_click`, `form_start`. Meta Pixel fires `Lead` and `Contact`. Google Consent Mode v2 defaults to denied until the visitor accepts the cookie banner (the banner appears only once a tracking ID is set).

Use `/thank-you/` as the conversion page for Google Ads and Meta.

## SEO & AI
- Clean URLs, unique titles/descriptions, canonical tags, Open Graph/Twitter cards
- JSON-LD: Organization/ProfessionalService, WebSite, BreadcrumbList, Service, FAQPage, BlogPosting, Person
- `sitemap.xml`, `robots.txt` (AI crawlers allowed), `llms.txt`, `llms-full.txt`, `blog/rss.xml`
- After going live: submit `https://leegtech.in/sitemap.xml` in Google Search Console and Bing Webmaster Tools.

## Content review
Legal facts were reviewed on the date in `site.lastReviewed`. Re-check statutory timelines and thresholds periodically (e.g. Patents Rules, GST thresholds, MSME limits) and update that date.

## Hosting
- **Production:** Hostinger shared hosting, website `leegtech.in` (account `u784176160`), with Hostinger SSL, Force HTTPS and CDN.
  Deploy with `npm run deploy` (builds, checks, uploads `dist/` via the Hostinger API, purges the CDN).
  `src/static/.htaccess` holds redirects, security headers, caching and the 404 page for Hostinger.
- The built site is also pushed to the `live` branch by `npm run publish-live` (for optional Hostinger Git auto-deploy).
- **Review (old):** Netlify project `leegtech-preview`, no longer the deploy target.
- Netlify Forms do not run on Hostinger; enquiries go to WhatsApp, which is the intended channel.

## Daily blog automation
A scheduled Claude task publishes 2 checked posts per day following `docs/blog-workflow.md`. Topics and the published log are in `docs/blog-topics.md`.
