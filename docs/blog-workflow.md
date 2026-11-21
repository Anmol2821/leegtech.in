# Daily blog workflow (2 posts per run)

## 1. Pick topics
- Take the next 2 unpublished topics from `docs/blog-topics.md` (or add better ones with clear search demand in India). Never duplicate an existing post in `src/content/blog/` or overlap heavily with one.
- Prefer topics that link naturally to a service page.

## 2. Research and write
- Research using official sources (Acts, Rules, IP India manuals/notices, MCA, CBIC, DPIIT, Udyam, FSSAI, WIPO). Note the source for every legal fact while writing.
- 900–1,400 words. Structure: intro answering the query directly → H2 sections (steps or key points) → "Common mistakes" (where useful) → "Frequently asked questions" with 2–4 H3 questions → standard note.
- Front matter: `title`, `seoTitle` (≤ 55 chars), `slug` (kebab-case, keyword first), `date` and `updated` (today, YYYY-MM-DD, IST), `description` (120–160 chars), `category` (patent | design | trademark-copyright | business-tax | legal).
- Use `<div class="table-wrap"><table>…</table></div>` for tables. Link 2–4 relevant service pages and, where relevant, one other blog post.
- Files: `src/content/blog/<slug>.html`.

## 3. Checks (all must pass before publishing)
1. **Fact check:** re-read every legal statement against its source. Remove anything unverified or out of date. Double-check numbers (days, months, years, thresholds, section numbers).
2. **Legal wording:** no guarantees, no fee amounts, no superlatives, no solicitation, no government-affiliation implication; standard note present.
3. **Build:** `node build.mjs`
4. **Site check:** `node scripts/qa/check-site.mjs` → must print `NO PROBLEMS`.
5. **Spelling:** `pip install -q pyspellchecker` then `python3 scripts/qa/spellcheck.py blog/<slug>` for each new post → must print `UNKNOWN WORDS: 0`. Fix genuine errors; add only verified proper nouns or legal terms to `scripts/qa/words.txt`.
6. **Devices:** `python3 -m http.server 8080 -d dist &` then `node scripts/qa/devices.cjs` (Playwright is pre-installed; run with `NODE_PATH=$(npm root -g)`) → must print `NO ISSUES`.

If any check fails and cannot be fixed with confidence, do not publish that post. Publish the other one if it passed.

## 4. Publish
1. Append the published posts (date, title, URL) to the log in `docs/blog-topics.md` and mark the topics done.
2. Commit to `main` with a clear message and push.
3. Deploy: `npm install` (once) then `npm run deploy`. It must end with `Deployed to https://leegtech.in`. Then run `npm run publish-live`. If deploy fails, report the error; the commit stays on `main` for the next run.
4. Finish with a short summary: titles, URLs, sources used, and anything skipped and why.
