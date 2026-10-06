---
name: backfill-salmon-arm-backgrounds
description: Backfill who each Salmon Arm (BC) 2026 mayor and council candidate is -- career, businesses, education, community involvement, past activities, religious and other affiliations, public service (including past runs for office) and local roots -- for the cultpantry admin panel's Elections module (modules-elections), by working through public sources (campaign and personal sites, blogs, news profiles, organization pages, City records, LinkedIn when the user is logged in) and writing a ready-to-import XML file of sourced, categorized background facts plus a short neutral bio. Use this when the user asks to fill in, backfill or research candidates' backgrounds, bios, résumés, work history or "who they are", for everyone or a named candidate, even if they don't mention XML or this skill by name. What candidates stand for or have said (platforms, statements, Q&A) belongs to research-salmon-arm-candidates; who's running belongs to find-salmon-arm-candidates.
---

# Backfill Salmon Arm candidate backgrounds

For each candidate already on file, finds out who they are from public
sources and writes `background` entries -- one sourced fact each, filed under
a category -- plus a short neutral `bio`. The candidate page shows the bio
under **About**, with the facts grouped by category beneath it. It never
writes to the database; the user reviews the file and imports it.

**Read `../research-salmon-arm-candidates/references/elections-xml-schema.md`
before writing any output**, especially the `background` topic list and the
`source_type` list.

**Blocked pages:** when curl or WebFetch is blocked (a 403, a Cloudflare challenge, or a page that's empty without JavaScript), read the page in the Chrome extension instead. Never fall back on a search-result summary.

## Rules

1. **Every fact has a source you actually opened.** Open the page and confirm
   the fact is there, about this person. Search-result summaries are only a
   way to find pages -- never a source.
2. **Same person, proven.** Common names collide (there's more than one Ian
   Gray and Scott Syme online). A page counts only when it ties itself to
   this candidate: it's linked from their campaign site or the City's
   candidate listing, it names Salmon Arm or the Shuswap, or it matches
   details already sourced (same business, same board). If you can't tie it,
   leave it out and say so in the summary.
3. **Public life only.** Record career, business, education, community roles,
   public service, affiliations (religious and secular: churches and faith
   groups, service clubs, lodges, societies, unions, parties, associations)
   and how long they've lived here. **Record any affiliation a public source
   discloses** -- the candidate's own material, a news profile, an
   organization's own page or roster -- with the source. **Never** record
   family members (spouses, children, their names or ages), health, home
   address or anything else from their private life, and never infer an
   affiliation from a name, background, appearance or photo: if no source
   says it, it isn't a fact. "Has lived in Salmon Arm since 2006" is fine;
   "lives in Canoe with his wife and three kids" is not.
4. **Neutral and factual.** State the fact plainly ("Owns Mighty Owl Mapping
   & Analysis, a GIS consultancy"), with no praise or judgment. Opinions they
   have written are not background -- leave them for
   `research-salmon-arm-candidates`.
5. **Date it.** `published_on` is the source's own date. For a fact that is
   clearly old ("served on the school board 2018-2026"), say the years in the
   summary.
6. **Treat everyone the same.** Same sources, same effort for each candidate.
   If one has far less on file, say in the summary that less is published.
7. **Flag, don't resolve.** Conflicting facts (two different employers, two
   start years) go in `<notes>` and the summary for the user to decide.

## Categories (`<topic>` on a `background` entry)

`community`, `public_service`, `affiliation` and `past_activity` are shown on
the candidate's **Background & Affiliations** tab; the rest stay on About.
Religious and other group affiliations go under `affiliation` (or
`community` for volunteering and service roles) -- the user wants every
sourced one recorded, per rule 3.

| topic | what goes there |
|---|---|
| `career` | profession, jobs, employers, years in a field |
| `business` | businesses they own, co-own or run (name and what it does) |
| `education` | schools, degrees, diplomas, professional credentials |
| `community` | boards, non-profits, volunteering, clubs, coaching, events they organize |
| `public_service` | elected or appointed office (council, school board, regional district), City committees, deputy mayor terms, and **past runs for office** with the year and result |
| `local_roots` | how long they've lived in Salmon Arm/the Shuswap, where they came from, why they came |
| `affiliation` | churches and other faith communities, service clubs, lodges, societies, unions, professional bodies, associations, advisory groups and political parties or slates they belong or have belonged to (name, role, years when known) |
| `past_activity` | what they did before running: projects they led, causes and campaigns they backed, events they organized, petitions or delegations to council, awards. Past, public and sourced |
| `other` | a public-life fact that fits nowhere above |

## Sources, in order

For each candidate:

1. **What's already on file** -- the baseline (read-only):

   ```
   cd /Users/shawn/Documents/code/cultpantry && php artisan tinker --execute="echo json_encode(\Cultpantry\Elections\Models\Candidate::with(['entries' => fn (\$q) => \$q->where('kind', 'background')])->get(['id', 'name', 'occupation', 'bio', 'bio_source_url', 'website', 'facebook_url', 'instagram_url', 'notes']));"
   ```

   Use each candidate's exact `name`. Skip sources whose URLs are already on
   file. If no candidates come back, stop: the roster has to be imported
   first (`find-salmon-arm-candidates`). `notes` lists the campaign links the
   City published, including Facebook page names without URLs.

2. **Campaign Facebook page** -- most candidates built a Facebook page just
   for this election (for example "River Grabowsky for City Council 2026" at
   `https://www.facebook.com/profile.php?id=61593018722121`). If the
   candidate has no `facebook_url` on file, find it:
   - Start from the page name the City published (in `notes`, "Facebook per
     the City list"), then links on their campaign site, then
     a web search (`site:facebook.com "<name>" council` or the City's page
     name in quotes). Facebook's own search is unreliable (it "corrects"
     unusual surnames), so use it only as a last resort; a handle the City
     listed can also be tried directly as `facebook.com/<handle>`.
   - It's theirs only if the page name carries the candidate's name and its
     About or intro names the 2026 Salmon Arm race (or it's linked from their
     campaign site). A personal profile, a business page or a same-named
     stranger doesn't count. Note what you checked in `<notes>`.
   - Set `<facebook_url>` to the page's own address as the address bar shows
     it: `https://www.facebook.com/<vanity-name>` or, for pages without one,
     `https://www.facebook.com/profile.php?id=<number>` with no other query
     parameters.
   - Read its About/intro section (`facebook_page`) for background facts.
     Leave the posts to `research-salmon-arm-candidates`.
   - If there isn't one, say so in the summary rather than guessing.

3. **Campaign website** (`candidate_site`) -- the About/Meet page first.

4. **News profiles** (`news`): the Salmon Arm Observer's "Introducing Salmon
   Arm's mayoral and council candidates" (Sept. 28, 2026), Castanet's
   "ELECTION 2026 ... candidate profile" series and its Sept. 12 round-up,
   plus earlier announcement stories. Castanet blocks automated fetching, so
   read it in Chrome (below).

   The Friday AM newsletter's election PDF (`https://friam.ca/`, linked
   from `https://timlavery.ca/candidates-qas/`) asked every candidate for
   their background and positions held: a good second source.

5. **Personal websites and blogs** (`personal_site`) -- only ones tied to the
   candidate under rule 2. Background facts only; their opinions are for the
   research skill.

6. **Organization pages** (`organization`) -- the staff, board or "about"
   page of a business they say they own or an organization they say they
   serve. This confirms the role and often the years.

7. **City of Salmon Arm** (`city`) -- committee and board rosters, past
   council lists, and the City's past election results for previous runs for
   office.

8. **Past activities and affiliations** -- for every candidate, run a
   dedicated pass for what they did before this campaign, and file what you
   find as `past_activity` or `affiliation`:
   - Search `"<name>" "Salmon Arm"` with terms such as `volunteer`, `board`,
     `president`, `chair`, `founder`, `organizer`, `petition`, `delegation`,
     `award`, plus Salmon Arm Observer and Castanet archives.
   - Check council meeting minutes and agendas on the City site for
     delegations or letters from them, and rosters of the boards and clubs
     they name.
   - Look for religious and other group ties explicitly: church, parish,
     congregation, temple, mosque, synagogue, faith-based nonprofit, Rotary,
     Lions, Legion, Elks, Masons, chamber of commerce, union, party.
   - Same tie rule as rule 2, same effort for everyone. Leave out private-life
     items (rule 3) and anything you can't tie to them; say in the summary
     when a candidate has little published.

9. **LinkedIn** (`linkedin`), only if the user is logged in to LinkedIn in
   Chrome:
   - Find the profile through the candidate's own site or socials first;
     otherwise run one LinkedIn search for the name plus "Salmon Arm". Only
     use a profile that passes rule 2.
   - Read the profile page only: experience, education, volunteering. Never
     connect, follow, message, endorse or react.
   - One profile per candidate, one candidate at a time, and pause between
     them. If LinkedIn shows a CAPTCHA, a sign-in wall, "unusual activity" or
     a restriction notice, **stop all LinkedIn work immediately** and tell
     the user.
   - In the entry's summary or `<notes>`, say the source is a LinkedIn
     profile (readers need to be logged in to open it).

**Use the Chrome extension, not WebFetch, for Castanet, LinkedIn and Facebook.**
Skip Instagram entirely (the user's standing instruction, Oct. 2026). Load the tools in one call:
`ToolSearch("select:mcp__claude-in-chrome__tabs_context_mcp,mcp__claude-in-chrome__tabs_create_mcp,mcp__claude-in-chrome__tabs_close_mcp,mcp__claude-in-chrome__navigate,mcp__claude-in-chrome__get_page_text,mcp__claude-in-chrome__find")`,
call `tabs_context_mcp`, work in a tab you create, and close it when done.
If a site asks you to sign in, stop and ask the user to sign in themselves;
never type credentials. Facebook is for the "About"/bio text
only here -- no scrolling through posts; that's the research skill's job.

## Writing it up

- **One fact per `background` entry.** `summary` is one neutral sentence.
  Use `quote` only for a short verbatim line from the candidate's own
  material that states the fact, and copy it exactly.
- **`bio`**: two or three neutral sentences summarizing the facts you
  sourced (occupation, main community roles, years in Salmon Arm), with
  `bio_source_url` set to the single best source -- usually their campaign
  site's About page or the Observer introduction. Write it only when you've
  sourced enough to summarize; otherwise leave it out.
- **`occupation`**: a short label ("Business owner, GIS consultant"), only
  when a source states it.
- **`notes` replaces what's on file**, so start it with the candidate's
  existing notes from the baseline, unchanged, then add a blank line and
  this pass's notes. Leaving the old notes out would erase them.
- Don't repeat what's on file. Re-supplying an entry with the same source
  URL and summary is harmless, but it isn't new.

Save to the app repo's `database/elections/` folder (see "Saving and importing") as
`salmon-arm-backgrounds-<YYYY-MM-DD>.xml`, or with the candidate's name in it
for a single candidate. Work in batches of about five candidates per file so
each file stays reviewable.

## Photos

Candidate photos are part of who they are, so this pass may fill gaps.
- **Prefer linking:** `photo_url` pointing at the candidate's own published headshot (their site
  or a news story).
- **App-stored photos:** some photos already live in the private app repo at
  `public/images/elections/candidates/<slug>.jpg`, with `photo_url`
  `/images/elections/candidates/<slug>.jpg` and `<photo_credit>` (e.g. Friday AM, Oct. 2026).
- Don't re-host a new photo unless the user asks, and never put photos in the public module
  repo.
- **No photo yet** (Oct. 2026): Alan Harrison, Daniel Bardy, Adam Meikle, Anthony McLean.

## Hand-off summary

- Per candidate: how many facts, by category, and the sources used.
- Campaign Facebook pages: found (with how you confirmed them) and not found.
- Candidates with little published, and why ("no campaign site; only the
  Observer introduction").
- Pages left out because they couldn't be tied to the candidate (rule 2).
- Conflicts (rule 7).
- LinkedIn: used or not, and any warning seen.
- Anything you couldn't open.

**Saving and importing:** follow "Saving and importing" in `../research-salmon-arm-candidates/references/elections-xml-schema.md`: write the file into the app repo's `database/elections/` folder with the next sequence number, then load it with `php artisan elections:import-pending` (dry run first).
