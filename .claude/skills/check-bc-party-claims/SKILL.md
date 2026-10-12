---
name: check-bc-party-claims
description: Build and keep current the provincial party pages for the cultpantry admin panel's Elections module (modules-elections) -- each BC party's profile, a short fact-check of its headline claims and pledges against recorded votes, the government record, past statements and independent reporting, and party-level news -- as XML the module imports. Use this when the user asks to fact-check a BC party or its platform, check what a party says against the record, add or refresh a party page, update a party's leader or seat situation, or file party news, even if they don't mention XML or this skill by name. Provincial only; a candidate's own news and background belong to backfill-bc-mla-backgrounds and the research skills.
---

# Check a BC party's claims

Writes `<party>` files for the BC provincial election
(`<election id="bc-provincial-salmon-arm-shuswap">`): a dated **profile**, **3-5 headline checks**,
and **party-level news**. It never writes to the database; run `php artisan elections:import-pending`
in `/Users/shawn/Documents/code/cultpantry` (add `--dry-run` first) as each party is finished.
Format: "Party profiles" in `../research-salmon-arm-candidates/references/elections-xml-schema.md`.

## Ground rules

1. **Open every source.** A search result is a lead, not a source. The search tool is US-indexed and
   returns old and wrong pages (it surfaced a 2024 article as 2026): check the date on the page.
   Blocked pages (Castanet, Rentals.ca): the Wayback copy, `web.archive.org/web/2026/<url>`, read with
   `../research-salmon-arm-candidates/references/playwright-fetch.cjs`.
2. **Neutral, dated, attributed.** State what the record shows; attribute opinions ("economist X told
   the Canadian Press"); never characterize motive. Everything carries `checked_on` and **must be
   re-checked before voting day** and when the news changes (leaders and caucuses have changed within days).
3. **Same effort for every party.** Same number of checks, same evidence kinds, same tone.
4. **Party-level news only** under `<news>`; candidate stories stay on the candidate.
5. A party's own site and releases are sources for *what it says*, never for whether it is true.
   Another party's claim about it is an attributed claim, not a finding.

## Choosing claims

3-5 per party, the ones a voter would hear most: the headline figure in the campaign release, a
record claim ("we created N jobs"), a claim about a rival, a costed pledge. Take them from the party's
plan page, its latest releases and the Canadian Press campaign stories. Mix `claim` (about the record)
and `pledge` (a promise). Quote the party's own wording, and cite where it says it.

## The evidence kinds (use whichever fit; most checks need two or more)

| kind | what | how |
|---|---|---|
| `votes` | recorded divisions | `scripts/party_votes.py` in `pull-bc-voting-record` (below), and the Votes and Proceedings PDF itself (`lims.leg.bc.ca/pdms/ldp/<parl><session>/votes/PDF/vYYMMDD.pdf`) |
| `government_record` | budgets, ministry releases, Statistics Canada, bills, Hansard | BC Gov News (`news.gov.bc.ca`), the Budget and Fiscal Plan PDF, `lims.leg.bc.ca` bill texts |
| `past_statement` | what the party or leader said earlier | the party's pages, releases, Hansard |
| `reporting` | independent press and research | named outlet and date; a wire story's author is the outlet |

Useful no-browser data: **Statistics Canada** tables by CSV
(`https://www150.statcan.gc.ca/t1/tbl1/en/dtl!downloadDbLoadingData-nonTraduit.action?pid=<table>&latestN=0&startDate=YYYYMMDD&endDate=YYYYMMDD&csvLocale=en&selectedMembers=<JSON of member ids per dimension>`;
get member ids from `https://www150.statcan.gc.ca/t1/wds/rest/getCubeMetadata`, POST `[{"productId":14100287}]`). The
CSV has embedded commas: parse with `csv`, take the value from the end of the row.

### Party votes

```
python3 -I .claude/skills/pull-bc-voting-record/scripts/party_votes.py <work-dir> --search "regex"
                                                                       --date YYYY-MM-DD
                                                                       --summary
```
`<work-dir>` is `~/.cache/cultpantry-elections/work/parties` (every division of the 43rd Parliament plus the
roster). Parties are as *elected*, so Conservatives include MLAs who later left for OneBC, CentreBC or
sat as independents; say so. Only recorded divisions have names (voice votes don't). Context lines show
what came *before* the division: match by tally and names. A bill's first reading is not a division.

## Ratings

| rating | means |
|---|---|
| `holds_up` | the facts as stated are supported by the record |
| `mostly_holds_up` | right in substance; a figure is rounded, one-sided, or its source is the party's own government |
| `mixed` | part supported, part not, or true but misleading without context the claim leaves out |
| `mostly_does_not` | the central point is contradicted; a small part is right |
| `does_not_hold_up` | contradicted by the record |
| `untestable` | a pledge or a prediction, or the evidence can't be had; say what the record shows meanwhile |

Pledges are always `untestable`; the summary says what the party's record and the arithmetic show
around it. Don't rate a claim by whether you like the policy. If the figure comes from the government
being praised, say so and rate no higher than `mostly_holds_up` without an independent source.

## Writing a file

One file per step, numbered in `cultpantry/database/elections/` as
`2026-10-12-NN-bc-provincial-party-<what>.xml`:

- **Profile** (new party needs `<name>` and `<as_of>`): leader and since when, how long it has existed,
  times in government, principles, platform points, sources. A correction can be only the changed fields.
  Do not state a seat count unless a source dated this week gives one.
- **Province-wide slate** (`<slate>` under the party): from Elections BC's final list,
  `https://elections.bc.ca/2026-provincial-election/candidate-list/` is the page, and the CSV is linked from it
  (`https://elections.bc.ca/2026election/2026-provincial-general-election-candidates.csv`, Windows-1252).
  Use only riding, ballot name and affiliation; the CSV also holds financial agents' addresses and phones, which stay out.
  Mark a sitting MLA running again with `<incumbent_riding>` (the seat held now): match the Legislature roster
  (`~/.cache/cultpantry-elections/work/parties/ctx/roster.json`) by full name, and by riding plus surname only where
  the ballot name differs, checked by hand. A retiring MLA is not an incumbent candidate.
  It is the whole list, so re-import it after any withdrawal or replacement (nominations closed Oct. 3, 2026).
- **Checks + news**: `<claims>` and `<news>` under the same `<party><name>`.
- Summaries: lead with the verdict, then the figures, then what the claim leaves out. Name the
  arithmetic when it is yours ("my arithmetic from the party's figures"). Keep each to a short paragraph.

## Polls and riding projections

Refresh these on the same cadence as the profiles (the numbers move with every poll; re-pull before October 24).

- **Polls**: `https://338canada.com/bc/polls.htm` lists every BC poll (columns: NDP, Conservative, Green, OneBC, CentreBC; dates are the model's, mid-field). Open each pollster's own release for the exact dates, sample, method and figures (Liaison: `press.liaisonstrategies.ca`; Angus Reid: `angusreid.org`; Ipsos: `ipsos.com/en-ca`; Léger, Research Co., Mainstreet, Innovative: their sites or press coverage). Where only the 338Canada table could be read, say so in `<note>`. Write only polls since the campaign began; the dashboard decides which are current.
- **Projections**: 338Canada has one page per riding (`https://338canada.com/bc/<id>e.htm`, the ids are linked from `https://338canada.com/bc/districts.htm`). It blocks plain fetches; read each page in a browser at a polite pace (about 3 seconds apart; 93 pages take about 8 minutes) and parse the "Projection" table (party code, share, margin) and the rating line above "Projection — <riding>". Party codes: CPBC, NDP, BCG, ONE, CEN, IND; BCU (the old BC United) is dropped. Check every riding name against the Elections BC list.
- Both are **modelled or published figures, not findings of ours**: label them, date them, never present a projection as a poll.

## Workflow per party

1. Read the party's plan page and latest releases; list candidate claims.
2. Pick 3-5; for each, open the source behind the party's number and one independent one.
3. Run `party_votes.py` when the claim touches the Legislature.
4. Write the file; dry-run the import; import; confirm the counts.
5. Move to the next party. Finish by running the module tests and the page build (see the repo notes).

## Hand-off

Tell the user the count per party and rating, which claims rest only on the party's own government or
the party's own estimate, anything you couldn't open, and what to re-check before October 24.
