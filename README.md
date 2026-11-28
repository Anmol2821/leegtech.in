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
- The enquiry form posts to `src/static/api/lead.php`, which saves the lead (with UTM/gclid/fbclid, landing page, referrer)
  to `leads-data/leads.sqlite` **outside public_html** (plus a `leads.csv` mirror), then the visitor continues to WhatsApp.
- **Lead tracker:** `https://leegtech.in/admin/` (private, password-protected, not indexed). Source: `src/static/admin/`.
  Shows website leads automatically; leads from WhatsApp/Meta ads, calls and referrals are added with "+ Add".
  Stages New → Contacted → Consulted → Quoted → Won/Lost, follow-up dates, notes, quote amounts, ad codes,
  which WhatsApp number the lead came in on (numbers are named in Settings),
  monthly counts by source and ad code, CSV export.
  - Shared code `admin/_inc/app.php` (blocked from the web by `_inc/.htaccess`); the service picker list
    `admin/_inc/services.json` is written by `build.mjs`.
  - Password hash: `leads-data/admin.json`. Forgot it? Delete that file in hPanel → File Manager, generate a new
    one-time setup code, put its `password_hash()` in `admin/_inc/config.php`, deploy, and set up again at `/admin/`.
  - Never commit `leads-data/` or any lead data to this repository.
- **Clients, matters and deadlines** (`admin/_inc/clients.php`): a won lead becomes a client ("Make client"); each client has
  matters (one per filing/job) with application no., filing date, status, Vyapar invoice no., fee, payment status
  (Pending / Advance received / Partially paid / Fully paid) and amount received, notes, and deadlines (incl. "Filing due").
  Leads also have a payment status and amount received, carried over when the lead becomes a client.
  Deadlines are typed in from the official notice; the tracker never calculates statutory dates. Today shows overdue
  deadlines and those due in the next 15 days.
- **IP tabs and sheet import** (`admin/_inc/portfolio.php`): Clients has tabs Patents / Designs / Trademarks / Copyrights
  (matter `ip_type`, set from the service). "Import from sheet" uploads a CSV (e.g. a Google Sheet tab), shows a preview, then
  adds applications (applicant, application no., filing date, contact, office status mapped to a tracker status); rows are
  grouped into clients by contact email, then phone. Re-importing updates existing application numbers. The CSV is never
  stored in this repository.
- **Team and assignments** (`admin/_inc/team.php`): the owner (original password) adds team members in Settings → Team
  (name, email, own password; passwords must be unique because sign-in is by password only). Leads, matters, individual
  deadlines and tasks can be assigned (one at a time or "Assign all shown"); the person is emailed. By default team members
  see only their own work, never amounts/payments, and cannot delete, import, export, assign or change settings. The owner can
  tick extra powers per member (`PERMS` in team.php, checked with `can()` on every action): all leads, all clients &
  applications, fees &
  payments, assign work, import & download, client portal, delete. Team and settings stay owner-only. A deadline without
  its own assignee belongs to whoever handles its matter. The owner and members with Assign work can edit a task (title, due date, person, note); the Delete power removes it.
  Notes record who wrote them. Switching a member off signs them out.
- **Client portal** (`src/static/portal/`, shared code `admin/_inc/portal_lib.php`): the owner switches it on per client
  (client page → Give portal access; a one-time password is shown with WhatsApp/email share buttons). Clients sign in at
  `/portal/` with mobile or email + password and see only their own matters: status, office status, upcoming deadline
  dates (no notes), fee/received/balance, the matter's "message to the client", and documents; they can upload documents
  (PDF, images, Word, Excel; base64 via `portal/portal.js`). Files live in `leads-data/uploads/` outside public_html and
  are served only after a sign-in check. Uploads are logged on the matter and emailed to the owner and the matter's handler.
- **Daily summary email** (`admin/_inc/daily.php`, CLI only): an hourly Hostinger cron job runs
  `/usr/bin/php /home/u784176160/domains/leegtech.in/public_html/admin/_inc/daily.php`; it sends one email a day after
  9am IST (deadlines, follow-ups, tasks, new leads) to the address in Settings (default info@leegtech.in) via PHP `mail()`,
  and a personal one to each active team member with an email (only their work, no amounts).
  Preview without sending: `php daily.php --preview`.

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
- **Domain & DNS:** leegtech.in is registered at GoDaddy and uses GoDaddy DNS (ns67/ns68.domaincontrol.com). `A @ 46.202.161.99` points to the Hostinger server; `www` is a CNAME to `leegtech.in`. Email is Hostinger Business Email (MX mx1/mx2.hostinger.com, SPF, DKIM CNAMEs in GoDaddy DNS).
- **Production:** Hostinger shared hosting, website `leegtech.in` (account `u784176160`), with Hostinger SSL, Force HTTPS and CDN.
  Deploy with `npm run deploy` (builds, checks, uploads `dist/` via the Hostinger API, purges the CDN).
  `src/static/.htaccess` holds redirects, security headers, caching and the 404 page for Hostinger.
- The built site is also pushed to the `live` branch by `npm run publish-live` (for optional Hostinger Git auto-deploy).
- **Review (old):** Netlify project `leegtech-preview`, no longer the deploy target.
- Netlify Forms do not run on Hostinger; enquiries go to WhatsApp, which is the intended channel.

## Daily blog automation
A scheduled Claude task publishes 2 checked posts per day following `docs/blog-workflow.md`. Topics and the published log are in `docs/blog-topics.md`.
