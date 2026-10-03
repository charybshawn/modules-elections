---
name: research-salmon-arm-candidates
description: Research the candidates already on file in the cultpantry admin panel's Elections module (modules-elections) for the Salmon Arm (BC) 2026 municipal election (mayor and council) and write a ready-to-import XML file. It covers bios, platform planks, statements, answers to questions from media, forums and Facebook, incumbents' council record, endorsements, campaign finance, election news coverage, withdrawals and election events (forums, voting days). Use this whenever the user asks to research, update, refresh or look into Salmon Arm election candidates, a specific candidate, their platform or promises, election coverage, or all-candidates forums, even if they don't mention XML, the import or this skill by name. Finding out who's running is find-salmon-arm-candidates, which must be run and imported first.
---

# Research Salmon Arm candidates

Researches the Salmon Arm 2026 general local election (voting day Saturday,
October 17, 2026; mayor + six councillors) and writes an XML file shaped for
the Elections module's **Import XML** button (`POST /admin/elections/import`,
handled by `Cultpantry\Elections\Actions\ImportElectionFromXml`). This is a
research aid, not an importer. It never writes to the database. The user
reviews the file and imports it themselves.

It researches only the candidates already on file. The roster (who's running)
comes from `find-salmon-arm-candidates`, which the user runs and imports first.

**Read `references/elections-xml-schema.md` before writing any output.** It
has the field list, the controlled value lists, and exactly how the import
matches and merges, which decides how you write a repeat run.

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

## Workflow

1. **Confirm scope.** "Everyone" means a full pass. A named candidate or topic
   ("Jane's housing position", "anything new from the forum") means a
   targeted pass. Ask if it's unclear.

2. **Read the baseline from the database** (read-only), so a repeat run
   produces a diff instead of re-reporting everything:

   ```
   cd /Users/shawn/Documents/code/cultpantry && php artisan tinker --execute="echo json_encode(['candidates' => \Cultpantry\Elections\Models\Candidate::with('entries')->get(), 'articles' => \Cultpantry\Elections\Models\Article::with('candidates:id,name')->get(), 'events' => \Cultpantry\Elections\Models\ElectionEvent::all()]);"
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
   - **Facebook groups the user belongs to** (local community and election
     discussion groups): use the group's own search box
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
   resort and note it. Never download or re-host images.

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

   **Don't import the file yourself.** The user reviews it and imports it
   through Admin → Elections → Import XML.

## Scale

A full pass over every candidate is large. Do the official sources and news
for everyone first and write that file, then do the social-media passes one
candidate (or a few) at a time, each as its own file. Smaller reviewable files
beat one giant unverified batch.
