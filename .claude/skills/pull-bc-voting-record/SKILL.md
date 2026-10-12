---
name: pull-bc-voting-record
description: Pull, and keep current, how a BC MLA voted -- every recorded division in the Legislative Assembly's published Votes and Proceedings -- for the cultpantry admin panel's Elections module (modules-elections), as structured, subject-tagged records the provincial candidate page shows on its Voting record tab, one entry per bill with two AI-written synopses (what the bill does; why the member voted as they did and how it ended) and a card of what they introduced themselves. It checks for new sittings first, and only does a full backfill the first time a candidate is run. Use this when the user asks for an MLA's or incumbent's voting record, how they voted on bills, motions or amendments, to add a candidate's record, or to refresh or check for new votes, even if they don't mention XML or this skill by name. Provincial only: council and board votes are a different record and are not covered. Who the MLA is belongs to find-bc-provincial-candidates; their wider background to backfill-bc-mla-backgrounds.
---

# Pull a BC MLA's voting record

For a candidate who sat in the BC Legislative Assembly, reads the published **Votes and
Proceedings** (the official minutes of each sitting day), finds every **recorded division**
the member is named in, and writes one `<vote>` per division. It also writes, per bill, two
AI synopses grounded in the bill, the debate and the party-by-party vote, and keeps the
"Bills and motions they introduced" card current. The provincial candidate page shows all of
it on the **Voting record** tab.

It reads public records only and writes import files; the user reviews and imports them.
Provincial only; it never touches the municipal election.

**Read `../research-salmon-arm-candidates/references/elections-xml-schema.md`** for the file
shapes ("Which election", "Votes and bill notes"). The root is always
`<election id="bc-provincial-salmon-arm-shuswap">`.

## The default: new sittings first

```
python3 -I scripts/refresh.py status                       # where each candidate stands
python3 -I scripts/refresh.py run <slug>                   # the normal call
```

- **Never run before** (no `candidates/<slug>/state.json`): a **full backfill**, every sitting
  and every bill the member voted on. Slow once; it fills the shared cache.
- **Run before:** one cached request for the list of sittings. If nothing is newer than
  `state.last_sitting` it says **"Nothing new"** and stops (a few seconds, almost no tokens).
  Otherwise it reads **only the new sittings**, writes only the new votes, and prepares a
  digest for only the bills that gained votes or still lack a synopsis. **Past sittings are
  not looked at again.**
- `--full` redoes everything (from the cache, so it is cheap); `--since YYYY-MM-DD` overrides
  the starting date; `--dry-run` writes nothing to the import folder and leaves state alone;
  `--accept-flags` proceeds past rows the check flagged (below).

Do not run a full pass for a candidate who has state unless the user asks.

## Workflow (what to do when asked to pull or refresh a record)

1. **`refresh.py run <slug>`.** Read its summary. "Nothing new" ends the job: tell the user
   the date it is current through.
2. **If it stops with flagged rows** (exit 2): a derived row is probably mislabelled (a
   reading stage whose official wording is a different question, a title missing, "the debate
   resumed on the main motion" after an amendment). Open the sitting's `v.txt` in the work
   folder, read the wording, add an override in `candidates/<slug>/overrides.json` (key it on
   `date`, `vote`, `for`, `against`, `starts`; set `title`, and `bill`, `bill_title`, `stage`
   where it belongs to a bill or motion), and re-run. A re-run is cheap (the cache).
3. **Import the votes.** The run wrote `database/elections/YYYY-MM-DD-NN-bc-provincial-voting-record-<slug>.xml`
   in the host app. Back up the database, then `php artisan elections:import-pending --dry-run`,
   fix every problem, then `php artisan elections:import-pending`.
4. **Write the synopses** for the bills in `digest.md` (the run prints its path; it holds only
   the bills that need one, each with the bill's note, the sponsor's opening, the votes with
   party splits, the member's own words, and the earlier synopsis if one is being updated).
   Write `{bill_key: {purpose, vote_analysis, basis}}` for exactly those bills (rules below).
5. **`refresh.py finish <slug> <synopses.json>`.** It refuses a file that doesn't cover exactly
   the digest's bills, merges into `candidates/<slug>/synopses.json`, writes the bill-synopsis
   import file, writes a sponsored-items file only if something introduced is new, clears
   `pending`. Import those files the same way.
6. **Hand-off** (below).

State is saved as it goes: `last_sitting` advances when the votes file is written, and the
bills still owed a synopsis stay in `pending`, so an interrupted run resumes without
re-reading sittings.

## Add a candidate

Create `candidates/<slug>/config.json`:

```json
{
 "name": "David L Williams",            // exactly as on file in the Elections module
 "slug": "david-l-williams",
 "surname": "Williams",                 // as printed in the Votes and Proceedings (bare surname)
 "sponsor_name": "David Williams",      // as the bill record names the sponsor (memberAlias)
 "index_name": "Williams, David",       // as the Hansard Members Index lists them (start of the entry)
 "speaker": "David Williams",           // as Hansard prints the speaker
 "election": "bc-provincial-salmon-arm-shuswap",
 "parliament": "43rd",
 "sessions": { "43rd1st": {"progress_id": 173}, "43rd2nd": {"progress_id": 206} }
}
```

- Check the surname is unique in the Parliament (a shared surname prints as `K. Neufeld`).
- `progress_id` is the number in the bills page's request
  `https://api.lims.leg.bc.ca/pdms/bills/progress-of-bills/<id>`; find it by loading the
  session's bills page with `../research-salmon-arm-candidates/references/playwright-fetch.cjs`
  and reading the network log. When a new session opens, `run` says so; add it with its id.
- `overrides.json` starts as `{"overrides": []}`. A candidate who is already backfilled by
  other means can be marked with `refresh.py adopt <slug> --last-sitting YYYY-MM-DD`.

## The cache

Every script reads public pages through `scripts/cache.py`: `~/.cache/cultpantry-elections/legislature`
(`$ELECTIONS_CACHE` to move it), never committed. Votes and Proceedings, final Hansard and a
bill's first-reading text never change, so they are kept for good; the lists that grow
(sittings, bills, debates, Members Index, roster) are re-checked after a few hours. It is shared
by every candidate, so a second MLA of the same Parliament needs almost no downloads, and it
survives between sessions. `python3 -I scripts/cache.py stats` / `clear [--older-than DAYS]`.
Work files (`<cache>/../work/<slug>/`: the parsed sittings, `dossier.json`, `digest.md`) are
disposable.

## What the record does and doesn't say (read before trusting a number)

1. **Only recorded divisions have names.** Most votes are voice votes and leave no names, so a
   member's record here is *the divisions*, not every vote. Say that in the hand-off.
2. **"Not listed" is not "absent."** A division prints only the members who voted. A committee
   section lists only its own members, so a member not listed may simply have been in another
   section. **Never write "absent" or "did not vote"; write nothing for a division the member
   isn't in.**
3. **Names are bare surnames** unless two members share one.
4. **The printed counts are the check.** Each `Yeas — N` / `Nays — N` header states the count;
   `parse.py` must produce exactly that many names. It reports mismatches; don't import if any
   remain.
5. **Quote the record, don't interpret it.** `description` is the Votes and Proceedings' own
   wording. Titles are mechanical. Opposition amendments, time-allocation and deferral motions
   are real recorded votes; label them for what they are.

## One entry per bill, and per motion

Each vote carries `bill_key` (`43rd1st:Bill 7`, unique within a session because numbers restart
every session), `bill_title` and a `stage`. The page puts a bill's own stages (`first_reading`,
`second_reading`, `committal`, `report_stage`, `third_reading`) up front and folds `amendment`,
`clause` and `procedural` votes under an expandable. Votes with no bill (adjourn, leave the Chair,
a time-allocation motion across several bills) stand alone. `build_xml.py` derives all three from
the title; set them in overrides (`"bill": "Bill 9", "stage": "procedural"`) for a procedural
motion about one bill.

A numbered motion with amendments (Motion 13, Motion M 205) groups the same way: set `bill`
(`"Motion M 205"`), `bill_title` (`"Motion M 205: <what it asks>"`) and `stage` (`motion` for
the main question, `amendment` for amendments and subamendments) on each division, and name the
mover in the title. Read the motion's own wording in the Votes and Proceedings.

## Per-bill synopses

Two AI-written paragraphs per bill, labelled as such, with the sources they rest on (bill text,
the Hansard issues used, the Votes and Proceedings: assembled for you):

1. **What the bill does**, from the bill's own words (Explanatory Note, the sponsor's
   introduction), not a headline.
2. **Why the member voted as they did, and how the question ended.**

`basis` says what the "why" rests on: `spoke` (their own words in debate explain the vote,
including remarks on a related bill that state the objection), `caucus` (the reason is another
caucus member's stated position in the same debate), `record` (only the votes and the bill).
The page prints it, so never claim more than you have.

- **Their own speech is never filtered.** What a member says on the Hansard record is directly
  theirs, so personal matters they chose to say in debate belong in the "why" when they are
  the reason they gave. This is the one exception to the family line in the research-sensitivity
  rules, which stay in force for what others say or research finds.
- **Do not invent motives.** If they did not speak, say so ("The member did not speak on the bill,
  so their reasons are not on the record") and state what the record shows: the pattern of votes
  (amendments by their caucus, a unanimous stage, a caucus split). Say plainly when the record
  does not explain a change (a speech of support followed by a nay at third reading).
- **Explain the result from the numbers.** Use the party split in the digest: which party carried
  or defeated it, who crossed, how close it was. A tied division carries only because the Speaker
  breaks ties; a tied committee vote is decided by the chair. A private member's bill that passes
  is unusual; say when it became law and when it went no further.
- **Check the stage labels first.** Reading-stage rows decide what you write ("voted at second
  reading"). The check in `run` flags the usual mislabels; a flagged row is never ignored.
- **Neutral wording.** Attribute a sponsor's or member's characterizations to them ("she said",
  "per her introduction"); describe what a bill does without adopting its framing. About 50-60
  words for the purpose, 70-110 for the analysis; quote at most a few words. Refer to the member
  as their own sources do, or by name; never guess a pronoun.
- **Party labels.** `elected_as` is the party a member was elected under; many have since left
  caucus. Say "the Conservatives" for the caucus as elected, and note a departure where it matters.
- A bill with no record (an opposition bill that stopped at first reading has no number or
  Explanatory Note): the mover's introduction from Hansard is the source; if there is nothing,
  say its content cannot be described beyond its title.

## Bills and motions they introduced

A separate record (`<sponsored>`), not votes: introducing a bill usually has no division. The
Legislature records **one sponsor per bill; there are no co-sponsors**, so none are counted.
`finish` runs `sponsored.py`, which reads the sponsor from the bill list and the member's private
members' motions from the Votes and Proceedings, sets how far each got, and adds a context line
(how many MLAs introduced a private member's bill this Parliament; how many became law). Motions
that merely adjourn a debate are not motions they introduced. A motion is recorded as "moved";
check later sittings and correct to agreed or defeated.

## Tagging by subject

`build_xml.py` maps bill titles to subject tags by keyword (`RULES`), reusing existing tags where
they fit and defining the provincial ones it needs. Review the tags; add rules or give an
override's `tags` when a bill doesn't match. A tag used only by provincial votes is hidden from
the municipal subject views automatically.

## Under the hood

`refresh.py` calls these in order; each can be run alone (`python3 -I scripts/<name>.py`, docstring
has the arguments): `fetch.py` (sittings, PDFs via the cache) -> `parse.py` (divisions, count-checked)
-> `describe.py` (wording, bill, stage, title) -> `build_xml.py` (votes, `--since` for new only) ->
`roster.py`, `bill_context.py`, `sponsor_speech.py`, `hansard.py`, `intro_statement.py` -> `dossier.py`
(the facts per bill) -> `build_notes_xml.py` (synopsis import file) and `sponsored.py`.

## Hand-off summary

- Mode (first run or incremental), the sittings read (first and last dates) and the date the
  record is now current through; or "Nothing new".
- New votes: how many, yea / nay, on how many bills and motions; divisions the member is **not
  listed in** (not absence); that voice votes are not in the record.
- Count check: divisions parsed vs `Yeas` headers, mismatches (must be 0).
- Rows flagged and the overrides added, and tags applied.
- Synopses written: how many rest on `spoke`, `caucus`, `record`; any bill with no source text.
- Files to import, in order: votes, synopses, sponsored.
