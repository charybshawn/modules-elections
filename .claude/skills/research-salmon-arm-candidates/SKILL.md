---
name: research-salmon-arm-candidates
description: Research the candidates already on file in the cultpantry admin panel's Elections module (modules-elections) for the Salmon Arm (BC) 2026 municipal election (mayor and council) and write a ready-to-import XML file. It covers platform planks (ranked by the candidate's own emphasis), each plank's plan (how they'd do it, mined from new sources) and AI analysis of each pillar (what it means, why it could work, why it might not, the devil in the details; footnoted), statements and Q&A answers from media, forums and Facebook, incumbents' council record, endorsements, campaign finance, election news coverage, subject tags, third-party scorecards such as Vote4Tomorrow, withdrawals and election events. Use this whenever the user asks to research, update, refresh, backfill or look into Salmon Arm election candidates, a specific candidate, their platform, plans or promises, election coverage, or all-candidates forums, even if they don't mention XML, the import or this skill by name. Finding out who's running is find-salmon-arm-candidates, which must be run and imported first; candidates' backgrounds and bios are backfill-salmon-arm-backgrounds.
---

# Research Salmon Arm candidates

Researches the Salmon Arm 2026 general local election (voting day Saturday,
October 17, 2026; mayor + six councillors) and writes an XML file shaped for
the Elections module's **Import XML** button (`POST /admin/elections/import`,
handled by `Cultpantry\Elections\Actions\ImportElectionFromXml`). This is a
research aid that writes an import file, then loads it into the local database with
`php artisan elections:import` (dry run first; see the hand-off step).

It researches only the candidates already on file. The roster (who's running)
comes from `find-salmon-arm-candidates`, which the user runs and imports first.
Who each candidate is (career, education, community roles, the `bio`) comes
from `backfill-salmon-arm-backgrounds`; leave `background` entries and the bio
to it.

**Read `references/elections-xml-schema.md` before writing any output.** It
has the field list, the controlled value lists, and exactly how the import
matches and merges, which decides how you write a repeat run.

**Blocked pages:** when curl or WebFetch is blocked (a 403, a Cloudflare challenge, or a page that's empty without JavaScript), read the page in the Chrome extension instead. Never fall back on a search-result summary.

## The rules that matter most

This is a file of claims about real people running for public office, which
invited readers will rely on to decide how to vote. So:

1. **Every item has a source you actually opened.** Each `<entry>` needs a
   `source_url` for the page, post or comment where you read it, and the bio
   needs a `bio_source_url`. Never cite a search-result summary. Open the page
   and confirm the claim is there, about this person, in Salmon Arm. Search
   summaries in this environment have merged different people and towns.
2. **Quotes are verbatim, summaries neutral.** `<quote>` is copied character
   for character from the source, or left out. `<summary>` paraphrases what
   they said, with no adjectives about it ("bold", "vague", "controversial"),
   no interpretation of motive, and no comparison with other candidates.
3. **No inferred stances.** If a candidate hasn't said where they stand on
   something, there's no entry for it. Don't extrapolate a position from
   their job, endorsements or who they follow. "Has not stated a position on
   X" is not an entry.
4. **Treat every candidate the same way.** Search for and read each one with
   the same effort, in the same places. If one candidate has far less on file,
   say in the summary that it's because less is published, not because they
   were checked less.
5. **Private individuals stay out.** In Facebook groups and comment threads,
   capture only what candidates themselves posted or replied. When a
   candidate answers a resident's question, paraphrase the question into
   `<question>` and don't name or quote the resident. Journalists and forum
   moderators asking in their professional role can be named in the summary.
   Never record anything about a candidate's family, health, address or
   private life unless the candidate made it part of their own campaign
   material.
6. **Flag, don't resolve.** If sources conflict (two different occupations, a
   quote reported two ways), put both in `<notes>` and the summary, and let
   the user decide.

## Where the work shows

So you know what each piece is for:
- **Candidate page tabs:**
  - About: bio and background.
  - Platform: a ranked, expandable list of planks by tier. Each open plank shows a **Plan
    check** beside "What they said", then a collapsible "AI analysis" panel on pillars.
    - The plan check asks every plank the same five questions: how, paying for it, when, how
      we'd know, who with. Each shows the sourced detail, or "Not stated".
    - The row chip scores it (e.g. "Plan 2/5"), so plan `<detail>` aspects matter.
    - Tag `<detail>`s with the right aspect; `other` shows only as an "Also" line.
  - Then the scorecard tab (e.g. Vote4Tomorrow), the other statement tabs, In the news, and
    Research notes (admins only).
- **Elections dashboard:** a ranked "What's being talked about" list (subject heat from dated
  statements, articles and pulse issues), candidate cards with compare toggles, and the latest
  coverage.
- **Browse by subject:** the who-campaigns-on-what matrix and one page per tag.
- **Compare:** two or three candidates side by side.
- **Quick search (⌘K):** candidates, subjects and planks.

Thin profiles read as thin candidates, so **coverage balance is a priority**. When the user asks
for a general research pass, backfill the candidates with the least on file first: fewest planks
and statements, no Castanet or Friday AM entry, no council record for an incumbent.

## Workflow

1. **Confirm scope.** "Everyone" means a full pass. A named candidate or topic
   ("Jane's housing position", "anything new from the forum") means a
   targeted pass. Ask if it's unclear.

2. **Read the baseline from the database** (read-only), so a repeat run
   produces a diff instead of re-reporting everything:

   ```
   cd /Users/shawn/Documents/code/cultpantry && php artisan tinker --execute="echo json_encode(['candidates' => \Cultpantry\Elections\Models\Candidate::with(['entries', 'planks'])->get(), 'articles' => \Cultpantry\Elections\Models\Article::with('candidates:id,name')->get(), 'events' => \Cultpantry\Elections\Models\ElectionEvent::all()]);"
   ```

   Note each candidate's exact `name` (the match key) and which entries'
   `source_url`s are already on file, so you don't spend time re-reading
   sources you've already captured.

   **If no candidates come back, stop.** Tell the user to run
   `find-salmon-arm-candidates` and import its file first. This skill never
   adds candidates. If you come across someone running who isn't on file,
   don't write them into the file; list them in the summary under "Not on
   file" so the user can re-run the roster skill.

3. **Official sources first.** Check the City of Salmon Arm's 2026 election
   page (salmonarm.ca) for:
   - status changes among the candidates on file: anyone the City now lists
     as withdrawn becomes `withdrawn`, and a `declared` candidate who now
     appears on the official list becomes `nominated`;
   - voting days, advance voting days, times and places, and any events not
     already on file (each one becomes an `<event>` with the City page as its
     URL).

4. **News.** For each candidate, and for the race in general:
   - **Salmon Arm Observer** (`https://www.saobserver.net/`): the local
     paper. Look for candidate announcements, profile series, questionnaires
     and forum coverage. Questionnaire answers are `qa_answer` entries, one per
     question, with the paper's question paraphrased in `<question>`.
   - **Castanet** (`castanet.net`): use its search and its regional
     coverage for Salmon Arm/Shuswap.
   - **Local radio** (`myshuswapnow.com` and similar): election stories and
     candidate interviews.

   - **Community questionnaires**, start from **Aim High Salmon Arm**'s
     hub (`https://timlavery.ca/candidates-qas/`, a former councillor's
     site), which links every local Q&A. Each is the candidates' own words:
     - Aim High's own three rounds for mayor and council
       (`timlavery.ca/category/qas/`): governance commitments (full term,
       schedule, eligibility; leadership style for mayor).
     - **Friday AM** (`https://friam.ca/`): its election-extra PDF asked
       every candidate for background and to "identify three issues
       important to you and how you would approach them", the strongest
       named-priority signal for the platform pass.
     - **Vote4Tomorrow** (Shuswap Climate Action Society,
       `https://vote4tomorrow.ca/election-2026/salmon-arm/`).
     - Castanet's election hub (`https://www.castanet.net/salmon-arm-votes-2026/`)
       and its six-question profile series.
     - Voice of the Shuswap interviews
       (`https://voiceoftheshuswap.ca/podcast-library/#civicelect4`). These
       are audio, so only usable if a transcript or written summary exists.

   Every story about the race becomes an `<article>` linked to each candidate
   it covers. Its substantive content about a candidate (a stated position,
   a quote) also becomes an `<entry>` with `source_type` `news`. The article
   is the coverage and the entry is the claim.

5. **Candidates' own channels.** Their website (platform page, about page,
   news posts), then Facebook and Instagram:
   - **Use the Chrome extension, never WebFetch, for Facebook and Instagram.**
     Load the tools first with
     `ToolSearch("select:mcp__claude-in-chrome__tabs_context_mcp,mcp__claude-in-chrome__tabs_create_mcp,mcp__claude-in-chrome__navigate,mcp__claude-in-chrome__get_page_text,mcp__claude-in-chrome__find,mcp__claude-in-chrome__javascript_tool")`,
     open a new tab, and read pages as the user's own logged-in session would.
     Only report what you actually read.
   - **Login walls are the user's step.** If Facebook or Instagram asks to log
     in, stop and ask the user to log in themselves in that tab. Never click a
     login button or type credentials.
   - **Facebook candidate pages:** read the About section, pinned posts and
     the campaign-period posts (roughly since nominations opened). Platform
     posts become `plank` entries. Replies the candidate makes to questions in
     their own comment threads are `qa_answer` entries, with the resident's
     question paraphrased and unnamed (rule 5). Use the post's or comment's own
     permalink as `source_url`. Get it from the post's timestamp link, not
     the page URL.
   - **Facebook groups the user belongs to** -- start with the **Salmon Arm
     Rant and Rave** group (`https://www.facebook.com/groups/714619231920570`),
     the user's priority group, then other local community and election
     discussion groups. The candidate's own answers there also feed the
     platform pass. Use the group's own search box
     (`.../groups/<id>/search/?q=<candidate name>`) for each candidate's name.
     Capture **only** the candidate's own posts and comments, with
     `source_type` `facebook_group` and the permalink as `source_url`. Note in
     `<notes>` that the source is in a group, since only members can open the
     link. Never post, react, comment, join or request to join anything.
   - **Instagram:** the bio and the candidate's own posts. The profile page
     text doesn't carry post dates; open a post and read its `<time>` element
     (`[...document.querySelectorAll('time')].map(t => t.getAttribute('datetime'))`)
     for `published_on`. Never like, follow, comment or open Stories (the
     owner sees who views a Story).
   - **Keep it light.** One candidate at a time, a pause between candidates,
     no endless scrolling. If Facebook or Instagram shows a challenge, a
     "suspicious activity" notice or "try again later", stop immediately
     and tell the user. It's their real account.

6. **Incumbents' prior record.** For each incumbent, look for what they did
   in the current term: council meeting minutes and agendas on the City's
   site (recorded votes, motions they moved), plus news coverage of council
   decisions that names how they voted. Each becomes a `prior_record` entry
   that states the action plainly ("Voted against the 2025 budget's 5.9% tax
   increase") with the minutes or article as the source. Prefer the minutes
   when both exist. Don't characterize a record as kept or broken promises.
   List the facts and let readers compare.

7. **Endorsements and finance.** `endorsement` entries only where the
   endorsement is published (by the candidate or the endorser) and sourced.
   `finance` entries come from Elections BC's disclosure records
   (`elections.bc.ca`, local election financing). Most 2026 filings won't be
   published until after the election. If none exist yet, say so in the
   summary rather than leaving it unmentioned.

8. **Photos.** `photo_url` should link to the candidate's own published
   headshot, ideally from their website or a news story. Facebook and
   Instagram image URLs expire within days, so use them only as a last
   resort and note it. Don't download or re-host images on your own
   initiative. The user may choose to keep photos in the app instead (they
   did for the Friday AM candidate profiles, Oct. 2026). Those live in the
   private app repo at `public/images/elections/candidates/<slug>.jpg`, with
   `photo_url` set to `/images/elections/candidates/<slug>.jpg` and
   `<photo_credit>` naming whose photo it is. Never put photos in the public
   module repo.

9. **Write the XML and hand it off as a diff.** Save to
   `~/Documents/election-research/` (create it if needed) as
   `salmon-arm-election-<YYYY-MM-DD>.xml`, or with the candidate's name in it
   for a targeted pass. Then summarize, structured around the baseline:
   - **Status changes** (declared → nominated, withdrawals), named.
   - **Not on file**: anyone running whom you found but who isn't on file,
     with the page where you saw them (re-run `find-salmon-arm-candidates`).
   - **New items per candidate**: counts by section, and the notable new
     planks or answers in one line each.
   - **New articles and events.**
   - **Conflicts and uncertainty** (rule 6), and anything you couldn't
     verify, such as a login wall or a private group you're not a member of.
   - **Things to delete by hand.** Anything on file that turned out to be
     wrong, retracted or about a different person. The import never
     deletes.
   - **Coverage balance** (rule 4): how much each candidate has on file and
     why it differs.

   **Importing (user's standing instruction, Oct. 3, 2026):** load the file yourself with the
   artisan command instead of handing it over. From `/Users/shawn/Documents/code/cultpantry`:
   1. Back up the local database: copy `database/database.sqlite` into the scratchpad.
   2. `php artisan elections:import <file> --dry-run`, and fix every problem it reports.
   3. `php artisan elections:import <file>`, then report the summary in the hand-off.
   
   This is for the local database only. Never import into staging or production unless the user
   asks for that specifically.

## Platform pass (planks)

A platform pass fills the **Platform** tab: each candidate's planks, ranked
by **how much the candidate themselves emphasizes them**. It is what the
candidate is putting out there, not what anyone else says about them, and
never a judgment of whether a position is good. Run it for a candidate (or
a batch of about five) when the user asks about platforms, priorities or
what someone is running on.

**Sources: only the candidate's own material.**
- Their campaign website: platform/priorities pages first, then the home
  and about pages and their own posts.
- Their campaign Facebook page and Instagram: their own posts, **and their
  own replies in those posts' comment sections** -- often where a position
  gets spelled out. Paraphrase the resident's question, never name them.
- Their own posts and replies in the **Salmon Arm Rant and Rave** group
  (`https://www.facebook.com/groups/714619231920570`) and other local groups
  the user belongs to: search the group for the candidate's name and keep
  only what the candidate wrote (`source_type` `facebook_group`).
- Their own words in the news and community questionnaires: their
  Observer introduction, Castanet Q&A, the Friday AM "three issues" answers,
  Vote4Tomorrow and Aim High Salmon Arm responses (all linked from
  `https://timlavery.ca/candidates-qas/`), and direct quotes in
  announcement stories. A
  reporter's paraphrase without a quote doesn't count unless the
  candidate's own material says the same thing.
- What they said at a forum, where there's a recording or a direct quote.

**Build the planks.**
1. Collect every statement of a position with its source (summary, verbatim
   `<quote>` where there is one, `source_url`, date).
2. Group statements that make the same position into one plank, across
   sources. Split two different positions within a topic ("more housing
   density" vs "protect farmland") into two planks.
3. Title each plank in the candidate's own framing, neutrally, in a few
   words ("Restart the downtown parking commission"), with a one- or
   two-sentence neutral `<summary>`. No adjectives about the position.
4. Give it a stable kebab-case `<key>`; on a repeat run reuse the key from
   the baseline.
5. **One statement backs one plank.** The import matches a statement by
   source URL + quote (or summary), so reusing the same quote from the same
   source under two planks moves it to whichever comes last. When one
   sentence touches two positions, file it under the one it's mainly about,
   or quote a different part of it for the other.

**Rank them by the candidate's emphasis.** Judge each plank on:
- **Named priority**: they list or number it as a priority, pillar or
  "my focus". Record their own position in `<priority_position>` only when
  they number or order the list themselves.
- **Repetition**: how many of their own, independent sources state it.
- **Placement and space**: whether they lead with it, and how much they say.
- **Specificity**: a specific, checkable commitment (a named project, a
  number, a timeline, "I will...") sets `<has_commitment>`; a value
  statement ("I support a vibrant downtown") doesn't.

Then put each plank in a tier and give it a rank across the whole platform
(1 = most emphasized, no ties):
- `top`: named priorities and positions they return to across their own
  material. Usually two to four.
- `also`: clearly stated positions with less emphasis.
- `mentioned`: raised once or in passing.

Write a one-sentence `<rationale>` for every plank that cites only these
signals ("Listed first of her four priorities on her site; repeated in her
Castanet Q&A and Observer introduction."). Never evaluate the position, and
apply the same yardstick to every candidate. If a candidate has published
very little, rank what there is and say in the hand-off that the order is
a rough guide (the page shows "Limited sources" when there are fewer than
two).

**Assess the plan for every plank, from new research.** The plan is not a paraphrase of the
statements already behind the plank. The page shows them side by side, so a plan that only
restates them adds nothing (the user called that out). Dig for *how* they'd do it:
- the full platform pages, not excerpts
- complete questionnaire answers, interviews and forum coverage
- their campaign posts and replies
- for incumbents, what they've moved or voted for on the same issue

Add any new statements you find to the plank as `<entries>`, then write the `<plan>`:
- **status:** specific, partial or none.
- **summary:** a neutral one- or two-sentence summary.
- **details:** each concrete element they stated (how, funding, timeline, measure, partners),
  with its source.

"No plan conveyed to date" (`none`) is a finding, not a criticism, and only valid **after**
looking in those places; list where you looked in the hand-off. Re-assess the plan whenever a
plank gains new statements; a later Q&A often adds the how. See the schema doc's "Plans" section.

**Hand-off** (in addition to the usual summary): per candidate, the planks
in rank order with tier and source count, which sources were used, and any
statements left out because they weren't the candidate's own words.

## AI analysis pass

**Pillars only.** Write an AI analysis for a candidate's **pillars**: their top-tier planks (named,
front-and-centre priorities). Skip planks they only "also" campaign on or mention. The code allows
an analysis on any plank, so a plank that becomes a pillar later can get one. If a plank drops out
of the top tier, remove its analysis (`<analysis … remove="true"/>`).

Each analysis (the page labels it "AI analysis") has four parts, the same for every pillar:
1. **meaning: What this means.** The proposal in plain language, in one or two sentences, from
   the candidate's full proposal. Collapsed panels show only this.
2. **works: Why it could work.** Two or three points.
3. **fails: Why it might not.** Two to four points. Who has the power (City, Province or
   others), cost, legal limits, capacity.
4. **details: The devil is in the details.** A concise conclusion in one or two sentences:
   what it really hinges on, and what the candidate hasn't said.

See the schema doc's "Analyses" section for the XML.

**Rules:**
- **Same treatment for everyone.** Every pillar of every candidate gets the same four parts and
  depth.
- **Balanced, never a verdict.** No "bad idea", and never a judgement of the candidate.
- **Facts are footnoted.** Every factual claim (a law, a number, a court ruling, an existing City
  plan, budget or contract) needs a `source_url` you opened; the page shows these as numbered
  footnotes. A pure reasoning point may go without one, but keep those rare.
- **Ground it in Salmon Arm.** Use the City's powers, plans, budgets and reports, BC law and court
  rulings, and local coverage. Reuse the same sourced facts across candidates so the same fact
  gets the same citation.
- **Use the candidate's full proposal.** Read their own page, not just the quote on file.

**Where it shows:** a collapsible panel on the plank card (Platform tab) and under the plank on
every subject page it's tagged with.

**Status (Oct. 3, 2026):** pillar samples are in
`~/Documents/election-research/salmon-arm-analysis-PILLAR-SAMPLES-2026-10-03.xml` (Bardy water,
Cannon wastewater, Desautels growth). Run the full pass, about 60 pillars, only after the user
approves them.

## Working with subagents

Large passes split well. For example: tag or plan batches of about 40 planks, or one candidate's
open-web sources per agent. Keep Chrome work (Castanet, Facebook) in the main session, one tab
at a time and throttled.
- **Give every agent the same rules.** The same vocabulary or yardstick, and the same output
  shape with a validation script.
- **Always run a check pass after the batches.** Have one agent verify every claim against the
  source it cites, drop what isn't supported, re-apply the yardstick, and report what changed.
  Batches drift apart: one plan batch leaned on our own plank summaries instead of the cited
  statements, and the check caught it.
- **Merge, validate, then dry-run.** Run the merged file against a copy of the database
  (`DB_DATABASE=…copy.sqlite php artisan tinker`) before handing it over.

## Tagging

Tag everything you write: planks, statements, Q&A answers, articles and scorecard statements.
- Use `<tags>` on the record, with 1 to 3 slugs from `references/tags.md`. The schema doc's
  "Tags" section has the rules.
- If nothing fits, add a tag in a `<vocabulary>` block at the top of the same file, with a
  topic, a name and a one-line description. Flag it in the hand-off.
  - Prefer widening an existing tag's description to adding a near-duplicate.
  - Keep `references/tags.md` in step.

**Backfill or re-tag existing records** when the user asks, or after the vocabulary changes:
- Read the records with the baseline `tinker` query.
- Write a `<tagging>` file to `~/Documents/election-research/salmon-arm-tags-<date>.xml`.
- Large batches split well across subagents: give each one the vocabulary and a slice of
  records, then merge and validate. Every slug must be in the vocabulary, each record gets 1 to
  3 tags, and there are no missing or extra records.

## Scorecard pass (Vote4Tomorrow and similar)

When the user asks for candidates' scorecard stances, or a refresh nearer
voting day, write a `<scorecards>` file. See the schema doc's "Scorecards" section.

1. **Fetch the scorecard.** Get the publisher's summary page and every candidate's page,
   including candidates who didn't answer. Vote4Tomorrow loads with plain `curl`. Save copies
   under `~/Documents/election-research/<scorecard>-<date>/`.
2. **Copy headers, intros and statements verbatim.** Record a stance for every statement, and
   mark non-responders `<responded>false</responded>`.
3. **Write a `<local_context>` for each category** from pages you opened: City pages and
   reports, Observer and Castanet stories (Castanet through Chrome). Keep the source list with it.
4. **Write one takeaway per responding candidate per category.**
   - Start with the local decision the category turns on (for example the step-code timeline,
     bus service levels, or a construction-waste bylaw) and where their answer points.
   - Then list the other stances briefly.
   - Use only facts that are in the local context. Add a candidate-specific fact only if it's
     sourced, such as an incumbent's recorded vote.
5. **Reuse keys** on a refresh, so stances are replaced in place.

## Scale

A full pass over every candidate is large. Do the official sources and news
for everyone first and write that file, then do the social-media passes one
candidate (or a few) at a time, each as its own file. Smaller reviewable files
beat one giant unverified batch.
