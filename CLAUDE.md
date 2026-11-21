# leegtech.in: notes for Claude

Static site for Leegtech (IP, business registration, compliance and legal documentation services, Gwalior, India). Built by `node build.mjs` into `dist/`. See README.md for structure.

## Ground rules for all content
- **Verified facts only.** Every legal or statutory statement (sections, deadlines, terms, thresholds, forms) must be checked against the current law or the official source (IP India, MCA, CBIC/GST, DPIIT, Udyam, FSSAI, WIPO, USPTO, UKIPO, CIPO). If you cannot verify it, leave it out.
- **No official fee amounts, no prices, no statistics, testimonials, ratings, client counts or success rates.** Fees change; say "official fees are charged at actuals".
  - **Only exception:** the Official Fee Calculator (`/tools/official-fee-calculator/`), whose amounts live solely in `src/data/official-fees.mjs`, each read from the official IP India schedule linked there, with `verifiedOn`. Never copy fee amounts into other pages or blog posts; link to the calculator instead.
- **No guarantees and no solicitation.** Never promise grant, registration, approval, timelines or rankings. No "best", "No. 1", "100%", "guaranteed". This site must not read as an advertisement of legal services (Bar Council of India rules). Never imply affiliation with any government office.
- Indian/British spelling (recognise, colour, licence as noun / license as verb, cheque, programme). Plain language.
- Every blog post ends with the standard note: general information, not legal advice, no guarantee, plus a link to the relevant service and `/contact/`.
- Enquiries go to WhatsApp (+91 89823 46105). Do not change contact details without the owner's instruction.

## Daily blog workflow
Follow `docs/blog-workflow.md` exactly. Topic backlog and published log: `docs/blog-topics.md`.



## Maintenance
Weekly health check and monthly content review: `docs/maintenance.md`. Deploy with `npm run deploy` (Hostinger).
