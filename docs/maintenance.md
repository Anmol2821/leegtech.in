# Website maintenance

Two scheduled Claude tasks keep leegtech.in healthy, in addition to the daily blog task (`docs/blog-workflow.md`).
Both run in the "Default" cloud environment, where the Hostinger API token is injected by the network proxy and
`dns.google`, `leegtech.in` and Hostinger file hosts are reachable.

## Weekly health check (every Monday)
Fix what you safely can, deploy with `npm run deploy` if anything in the repo changed, and report the rest.

1. **Uptime:** fetch every URL in `https://leegtech.in/sitemap.xml` and expect 200. The environment's proxy
   occasionally drops a request (HTTP 000/502); retry a URL up to 5 times before treating it as down.
   Also confirm `https://leegtech.in/google049db8a1006dc535.html` and `/google8c276c0552068377.html` return 200: it keeps Google Search Console
   verified (source: `src/static/`). Never delete it.
   Also confirm `GET https://leegtech.in/api/lead.php` returns `{"ok":false,"error":"method"}` (the lead endpoint is
   running). Do not POST test leads to it except with `_test=1`.
2. **Redirects and errors:** `https://www.leegtech.in/` → 301 to apex; `/privacy` → `/privacy-policy/`;
   `/no-such-page/` → 404; `/wp-admin/` → 410.
3. **SSL:** certificate for leegtech.in valid for more than 14 days (Hostinger renews it automatically; if not,
   check `ssl/status` via the Hostinger API and report).
4. **DNS (via https://dns.google/resolve):** `A leegtech.in = 46.202.161.99` (or the current IP in README),
   `www` → `leegtech.in`, MX `mx1/mx2.hostinger.com`, SPF includes `_spf.mail.hostinger.com`, the three
   `hostingermail-*._domainkey` CNAMEs. DNS is at GoDaddy: report changes, do not try to edit it.
5. **Email plan:** Hostinger mail API `mail_orders_list` (see `scripts/deploy-hostinger.mjs` for how the
   Hostinger MCP client is used). Warn if the leegtech.in email order expires within 30 days or is not active,
   and if mailbox `info@leegtech.in` is not active.
6. **Site checks:** `npm install`, `node build.mjs`, `node scripts/qa/check-site.mjs` (NO PROBLEMS),
   `python3 scripts/qa/spellcheck.py` (UNKNOWN WORDS: 0), devices check (`scripts/qa/devices.cjs` against a local
   server on port 8080).
7. **Live = repo:** compare the live home page's CSS/JS file names with a fresh build; if they differ, deploy.
8. **Blog task health:** the newest post in `docs/blog-topics.md` should be no older than 2 days; if older,
   report it (the daily task may be failing). Keep at least 20 unpublished topics in the backlog; add new,
   relevant topics with real search demand if it runs low.

9. **Link building:** read `docs/seo/backlinks.md`. Research 3–5 new, genuine opportunities (Gwalior/MP
   institutions, incubators, startup communities, directories, journalist requests relevant to Indian IP and
   business registration). Verify each exists before adding it. Add them to the tracker with an angle and the
   matching template from `docs/seo/outreach-templates.md`, and list them in the report so the owner can send
   them. Never buy links or add the site to spammy directories.
10. **Tools:** check `/tools/trademark-class-finder/` returns sensible classes for "tea", "software", "clothing" and
    that `/tools/patent-deadline-calculator/` shows dates for a sample priority date.

## Monthly content review (1st of each month)
Purpose: keep legal and statutory information current. Follow CLAUDE.md ground rules.

1. Check official sources for changes since `site.lastReviewed` (`src/data/site.mjs`): Patents Act and Patents
   Rules amendments, Trade Marks Rules, Designs Rules, Copyright Rules, Companies/LLP rules and MCA forms and due
   dates, GST registration thresholds, MSME (Udyam) classification limits, DPIIT startup criteria, FSSAI licence
   categories, the Income-tax Act, 2025, PCT and Madrid membership changes.
2. Update affected text in `src/data/*.mjs`, FAQs and blog posts (set the post's `updated` date). Never add fee
   amounts, guarantees or unverifiable claims.
3. **Official fees:** open each source URL in `src/data/official-fees.mjs` (IP India patents schedule PDF, trade
   marks fees page, Designs Rules) and compare every amount. Update any changed amount and set `verifiedOn` to
   today. If a source cannot be reached, report it and leave the data unchanged (do not guess).
   Also re-check `src/data/poa-stamp-duty.json` against https://ipindia.gov.in/stamp-duty-for-POA (update the
   data and `POA_PUBLISHED` in `src/lib/tools.mjs` if IP India publishes a newer version).
4. If anything was reviewed, set `site.lastReviewed` to today. Run all checks, commit, `npm run deploy`,
   `npm run publish-live`.
5. Report what changed, with sources, or "no changes needed".

## Website leads
Every enquiry-form submission is saved by `src/static/api/lead.php` (PHP 8.3 on Hostinger) before the visitor is
sent to WhatsApp. Storage is a SQLite file **outside public_html**: `domains/leegtech.in/leads-data/leads.sqlite`
(all fields, including test rows flagged `is_test=1`), mirrored to `leads.csv` in the same folder for real leads only.
The owner downloads `leads.csv` from hPanel > File Manager > domains/leegtech.in/leads-data. Deploys never touch this
folder. Security: same-origin check, honeypot, server-side validation, max 5 submissions per IP per 10 minutes (IP
stored only as a salted hash), files chmod 600, CSV formula-injection guard. The Meta Pixel `Lead` event fires on
submit with `eventID` = the lead's `event_id`, so a future Conversions API event can be de-duplicated.

## Search Console
The owner verified leegtech.in in Google Search Console (URL prefix, HTML file) on 2026-11-25. There is no API access;
the owner sends a Performance export (CSV zip) or screenshots each month. Use it in the monthly review to find
queries ranking on pages 2–3, pages with many impressions but few clicks, and pages not indexed; turn findings
into title/description improvements and new topics in `docs/blog-topics.md`.

## Owner-requested changes
Content or design changes requested by the owner are made in a normal session following CLAUDE.md, checked
with the same scripts, committed and deployed with `npm run deploy`.
