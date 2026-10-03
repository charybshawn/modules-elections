---
name: community-pulse-salmon-arm
description: Read local election discussion in the Salmon Arm Rant and Rave Facebook group (and other local groups the user belongs to), then write a dated Community Pulse snapshot for the cultpantry Elections module (modules-elections) -- the issues residents raise, weighted by how many different people raise them, the rough stance split and heat, what residents want, the questions they keep asking, and how often each candidate is mentioned -- as a ready-to-import XML file. Use this when the user asks what residents or voters are saying, the community's temperature or mood on the election, a pulse or sentiment check, or what issues people care about, even if they don't mention XML or this skill by name. Candidates' own positions belong to research-salmon-arm-candidates, not here.
---

# Community Pulse for Salmon Arm

Summarizes **what residents are discussing** about the 2026 Salmon Arm
election into a dated snapshot shown on Elections → Community Pulse, a page
visible to admins and invited viewers. It's an honest read of one online
conversation, not a poll and not a verdict on candidates. It never writes to
the database; the user reviews and imports the file.

**Read `../research-salmon-arm-candidates/references/elections-xml-schema.md`
→ "Community Pulse" before writing output.**

## Rules

1. **No resident is identifiable.** Never record a name, a quote, a profile
   link, a photo or any detail that points to one person. Everything is an
   aggregate or a neutral paraphrase. Read the comments, summarize, and keep
   nothing else; don't save raw comment text to files.
2. **Weight by people, not comments.** `voices` is the number of *distinct*
   people who raised an issue. Ten comments from one person count once.
   Several different people agreeing within a thread is the strongest signal.
3. **Candidates: counts only.** `<mentions>` is how often each candidate came
   up by name, and how many different people mentioned them. No sentiment
   about candidates, no "who's winning", no electability claims.
4. **Neutral paraphrase.** Summaries, wants and questions describe what people
   said without endorsing or mocking it. Leave out personal attacks,
   rumours about individuals and anything defamatory entirely.
5. **Say what it can't tell you.** One Facebook group skews toward the people
   who post there. Note anything notable about the sample in `<method_note>`
   (e.g. one thread dominated, few threads on an issue).
6. **Facebook safety.** Follow the throttling rules: about 8-15 seconds
   between loads and clicks, a handful of threads per sitting, read-only
   (never post, react, comment, join), and stop immediately at any CAPTCHA,
   "unusual activity" or login prompt and tell the user. Don't open the
   user's notifications or messages.

## Collecting (Chrome extension)

1. Load the tools in one call:
   `ToolSearch("select:mcp__claude-in-chrome__tabs_context_mcp,mcp__claude-in-chrome__tabs_create_mcp,mcp__claude-in-chrome__tabs_close_mcp,mcp__claude-in-chrome__navigate,mcp__claude-in-chrome__javascript_tool,mcp__claude-in-chrome__computer,mcp__claude-in-chrome__browser_batch")`.
   Use `javascript_tool` reads, not `find` (which uses the account's model
   quota).
2. **Find threads** with the group's search for election terms, not names:
   `https://www.facebook.com/groups/714619231920570/search/?q=<term>` for
   *council candidates*, *election*, *mayor*, *vote*, *all candidates*, and
   issue words (*homeless*, *taxes*, *pool*, *wastewater*, *housing*). Plus
   any thread links the user sends. Skip threads older than the campaign
   (before about Aug. 2026) unless the user asks.
3. **Open a thread** by clicking a search result's comment count, or
   navigate to its permalink. Then:
   - switch the sort to **All comments** (click the "Newest"/"Most relevant"
     button, then the "All comments" menu item);
   - expand **View N replies** one at a time with ~4 s waits;
   - read each comment's author (the `aria-label` "Comment by …" /
     "Reply by …") only to count distinct people and spot candidates, and its
     text to summarize. Don't keep either.
   Long threads may not load every top-level comment; note partial coverage.
4. Keep running tallies per issue: distinct people, rough stance, heat (how
   heated or repetitive the exchange is), wants, recurring questions. And per
   candidate: mentions and distinct mentioners.

## Writing the snapshot

- Map each issue to a module `topic` (the same list planks use) so the page
  can set it beside candidates' platforms; use `other` only when nothing fits.
- Reuse issue `<key>`s from earlier snapshots (check the last export or the
  page) so changes over time line up.
- `<conclusions>`: three to five plain-language takeaways, each tied to an
  issue key, about the conversation, not about candidates. "Homelessness
  draws the most people, split between services and enforcement" is fine;
  "residents are turning against X" is not.
- Save to `~/Documents/election-research/salmon-arm-pulse-<YYYY-MM-DD>.xml`
  with just a `<pulse>` block inside `<election>`.

## Hand-off

Threads read (with dates), distinct commenters, the issues ranked by voices,
anything partial or skewed, and any thread where a **candidate** answered:
pass those to `research-salmon-arm-candidates`, since a candidate's own reply
belongs on their platform, not in the pulse.

**Don't import the file yourself.**
