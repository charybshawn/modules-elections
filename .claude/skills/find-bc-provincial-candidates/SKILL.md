---
name: find-bc-provincial-candidates
description: Setup for the BC provincial election side of the cultpantry admin panel's Elections module (modules-elections) that finds everyone seeking to be the MLA for the Salmon Arm-Shuswap riding in the next BC general election and writes a ready-to-import XML roster (name, party, status, whether they are the sitting MLA, campaign links, the round-up articles that name them, and the election events). Use this when the user asks who is running provincially, who the MLA candidates are for Salmon Arm-Shuswap, or wants to set up, start or refresh the BC provincial election tracker, even if they don't mention XML, the import or this skill by name. The municipal race is find-salmon-arm-candidates; researching what the provincial candidates say or stand for comes later.
---

# Find BC provincial candidates (Salmon Arm-Shuswap)

Builds the list of everyone seeking to be the MLA for the **Salmon Arm-Shuswap**
riding in the next BC general election and writes it as an XML file for the
Elections module's provincial side (Elections → BC Provincial Election). It
mirrors `find-salmon-arm-candidates`: it runs first, the file is imported, and
the provincial dashboard then lists who is running. It writes an import file
into the app repo's `database/elections/` folder and loads it with
`php artisan elections:import-pending` (dry run first).

**Read `../research-salmon-arm-candidates/references/elections-xml-schema.md`
before writing any output**, especially "Which election". Every file from this
skill starts `<election id="bc-provincial-salmon-arm-shuswap">`; without the
`id` it would be imported as a municipal file and rejected (`mla` is not a
municipal office).

**Blocked pages:** read them through Browserless, per
`../research-salmon-arm-candidates/references/browser-access.md`.

## Find out which stage the race is at first

BC elections are normally fixed-date (the third Saturday in October, every four
years), but a **snap election can be called at any time**, and the first run of
this skill (Oct. 11, 2026) found one: writ Sept. 22, final voting day Oct. 24,
2026, nominations closed Oct. 3. Don't assume from the calendar or from a web
search, which is US-indexed, often stale and has wrongly said "there is no 2026
election". **Open `https://elections.bc.ca/` and its election page**
(`/2026-provincial-election/` or the current year's) and read the key dates
before anything else, then branch:

- **Writ dropped, nominations closed:** Elections BC's candidate list
  (`/<year>-provincial-election/candidate-list/`, a table of district, candidate
  and affiliation) is final and official. Everyone on it is `nominated`.
- **Writ dropped, nominations open:** the list is partial and still growing.
  Re-run (reconcile-only) before the nomination deadline.
- **No election called:** there may be only the sitting MLA and a few declared
  or nominated candidates, or nobody. That is a normal result: write what is
  verifiably on a page, report the gap, and don't pad the list.

## Rules

1. **Every name comes from a page you actually opened.** Search results only
   tell you which pages to open. Confirm the person is seeking the Salmon
   Arm-Shuswap nomination or seat (not a neighbouring riding) on the page.
2. **Neutral and factual.** Record who is running and where it is stated,
   nothing about their merits. Party is a fact about a candidate; record it in
   `<party>` exactly as Elections BC prints it in the Affiliation column
   (and in `<notes>` where you found it), never as a judgment.
3. **Private individuals stay out**, and nothing about any candidate's family,
   health, address or private life.
4. **Flag, don't resolve.** When sources disagree (a name spelled two ways,
   someone announced but not yet on Elections BC's list, a nomination contest
   still open), say so in `<notes>` and the summary, and let the user decide.

## Workflow

1. **Check what's on file** (read-only):

   ```
   cd /Users/shawn/Documents/code/cultpantry && php artisan tinker --execute="echo json_encode(['candidates' => \Cultpantry\Elections\Models\Candidate::forElection(\Cultpantry\Elections\Models\Election::provincial())->get(['name', 'office', 'status', 'is_incumbent']), 'events' => \Cultpantry\Elections\Models\ElectionEvent::forElection(\Cultpantry\Elections\Models\Election::provincial())->get(['title', 'starts_at'])]);"
   ```

   If candidates are already on file, do a reconcile-only run: report names
   that are new or missing and status changes, and write only those. Spell every
   returning candidate exactly as on file.

2. **Elections BC** (elections.bc.ca), **opened first**, in a browser, before
   any search: it is the authority on status and tells you which stage the
   race is at. Read it through Browserless
   (`browserless_smartscraper` or a session) or, if Browserless is out of
   units, `playwright-fetch.cjs` (see browser-access.md):
   - Its candidate list (the `Salmon Arm-Shuswap` rows of the table; the CSV link
     on that page is the whole province, so don't download it). Everyone on its
     official list is `nominated`.
   - Withdrawals it lists → `withdrawn`.
   - The election page's "Key dates" table (writ day, nominations close,
     advance voting, vote-by-mail request deadline, final voting day, final
     count, return day): each is an `<event>` with the Elections BC page as
     `<url>` (`general_voting`, `advance_voting`, `deadline`, `other`).
   - The advance voting places PDF (`/<year>election/advance-voting-places-<year>.pdf`):
     the riding's rows (place, address, dates) go in the advance voting event's
     `<description>`. Read PDFs with `browserless_smartscraper` and `rawText`;
     one at a time, since a burst gets a 429.

3. **Web search, only after the authority pages are open,** for the race ("Salmon Arm-Shuswap MLA candidates", "Salmon
   Arm-Shuswap BC election candidate nomination", "BC election 2028 Salmon
   Arm-Shuswap") to find the pages to open in the next steps. Confirm first
   that the riding is still called Salmon Arm-Shuswap (boundaries and names can
   change before an election); if it has changed, stop and tell the user.
   The search tool is US-indexed and has returned stale pages, dead links, and
   flat wrong claims (that no election exists). Treat results as leads only and
   search through the browser (Browserless, or the Playwright fallback) where
   you can.

4. **The sitting MLA** (BC Legislature member page, leg.bc.ca): the person
   holding the seat now is `is_incumbent` = `true` *if they are running again*.
   If they have said they are not, record that in the summary and leave them off.

5. **Parties and riding associations.** Each party's candidates page and the
   Salmon Arm-Shuswap riding association pages (BC NDP, BC Conservatives, BC
   Greens, BC United or successor, others): a nominated or declared candidate
   named there who is not yet on Elections BC's list is `declared`. Open the
   page; never infer a nominee from a party's social posts alone.

6. **Local press**: Salmon Arm Observer (`saobserver.net`), Castanet
   (`castanet.net`), My Shuswap Now (`myshuswapnow.com`, whose "2026 BC
   Election" section loads reliably), Eagle Valley News, Frequency News and
   South Shuswap Scoop for announcements and round-ups. **Search-tool URLs can be
   wrong:** two story links a search returned (Observer, Eagle Valley News) were
   404s when opened, so only cite a story you actually opened.

7. **Reconcile** every name into one list:
   - On Elections BC's list → `nominated`. Announced or named by a party but
     not on the official list → `declared`, and flag it ("announced but not
     on the official list yet").
   - Use Elections BC's spelling as `<name>`, since the import matches on it
     exactly (within the provincial election). Put other spellings in `<notes>`.
   - Every candidate has `<office>mla</office>`.

8. **Light fields only.** For each candidate: `name`, `office`, `status`,
   `party`, `is_incumbent`, and `website`, `facebook_url` or `instagram_url` only if one
   appears on a page you have already opened. No bios, entries, photos or social
   media browsing; that is later research.

9. **Articles.** Each round-up or announcement story you opened becomes an
   `<article>` linked to every candidate it names, with a one- or two-sentence
   neutral `<summary>`.

10. **Notes.** In each candidate's `<notes>`, list where they were found (Elections BC, party, article URLs).

11. **Write the file and hand it off.** Save to the app repo's
    `database/elections/` folder as `bc-provincial-candidates-<YYYY-MM-DD>.xml`
    with the sequence number, containing only `<candidates>`, `<articles>`
    and `<events>`, and no `<entries>`. The root must be
    `<election id="bc-provincial-salmon-arm-shuswap">`. Then summarize:
    - A table with one row per candidate: name | party | on Elections BC |
      party page | press | status | sitting MLA.
    - Flags: names in the news but not on the official list, spelling
      differences, an MLA not running again, boundary or riding-name changes,
      and anything you couldn't open.
    - Events added.

    **Saving and importing:** follow "Saving and importing" in
    `../research-salmon-arm-candidates/references/elections-xml-schema.md`:
    write the file into the app repo's `database/elections/` folder with the
    next sequence number, then load it with
    `php artisan elections:import-pending` (dry run first).
