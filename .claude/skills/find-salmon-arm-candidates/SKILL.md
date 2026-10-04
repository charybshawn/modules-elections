---
name: find-salmon-arm-candidates
description: One-time setup for the cultpantry admin panel's Elections module (modules-elections) that finds everyone running for mayor, council and the Salmon Arm-area School District 83 trustee seats in the Salmon Arm (BC) 2026 local election and writes a ready-to-import XML roster (name, office, status, incumbency, campaign links, the round-up articles that name them, and the voting-day events). Use this when the user asks who's running, wants the candidate list or the election hopefuls, or wants to set up or start the Salmon Arm election tracker, even if they don't mention XML, the import or this skill by name. Researching what candidates say or stand for (platforms, statements, Q&A, records) belongs to research-salmon-arm-candidates, which needs this roster imported first.
---

# Find Salmon Arm candidates

Builds the list of everyone seeking election in the Salmon Arm 2026 general
local election (voting day Saturday, October 17, 2026; mayor + six
councillors, plus the School District 83 trustees elected by Salmon Arm
voters) and writes it as an XML file for the Elections module's **Import
XML** button. This is the preliminary step. It runs once, the user imports the
file, and `research-salmon-arm-candidates` then researches each candidate on
file. It writes an import file and loads it into the local database with `php artisan elections:import` (dry run first).

**Read `../research-salmon-arm-candidates/references/elections-xml-schema.md`
before writing any output.** It has the field list, the controlled value lists
and how the import matches on exact `<name>`.

**Blocked pages:** when curl or WebFetch is blocked (a 403, a Cloudflare challenge, or a page that's empty without JavaScript), read the page in the Chrome extension instead. Never fall back on a search-result summary.

## Rules

1. **Every name comes from a page you actually opened.** Search results only
   tell you which pages to open. Never put a name on the roster from a search
   summary; open the page and confirm the person is running for mayor or
   council in Salmon Arm, or for a School District 83 (North Okanagan-Shuswap)
   trustee seat that Salmon Arm voters elect (not Sicamous council or the CSRD).
2. **Neutral and factual.** Record who is running and where it's stated,
   nothing about their merits.
3. **Private individuals stay out**, and nothing about any candidate's family,
   health, address or private life.
4. **Flag, don't resolve.** When sources disagree (a name spelled two ways,
   someone announced but missing from the official list), say so in `<notes>`
   and the summary, and let the user decide.

## Workflow

1. **Check what's on file** (read-only):

   ```
   cd /Users/shawn/Documents/code/cultpantry && php artisan tinker --execute="echo json_encode(['candidates' => \Cultpantry\Elections\Models\Candidate::all(['name', 'office', 'status', 'is_incumbent']), 'events' => \Cultpantry\Elections\Models\ElectionEvent::all(['title', 'starts_at'])]);"
   ```

   If candidates are already on file, the roster has been imported before.
   Tell the user, and do a reconcile-only run: report names that are new or
   missing and status changes (withdrawals), and write only those. Spell
   every returning candidate exactly as on file.

2. **Web search** for the race ("Salmon Arm 2026 municipal election
   candidates", "Salmon Arm council candidates 2026", "Salmon Arm mayor
   candidates 2026") to find the pages to open in the next steps.

3. **City of Salmon Arm** (salmonarm.ca), the authority on status:
   - The official list of candidates (the nominations or "candidates" page,
     or the declaration of candidates). Everyone on it is `nominated`.
   - Withdrawals the City lists → `withdrawn`.
   - Acclamation: if a race has no more candidates than seats (for example,
     a single mayoral candidate), the City declares them elected by
     acclamation. Note it in the summary and in that candidate's `<notes>`.
   - The current Mayor & Council page: only someone holding a seat now is
     `is_incumbent` = `true`.
   - Voting days, advance voting days (times and places) and any remaining
     deadlines, each as an `<event>` with the City page as `<url>`.

4. **Salmon Arm Observer** (`https://www.saobserver.net/`): candidate
   announcements and "who's running" round-ups.

5. **Castanet** (`castanet.net`): its Salmon Arm/Shuswap election coverage.

6. **Reconcile** every name into one list:
   - On the City's list → `nominated`. Announced in the news but not on the
     City's list → `declared`, and flag it ("announced but not on the
     official list; may not have filed or may have withdrawn").
   - Use the City's spelling as `<name>`, since the import matches on it
     exactly. Put other spellings found in the news in `<notes>`.
   - Mayor, council and school trustees. Trustees use `<office>trustee</office>`.
     The authority for trustee candidates is School District 83's own election
     page (sd83.bc.ca), which runs its own nominations; say in `<notes>` which
     trustee electoral area each one is running in, and record the number of
     seats for that area in the summary. Trustees running only in areas
     Salmon Arm voters don't vote in are out of scope.
   - Regional district (CSRD) candidates are out of scope.

7. **Light fields only.** For each candidate: `name`, `office`, `status`,
   `is_incumbent`, and `website`, `facebook_url` or `instagram_url` only if
   one appears on a page you've already opened (a City listing or a news
   story). No bios, entries, photos or social media browsing; that's the
   research skill's job.

8. **Articles.** Each round-up or announcement story you opened becomes an
   `<article>` linked to every candidate it names, with a one- or
   two-sentence neutral `<summary>`.

9. **Notes.** In each candidate's `<notes>`, list where they were found: the
   City list URL and the article URLs.

10. **Write the file and hand it off.** Save to `~/Documents/election-research/`
    (create it if needed) as `salmon-arm-candidates-<YYYY-MM-DD>.xml`, with only
    `<candidates>`, `<articles>` and `<events>` and no `<entries>`. Then
    summarize:
    - A table with one row per candidate: name | office | on City list |
      Observer | Castanet | status | incumbent.
    - Flags: names in the news but not on the City list, spelling
      differences, withdrawals, acclamations, and anything you couldn't open.
    - Events added.

    **Importing (user's standing instruction, Oct. 3, 2026):** load the file yourself with the
    artisan command instead of handing it over. From `/Users/shawn/Documents/code/cultpantry`:
    1. Back up the local database: copy `database/database.sqlite` into the scratchpad.
    2. `php artisan elections:import <file> --dry-run`, and fix every problem it reports.
    3. `php artisan elections:import <file>`, then report the summary in the hand-off.
    
    This is for the local database only. Never import into staging or production unless the user
    asks for that specifically.
