---
name: backfill-bc-mla-backgrounds
description: Investigate who each BC provincial candidate and sitting MLA for the Salmon Arm-Shuswap riding is -- professional biography, career, businesses, corporate boards and directorships, corporate ownership, shares, property and other assets, political donations, public service and legislative record, affiliations, past statements and any sourced, verified controversy -- for the cultpantry admin panel's Elections module (modules-elections), by working the public record like a detective: following every secondary link and loose thread until the interconnections show. Writes a ready-to-import XML file of sourced, categorized background facts plus a short neutral bio. Use this when the user asks to dig into, backfill, research or investigate a provincial candidate's or MLA's background, finances, holdings, connections or record, for everyone or a named person, even if they don't mention XML or this skill by name. The roster is find-bc-provincial-candidates; platforms and statements belong to the provincial research pass.
---

# Backfill BC MLA and candidate backgrounds (the spiderweb)

For each provincial candidate already on file (`election id
bc-provincial-salmon-arm-shuswap`), establish **who this person is and what
they are connected to**, from public sources only, and write `background`
entries -- one sourced fact each -- plus a short neutral `bio`. It never
writes to the database; the user reviews the file and imports it.

**Read `../research-salmon-arm-candidates/references/elections-xml-schema.md`
before writing any output**, especially "Which election" (the root must be
`<election id="bc-provincial-salmon-arm-shuswap">`) and the `background` topic
list. **Blocked pages, Castanet, Facebook, LinkedIn:** read them through
Browserless, per `../research-salmon-arm-candidates/references/browser-access.md`
(which also documents the Playwright fallback).

## How to think: a web, not a checklist

Don't work down a list of sources and stop. Work like an investigator with a
corkboard. Every fact you find is a **node**; every name, company, address,
file number, date, employer, co-director, co-signer, charity, law firm,
registry id and URL inside it is a **thread** to pull. The valuable findings
are almost never on the first page; they are the **interconnections** --
the company that also lists a director who is also a donor to the same
party, the property held through a numbered company that shares an address
with the candidate's business, the board seat that lapsed the year the
contract was awarded.

Rules of the hunt:

1. **Keep a live thread list.** From the first page you open, write down
   every unresolved name, company, entity number, address, date and
   cross-reference you meet, **including ones that look irrelevant to the
   question you were asking.** Irrelevant now is not irrelevant later. A
   footnote, a "formerly known as", a co-director, a registered office, a
   sponsor logo, an "also see", a signature block, a comment-section
   mention: all threads.
2. **Pull every thread at least one hop.** For each lead, open it, decide
   whether it ties to the person (rule 3), and add whatever *it* mentions to
   the thread list. Go **two or three hops** from the candidate on anything
   that keeps yielding ties. Stop a branch only when it stops producing new
   tied facts, not when the original question is answered.
3. **Tie everything, twice.** A node counts only when **two independent
   identifiers** connect it to *this* person (their riding, a named employer,
   a business name already sourced, an unusual full name or middle initial,
   a photo credit, a shared address already on file, a source that itself
   names them in context). Common names collide; a wrong match pins someone
   else's assets or lawsuit on a candidate. One identifier = a **lead**, not a
   fact: chase it, don't record it, and say so in the summary. Surname-only
   matches (a family-named holding company) stay out entirely.
4. **Follow the money and the paper.** Prefer records that are *created by a
   rule* over stories about the person: registries, disclosures, filings,
   court registries, Hansard, Votes & Proceedings, land and corporate
   records, financial returns. They are checkable and neutral. Press and
   party pages are for leads and for context the records don't carry.
5. **Cross-check every node.** A fact from one source is a fact; a fact from
   two independent sources is solid. Where two sources disagree, record both
   in `<notes>` and the summary and let the user decide. Flag, don't resolve.
6. **Map the web in the notes, not just the entries.** Entries hold the
   verified facts. Put the *connections* and the *open threads* in the
   candidate's `<notes>`: "Director of X Ltd. (BC Registries, 2019-2023);
   X's other director, Y, appears as a donor to the same party (Elections BC,
   2022) -- not verified as connected to the candidate." Unverified links are
   leads for the user, clearly labelled, never entries.
7. **Time-box and report dead ends.** Note branches that went nowhere
   ("searched BC Registries for 'Williams Appraisals' -- three same-name
   firms, none tied") so the next run doesn't repeat them.

## Rules (what is in, what is out)

1. **In: public, solidly sourced, verified fact -- however controversial.**
   Being awkward for the candidate is not a reason to leave something out,
   and being flattering is not a reason to keep it. A fact needs a source you
   opened, cited by URL, and enough corroboration that you'd stand behind it.
   State it neutrally: quote or cite the record, don't characterize, don't
   speculate about motive or meaning.
2. **Out: family and health.** Never record children, spouses or partners
   (names, ages, jobs), divorces and other family-law matters, or health.
   "Owns a house" is in; "lives there with his wife and kids" is not. If a
   record names a family member (a co-owner, a co-director who is a relative),
   do not record the relative's name or the relationship: record the
   candidate's own holding and note "jointly held" only if the record says so
   without naming. This is the user's line (see "Research sensitivity level"
   in memory): **assets, property records, ownership, directorships, shares,
   donations and sourced controversies are fair game; family is not.**
3. **A residential street address is the one thing to ask about.** Record
   owned property by municipality, parcel type and the registry's own
   description. Don't put a home street address into the file unless the user
   has said to; flag it in the summary instead.
4. **No inference of affiliation from a name, appearance or photo.** If no
   source says it, it isn't a fact.
5. **Date it.** `published_on` is the source's own date (the filing date, the
   disclosure year, the article date). For a fact that spans years, put the
   years in the summary.
6. **Treat everyone the same.** Same sources, same effort, same depth for
   every candidate, including newcomers with little on file. Say in the
   summary when someone has little published.
7. **Open every source.** Search results and snippets only find pages;
   they are never a source. Search tools here are US-indexed and often wrong
   or stale, and have returned dead links: open the page and confirm.

## What to look for (categories)

`topic` values on a `background` entry. The last three are new and have no
municipal equivalent; all of the right-hand column shows on the Background &
Affiliations tab.

| topic | what goes there |
|---|---|
| `career` | professional biography: jobs, employers, profession, years in a field, certifications and licences |
| `business` | businesses they own, co-own or run (name, what it does, years) |
| `education` | schools, degrees, diplomas, professional credentials |
| `corporate_roles` | **boards, directorships, officer roles** at companies, societies and non-profits: name of the entity, role, dates, registry id when you have it |
| `holdings` | **corporate ownership, shares, property and other assets** from public records or mandated disclosures: what, where (municipality, parcel type), how held, source and year. Include corporate ownership found through registries and filings |
| `political_giving` | **political donations the person themselves made** to a party, candidate or campaign: recipient, amount band or amount as published, year, filing |
| `public_service` | elected or appointed office, committees, past runs for office with the year and result, **and the legislative record**: ministerial/critic/caucus roles, committee memberships, bills sponsored, notable votes (cited from the Votes & Proceedings) |
| `community` | boards, non-profits, volunteering, clubs, coaching, events they organize |
| `affiliation` | churches and other faith communities, service clubs, lodges, unions, professional bodies, associations, parties (name, role, years) |
| `past_activity` | what they did before: projects led, causes backed, delegations, awards, **documented controversies, legal and regulatory findings, complaints and their outcomes, as the record states them** |
| `local_roots` | how long in the riding, where from |
| `other` | a public fact that fits nowhere above |

## Sources: where to start pulling threads

Start anywhere; the web decides the order. Seeds, by usefulness:

1. **Baseline on file** (read-only):

   ```
   cd /Users/shawn/Documents/code/cultpantry && php artisan tinker --execute="echo json_encode(\Cultpantry\Elections\Models\Candidate::forElection(\Cultpantry\Elections\Models\Election::provincial())->with(['entries' => fn (\$q) => \$q->where('kind', 'background')])->get(['id', 'name', 'party', 'is_incumbent', 'occupation', 'bio', 'bio_source_url', 'website', 'facebook_url', 'notes']));"
   ```

   Use each candidate's exact `name`. If none come back, stop: the roster has
   to be imported first. `notes` already holds flagged leads from the roster
   pass (for example an unresolved federal-party question): resolve those.

2. **Party and campaign pages** (`candidate_site`, party site): the bio, past
   roles, the riding association, the candidate's own site. Note every
   organization, employer and person named -- threads.
3. **BC Legislature** (`leg.bc.ca`, `lims.leg.bc.ca`): member profile, roles,
   committees, Hansard (speeches), Votes & Proceedings (bills, divisions),
   order papers. For the sitting MLA especially.
4. **Conflict of Interest Commissioner** (the public disclosure statements
   MLAs file): the declared interests -- real property, corporate interests,
   income sources, gifts -- as published. Confirm what the pages actually
   show before relying on them; do not extrapolate.
5. **BC Registries / OrgBook BC** (corporate registry): search the person's
   name and every business name found. Directors, officers, registered
   offices, other directorships, amalgamations, dissolutions. **Follow each
   company's other directors and its registered office** (rule 3 still
   applies to each hop).
6. **Property**: BC Assessment (public roll), the Land Owner Transparency
   Registry, Land Title (may cost per search; ask before spending).
7. **Elections BC financial disclosure** (`elections.bc.ca` financial
   reports and contributions system): the candidate's own filings and
   donations *made by* them, found by name with the second identifier
   (city, employer). Donors to the campaign are other people's data: leave
   donor names out of entries; use them only as unrecorded leads.
8. **Courts and tribunals**: BC court registries' public records and
   regulator decisions (BC Securities Commission, professional bodies,
   Information and Privacy Commissioner). Family-law matters are out.
9. **Press and archives**: Salmon Arm Observer, Castanet, My Shuswap Now,
   Eagle Valley News, Frequency News, CFJC, Radio NL, CBC. News is the
   connective tissue: it names people and companies the registries don't
   link. Wayback copies for pages that have since changed or blocked
   automated fetches.
10. **Facebook** (candidate/party pages, logged out; saved `facebook`
    profile only for members-only groups with the user's say-so) and
    **LinkedIn** (saved logged-in profile only, per browser-access).
    **Instagram: skip.** Read-only, light, per browser-access.
11. **Local organizations**: `php artisan elections:scan-organizations
    --candidate="<exact name>" --election=bc-provincial-salmon-arm-shuswap`
    (in the cultpantry app) for the module's registry of local churches,
    clubs and societies; every hit is a lead to open and tie, not a fact.

## Field notes from the first run (David L Williams, Oct. 2026)

How the sources actually behave, so the next run doesn't relearn it. Re-verify
anything that looks changed; these are observations, not guarantees.

**Conflict of Interest disclosures (the richest source for an MLA)**
- Commissioner's site is `https://coibc.ca/` (not `www.`, whose certificate is
  wrong). Its Disclosure Process page says what a statement covers.
- The statements themselves are on the Legislature's site:
  `https://www.leg.bc.ca/about/accountability/members-disclosures/public-disclosure-statements`.
  The per-member PDF links sit inside a collapsed accordion, so a plain link
  scrape misses them; list **all** links on the page (the fetch script caps
  `--links` at 80) and filter on the member's surname.
- A `leg.bc.ca/parliamentary-documents/...pdf` address returns an **HTML
  viewer page**, not the PDF. The real file is the iframe address
  `https://api.lims.leg.bc.ca/pdms/file/content-committees/AccountabilityDocuments/MLAs/...`.
  Download each file into its own empty folder and extract with `pdftotext -layout`.
- Per member there can be: this year's and last year's **statement**, **notices
  of material change** (dispositions, new acquisitions) and **gift
  declarations**. Statements are removed five years after dissolution, so
  save the facts.
- **A statement covers the member, the spouse and minor children together,
  marked `(Member)` / `(Spouse)`.** Record only `(Member)` lines and
  `(Member & Spouse)` joint holdings (as "held jointly", naming no one).
  Anything marked `(Spouse)`, including income, property, deposits, tax owed
  and a spouse's property sold in a material change, is left out entirely.
- Statements give the **nature, not the value**, of assets, and name shares
  by company only. Don't put a value or a jurisdiction on a holding the
  record doesn't give; identify the issuer from its own filing or release and
  say the disclosure gives the name only. If no single company matches the
  name, say so and don't guess.

**Registries and records**
- **OrgBook BC** has a plain JSON API that needs no browser:
  `https://orgbook.gov.bc.ca/api/v4/search/topic?q=<name>&inactive=any&latest=true&revoked=any&page_size=6`
  (results carry registry id, status, type, registration date). It confirms an
  entity exists and its status; **it does not list a society's directors**, so
  a directorship stays sourced to the disclosure or the entity's own page.
  Names must match exactly to count: a near name is a lead.
- **Elections BC contributions system** (`contributions.electionsbc.gov.bc.ca/pcs/`):
  an ASP.NET form. *Political Contributions* searches by contributor name
  (try `First Last`; `Last, First` returned nothing) and returns name, date,
  amount, recipient and class, **with no address or city**. A common name
  therefore returns unrelated people and nothing ties any row to the candidate
  (a first-run search for "David Williams" gave 17 records across three
  parties). Treat those as leads in `<notes>`, not entries.
  *Election Summary* (partial filer name + event + electoral district) returns
  the candidate's **own** financing report (income, expenditures,
  reimbursement), which is tied by the district and filer type and *is* a
  fact. Don't collect the campaign's donor names.
- **Hansard** is on `lims.leg.bc.ca/hdms/file/Debates/<parliament><session>/<date><am|pm>-Hansard-n<issue>.html`;
  a search restricted to `lims.leg.bc.ca` and `leg.bc.ca` found a speech by a
  keyword from a disclosure (a directorship), which is exactly the
  cross-link the method is after. Open the transcript, read the exact words,
  and check what was and wasn't declared.
- The Legislature's member profile
  (`leg.bc.ca/members/43rd-Parliament/<First>-<Last>`) renders empty without
  its API; use it only to confirm the page exists. Roles come from the party
  page and Wikipedia (cited to the party), checked against Hansard.
- Wayback copies (`web.archive.org/web/2024/<url>`) read pages that block
  automated fetches (Castanet, and CentreBC's site from a plain fetch).

**Dead ends, so you don't repeat them**
- DuckDuckGo's HTML search through a browser returned a non-result page (202);
  Bing and Google weren't tried because search pages invite bot checks. Use
  the search tool for leads, then open the real pages.
- `barchart.com` company pages are blocked (403); the company's own
  newswire release (`newswire.ca`, `newsfilecorp.com`) loads.
- `cfjctoday.com`, `castanet.net` are bot-checked from a fresh browser; use
  the archive copy or a different outlet.

**How findings mapped to entries**
- One entry per disclosed item; **one entry per issuer** for shares.
- `finance` kind (topic `governance_transparency`, source type `elections_bc`)
  for the candidate's own financing report.
- A gift declaration is a `background` entry with topic `other`.
- A legislative statement is `public_service`, with the short verbatim line
  as `quote` and the issue number in `source_name`.
- Liabilities and income sources from a disclosure were included because they
  are mandated public financial records; they are a judgment call, so name
  them in the hand-off and make them easy to drop.
- Party-page claims that no independent record confirms stay in the file,
  attributed to the party page in the summary ("his party profile says ...").

## The loop (per person)

1. Pull the baseline and seed the thread list from it and from the first
   two sources.
2. **Round 1 -- breadth:** open each seed; record tied facts as draft
   entries; add every name, entity, address, number and date to the threads.
3. **Round 2 -- depth:** work the thread list, nearest ties first. For each
   thread, search the right registry or record for it, open the hit, test
   it against rule 3, add what it mentions. Record new tied facts; log
   unconfirmed hits as leads.
4. **Round 3 -- cross-links:** look across all nodes for the connections
   that matter: shared addresses, shared directors, dates that line up,
   the same entity appearing in two registries, a donor/director overlap.
   Write each as a labelled, unverified link in `<notes>` unless both ends
   are verified facts, in which case state the connection neutrally in a
   summary.
5. **Stop** when a full pass over the thread list produces no new tied
   facts, or the remaining threads need paid access or a login you don't
   have (list them).

**Browser first, search second.** Open the authority and record pages in a
real browser before any web search, and do discovery searches inside the
browser where you can: the search tool is US-indexed, stale, and has returned
dead links and false claims. Registries and public records (BC Registries,
OrgBook, BC Assessment, the Legislature, Elections BC) are pages to *open and
read*, not things to infer from snippets.

Use Browserless (the `browser-scrape` skill) by default. When it is out of
units or down, fall back to Playwright with
`../research-salmon-arm-candidates/references/playwright-fetch.cjs` -- public
pages only; a security challenge is a stop for that page (try a web-archive
copy), never something to engineer around. Keep browser work in the main
session, one site at a time, with the paced, low-volume habits in
`browser-access.md`. Close every Browserless session the moment its data is
extracted.

## Writing it up

- **One fact per `background` entry.** `summary` is one neutral sentence with
  the specifics (entity, role, dates, amounts as published). `quote` only for
  a short verbatim line from the record; copy it exactly.
- **`source_url`** is the exact page or document you opened, and
  `source_type` is the right kind from the schema list (`elections_bc`,
  `city`, `organization`, `news`, `candidate_site`, `other`, ...). For a
  registry or court record use `other` and say what it is in `source_name`.
- **`bio`**: two or three neutral sentences from what you sourced, with
  `bio_source_url` set to the single best source. Only when you have enough.
- **`occupation`**: a short label, only when a source states it.
- **`notes` replaces what's on file**, so start it with the existing notes
  unchanged, then a blank line, then this pass's notes: the web map
  (connections and open threads), dead ends, and conflicts.
- Don't repeat what's on file. Re-supplying an identical entry is harmless
  but isn't new.

Save to the app repo's `database/elections/` folder as
`bc-provincial-backgrounds-<YYYY-MM-DD>.xml`, or with the candidate's name
for one person. About five candidates per file. The root is
`<election id="bc-provincial-salmon-arm-shuswap">`.

## Hand-off summary

- **Per person:** facts filed, by category, and the sources used.
- **The web:** the connections you found (verified ones plainly; unverified
  ones labelled as leads), and the threads still open, with what each would
  need (a paid search, a login, a records request).
- **Held back, and why:** matches that couldn't be tied under rule 3;
  anything touching family; a residential address; surname-only matches.
- **Conflicts** between sources, for the user to decide.
- **Dead ends** worth not repeating.
- Who has little published.
- Anything you couldn't open.

**Saving and importing:** follow "Saving and importing" in
`../research-salmon-arm-candidates/references/elections-xml-schema.md`: write
the file into the app repo's `database/elections/` folder with the next
sequence number, then `php artisan elections:import-pending` (dry run first).
