---
name: backfill-bc-legislature-votes
description: Backfill how a sitting BC MLA voted -- every recorded division in the Legislative Assembly's published Votes and Proceedings -- for the cultpantry admin panel's Elections module (modules-elections), as structured, subject-tagged vote records that the provincial candidate page shows by year on its Voting record tab. Use this when the user asks for an MLA's or incumbent's voting record, how they voted on bills, motions or amendments, or to refresh or extend it after new sittings, even if they don't mention XML or this skill by name. Provincial only: council and board votes are a different record and are not covered. Who the MLA is belongs to find-bc-provincial-candidates; their wider background to backfill-bc-mla-backgrounds.
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

## First run (Oct. 2026): David L Williams

110 sitting days (43rd Parliament, 1st and 2nd sessions, Feb. 2025 to May 2026), 183
recorded divisions, 143 in which the member is listed (82 yea, 61 nay), 40 where he is
not listed. 17 divisions were unanimous (a `Yeas` block only). The 10 untitled and
about a dozen procedural items were titled by hand from the raw record.
