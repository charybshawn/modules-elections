---
name: backfill-bc-legislature-votes
description: Backfill how a sitting BC MLA voted -- every recorded division in the Legislative Assembly's published Votes and Proceedings -- for the cultpantry admin panel's Elections module (modules-elections), as structured, subject-tagged vote records that the provincial candidate page shows by year on its Voting record tab, one entry per bill with two AI-written synopses (what the bill does; why the member voted as they did and how it ended). Use this when the user asks for an MLA's or incumbent's voting record, how they voted on bills, motions or amendments, or to refresh or extend it after new sittings, even if they don't mention XML or this skill by name. Provincial only: council and board votes are a different record and are not covered. Who the MLA is belongs to find-bc-provincial-candidates; their wider background to backfill-bc-mla-backgrounds.
---

# Backfill BC legislature votes

For a candidate who sat in the BC Legislative Assembly, reads the published
**Votes and Proceedings** (the official minutes of each sitting day), finds every
**recorded division** the member is named in, and writes one structured `<vote>` per
division: when, what was decided, how they voted, the result and tally, the record
it came from, and subject tags. The provincial candidate page shows them on the
**Voting record** tab, grouped by year, filterable by how they voted and by subject.

It reads public records only and writes an import file; the user reviews it and
imports it. Provincial only -- the municipal election has its own pages and a
different record (council minutes), and this skill never touches it.

**Read `../research-salmon-arm-candidates/references/elections-xml-schema.md`** for
the file shape ("Which election" and the `<votes>` section). The root is always
`<election id="bc-provincial-salmon-arm-shuswap">`.

## What the record does and doesn't say (read before trusting a number)

1. **Only recorded divisions have names.** Most votes in the Assembly are voice votes
   and leave no names, so a member's record here is *the divisions*, not every vote.
   Say that in the hand-off.
2. **"Not listed" is not "absent."** A division prints only the members who voted. A
   committee section (A, B, C) lists only its own members, so a member not listed
   in a section's division may simply have been in another section. **Never write
   "absent" or "did not vote"; write nothing for a division the member isn't in.**
   The script drops them and counts them separately for the summary.
3. **Names are bare surnames** unless two members share one (then `K. Neufeld`,
   `L. Neufeld`). Check the surname is unique in the Parliament before trusting a
   bare match: the script flags any `X. Surname` variant it finds.
4. **The printed counts are the check.** Each `Yeas — N` / `Nays — N` header states
   the count; the parse must produce exactly that many names for every division.
   The scripts report mismatches and stop on them. Don't import if any remain.
5. **Quote the record, don't interpret it.** `description` is the Votes and
   Proceedings' own wording of the question. Titles are mechanical (bill, stage,
   clause, who moved it). Don't say what a vote "means" or what it says about
   the member. Opposition amendments, time-allocation and deferral motions are
   real recorded votes; label them for what they are, not as bill stages.

## The data

- **Sitting-day index** (JSON, one call per session):
  `https://api.lims.leg.bc.ca/pdms/votes-and-proceedings/<parliament><session>`
  e.g. `43rd1st`, `43rd2nd` -> `allParliamentaryFileAttributes.nodes[]` with
  `fileName` (`v250415.htm`), `date`, `votesAttributesByFileId.nodes[0].voteNumbers`.
  The public page `leg.bc.ca/parliamentary-business/overview/<parl>-parliament/<n>-session/votes-and-proceedings`
  just embeds this; its HTML has no document links.
- **Each sitting day's PDF:**
  `https://lims.leg.bc.ca/pdms/ldp/<parliament><session>/votes/PDF/<fileName>.pdf`
  (the `.htm` is the same document). One request per day, paced; the first run
  fetched 110 days without trouble at ~1 request a second.
- **A division looks like** (in `pdftotext -layout` output):
  ```
  Steve Kooner moved a proposed amendment to clause 3 of Bill (No. 7) which was
  defeated on the following division:
                         Yeas — 5
  Kooner      Halford   ...     (four columns of surnames)
  \x0c2                  April 15, 2025          <- a page footer can split the blocks
                         Nays — 7
  ...
  ```
  A division may be unanimous (a `Yeas` block only), the blocks can come in either
  order, a page footer can fall between them, and the question is the paragraph(s)
  just above. Committee divisions read "the Committee divided as follows".

## Workflow

Scripts live in `scripts/` (Python 3; run with `python3 -I`; PDFs are untrusted
downloads, so each goes in its own folder and nothing is executed from it). They need
`pdftotext` (poppler). Work in a scratchpad directory.

1. **Check what's on file** (read-only): the candidate's existing votes, so a
   refresh only adds what's new.
   ```
   cd /Users/shawn/Documents/code/cultpantry && php artisan tinker --execute="echo json_encode(\Cultpantry\Elections\Models\Vote::whereHas('candidate', fn(\$q)=>\$q->where('name','<exact name>'))->orderBy('voted_on')->get(['voted_on','reference','vote']));"
   ```
2. **Fetch:** `python3 -I scripts/fetch.py <work-dir> [43rd1st 43rd2nd ...]`
   Writes `index.json` and one folder per sitting day with `v.pdf` and `v.txt`.
   Re-running skips what is already downloaded.
3. **Parse:** `python3 -I scripts/parse.py <work-dir>` -> `divisions.json`.
   Confirm `count mismatches: 0`. The number of divisions should equal the number of
   `Yeas —` headers in the text (`grep -hE "^\s*Yeas\s+[—-]\s+[0-9]+" */v.txt | wc -l`).
4. **Describe:** `python3 -I scripts/describe.py <work-dir> <Surname>` ->
   `described.json`: for each division the member is in, the official wording, the
   outcome, bill reference and name, stage and a derived title.
5. **Review every row.** Print titles beside the official wording and read them all;
   this is the step that catches errors. The derivation is mostly right and
   confidently wrong in a few places: procedural motions (time allocation,
   deferral, adjournment, "leave the Chair"), a question that follows a long
   preceding paragraph, a bill introduced in an earlier session, a clause vote after
   a ruled-out amendment. Fix those in an **overrides file** keyed on
   `date, vote, tally, start of wording`:
   `scripts/overrides-david-l-williams.json` is the worked example.
6. **Build:** `python3 -I scripts/build_xml.py <work-dir> <Surname> "<Name as on file>" <out.xml> <overrides.json>`
   Writes the vocabulary for any new subject tags and one `<vote>` per division (yea or
   nay). A "Division k of n recorded on <date>" note is added to each description so
   two identical questions on one day stay distinct records. Output goes straight into
   the host app's `database/elections/` folder as
   `YYYY-MM-DD-NN-bc-provincial-voting-record-<name>.xml`.
7. **Import:** back up the database, `php artisan elections:import-pending --dry-run`,
   fix every problem, then `php artisan elections:import-pending` (see "Saving and
   importing" in the schema doc).
8. **Write the per-bill synopses** for every bill that has votes and no note yet (see
   "Per-bill synopses" below). This is part of the job, not an extra: a bill shows on
   the page with its two synopses, so new votes are not finished until the bills
   they belong to have them. Import them the same way as the votes, as a second file.

## One entry per bill

A bill is one entry on the tab. Each vote carries `bill_key` (`43rd1st:Bill 7`, unique
within a session because bill numbers restart every session), `bill_title`
(`Bill 7: Economic Stabilization (Tariff Response) Act`) and a `stage`. The page puts
a bill's own stages (`first_reading`, `second_reading`, `committal`, `report_stage`,
`third_reading`) up front and folds `amendment`, `clause` and `procedural` votes
under an expandable "Amendments and committee votes". A bill sits in the year of its
latest vote. Votes with no bill (a motion to adjourn, a time-allocation motion across
several bills) stay as their own entries.

`build_xml.py` derives all three from the title. Set them by hand in the overrides
file (`"bill": "Bill 9", "stage": "procedural"`) for a procedural motion about a single
bill (time allocation, deferral), which has no stage in its title. A bill whose only
votes are amendments to its second-reading motion shows with an empty stage section
and the amendments under it; that is correct, not a gap.

### Bills and motions they introduced

The right column's "Bills and motions they introduced" card is a separate record (`<sponsored>`),
not votes: introducing a bill usually has no recorded division. Run it on every refresh:

```
python3 -I scripts/sponsored.py ctx <work-dir> "<Name as in the bill record>" "<Name on file>" <out.xml>
```

It reads the sponsor out of `bills-<session>.json` and the member's private members'
motions out of the Votes and Proceedings, sets how far each got (introduced, second
reading, third reading, law), and adds one line of context (how many MLAs introduced a
private member's bill this Parliament, and how many became law). The Legislature
records one sponsor per bill, so there are no co-sponsors to count; do not invent any.
A member who introduced nothing shows "None on record". Motions that merely adjourn a
debate ("On the motion of David Williams, the debate was adjourned") are not motions
they introduced. The status of a motion is set to "moved"; check the later Votes and
Proceedings and correct it to agreed or defeated.

### Motions group like bills

A numbered motion with amendments or subamendments (Motion 13, Motion M 205) is one
entry too: set `bill` (`"Motion M 205"`), `bill_title` (`"Motion M 205: <what it asks>"`)
and `stage` (`motion` for the main question, `amendment` for amendments and
subamendments) in the overrides file for each of its divisions, and name the mover in the
title. The page counts them as motions, not bills. Procedural motions that belong to no
single bill or motion (adjourn, leave the Chair, defer divisions) stay standalone.
Read the motion's own wording in the Votes and Proceedings; the division text alone
does not say what it was about, and the main question may be a voice vote or a division
the member is not listed in.

## Per-bill synopses

Each bill on the page carries two AI-written synopses, labelled as such, with the
sources they rest on:

1. **What the bill does**, from the bill's own words (its Explanatory Note and the
   sponsor's introduction), not from a headline.
2. **Why the member voted as they did, and how the question ended**: what they did at
   each stage, the reasons they gave, and why it passed or failed.

They are stored per member and bill (`<bill_notes>`; one note per `bill_key`), with a
`basis` that says what the "why" rests on, and a source list assembled for you.

### Gathering the facts

All of these are public pages; each writes JSON into one work folder (`ctx/`):

```
python3 -I scripts/roster.py ctx/roster.json                       # who sat for which party (Wikipedia table)
python3 -I scripts/bill_context.py ctx 43rd1st 173                 # bill facts + Explanatory Note (progress id: see the script)
python3 -I scripts/bill_context.py ctx 43rd2nd 206
python3 -I scripts/sponsor_speech.py ctx 43rd1st                   # the sponsor's opening speech
python3 -I scripts/sponsor_speech.py ctx 43rd2nd
python3 -I scripts/hansard.py ctx 43rd1st "Williams, David" "David Williams"   # the member's own speeches, per bill
python3 -I scripts/hansard.py ctx 43rd2nd "Williams, David" "David Williams"
python3 -I scripts/intro_statement.py ctx <votes.xml>              # the mover's statement for bills with no sponsor opening
python3 -I scripts/dossier.py <work-dir> ctx <votes.xml> <Surname> dossier.json
```

`dossier.json` is one record per bill: its facts, the member's votes with the result,
tally and **how each party voted** (`yea_by_party` / `nay_by_party`, by the party each
member was elected under), the sponsor's opening, and everything the member said
about it. Read it; write from it. If a bill has no record at all (an opposition bill
that stopped at first reading has no number or Explanatory Note), the mover's
introduction from Hansard is the source; if there is nothing, say the content cannot
be described beyond its title rather than guessing.

### Writing them

Write `synopses.json` (`{bill_key: {purpose, vote_analysis, basis}}`), then
`python3 -I scripts/build_notes_xml.py dossier.json synopses.json "<Name>" <out.xml>`,
which refuses a note with no basis or for a bill not in the dossier, builds the
source list (bill text, the Hansard issues used, the Votes and Proceedings) and writes
`YYYY-MM-DD-NN-bc-provincial-bill-synopses-<name>.xml`.

- **`basis`**: `spoke` when the member's own words in debate explain the vote
  (including remarks on a related bill that state the objection); `caucus` when the
  reason is another member of their caucus's stated position in the same debate;
  `record` when only the votes and the bill are available. The page prints it, so
  never claim a stronger basis than you have.
- **Their own speech is never filtered.** What a member says on the Hansard record is
  directly theirs, so personal or family matters they chose to say in debate belong
  in the "why" when they are the reason they gave. This is the one exception to the
  family line in the research-sensitivity rules, which stay in force for what others
  say or research finds.
- **Do not invent motives.** If they did not speak, say so ("He did not speak on the
  bill, so his reasons are not on the record") and state what the record does show:
  the pattern of votes (amendments moved by their caucus, a unanimous stage, a
  caucus split), and what the caucus did. Say plainly when the record does not
  explain a change (a speech of support followed by a nay at third reading).
- **Explain the result from the numbers.** Use `yea_by_party`/`nay_by_party`: which
  party carried or defeated it, who crossed, how close it was. A tied division
  carries only because the Speaker breaks ties (the Speaker votes only to break one);
  a tied committee vote is decided by the chair. A private member's bill that passes
  is unusual; say when it became law (Royal Assent) and when it went no further.
- **Check the stage labels first.** The reading-stage rows decide what you write
  ("voted at second reading"). The derivation mislabels a few: the main question after
  an amendment ("The debate resumed on the main motion"), a government motion to
  recommit or to send a bill to a committee, and an order-of-business motion. Fix
  them in the overrides file (`stage: "procedural"` or `second_reading`, plus `bill`),
  re-run build and the importer, and re-run `dossier.py`, before you write.
- **Neutral wording.** Attribute a sponsor's or member's characterizations to them
  ("she said", "per her introduction"); describe what a bill would do without
  adopting its framing. Keep to about 50-60 words for the purpose and 70-110 for the
  analysis; quote at most a few words.
- **Don't carry a caucus roster as fact.** `elected_as` is the party a member was
  elected under; members have since changed caucus (Wikipedia's table shows the
  changes). Say "the Conservatives" for the caucus as elected, and note a departure
  where it matters.

## Tagging by subject

Votes carry subject tags like any other record. `build_xml.py` maps bill titles to
subject tags by keyword (`RULES`), reusing existing tags where they truly fit
(`reconciliation`, `transparency`, `housing-supply`, `climate-action`,
`mental-health-addictions`, ...) and defining the provincial ones it needs
(`provincial-budget-taxes`, `energy-electricity`, `health-care`, `trade-tariffs`,
`legislature-process`, `human-rights`, ...). Review the tags in the review step; add
rules or give an override's `tags` list when a bill doesn't match. A tag used only by
provincial votes is hidden from the municipal subject views automatically.

## Hand-off summary

- Per member: how many recorded divisions, split yea / nay, by year; the span of
  sittings read (first and last dates); how many divisions they were **not listed in**
  (and that this is not absence).
- Count check: divisions parsed vs `Yeas` headers, mismatches (must be 0).
- Titles overridden by hand and why; tags applied.
- Any surname ambiguity or a division the parse could not read.
- That voice votes are not in the record.
- Synopses: how many bills got a note, how many rest on `spoke`, `caucus` and `record`,
  any bill with no source text, and any stage label corrected before writing.

## First run (Oct. 2026): David L Williams

110 sitting days (43rd Parliament, 1st and 2nd sessions, Feb. 2025 to May 2026), 183
recorded divisions, 143 in which the member is listed (82 yea, 61 nay), 40 where he is
not listed. 17 divisions were unanimous (a `Yeas` block only). The 10 untitled and
about a dozen procedural items were titled by hand from the raw record.
