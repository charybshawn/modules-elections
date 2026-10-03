# Elections XML schema

What `Cultpantry\Elections\Actions\ImportElectionFromXml` reads (Admin →
Elections → Import XML) and `ExportElectionToXml` writes. The controlled value
lists below are validated server-side against the constants on
`Cultpantry\Elections\Models\Candidate`, `Entry` and `ElectionEvent` — don't
invent new values.

## Shape

```xml
<?xml version="1.0" encoding="UTF-8"?>
<election>
  <candidates>
    <candidate>
      <name>Jane Example</name>                 <!-- required; the match key -->
      <office>councillor</office>               <!-- required for a new candidate -->
      <status>nominated</status>
      <is_incumbent>false</is_incumbent>
      <occupation>Small business owner</occupation>
      <bio>Two or three neutral sentences.</bio>
      <bio_source_url>https://janeexample.ca/about</bio_source_url>
      <website>https://janeexample.ca</website>
      <facebook_url>https://www.facebook.com/janeexampleforcouncil</facebook_url>
      <instagram_url>https://www.instagram.com/janeexample</instagram_url>
      <email>jane@janeexample.ca</email>
      <phone>250-555-0100</phone>
      <photo_url>https://janeexample.ca/img/jane.jpg</photo_url>
      <notes>Research notes, admins only.</notes>
      <entries>
        <entry>
          <kind>plank</kind>                     <!-- required -->
          <topic>housing</topic>
          <summary>Neutral paraphrase.</summary>  <!-- required -->
          <quote>Exact words, verbatim.</quote>
          <question>Paraphrased question (qa_answer only).</question>
          <source_url>https://...</source_url>   <!-- required, http(s) -->
          <source_type>candidate_site</source_type> <!-- required -->
          <source_name>janeexample.ca</source_name>
          <published_on>2026-09-21</published_on>
        </entry>
      </entries>
      <planks>                                  <!-- the Platform tab; see "Planks" below -->
        <plank>
          <key>downtown-parking</key>           <!-- required; stable kebab-case; the match key per candidate -->
          <title>Fix downtown parking</title>   <!-- required; their framing, neutral, a few words -->
          <topic>downtown_development</topic>
          <summary>One or two neutral sentences.</summary>
          <tier>top</tier>                      <!-- top | also | mentioned -->
          <rank>1</rank>                        <!-- 1 = most emphasized, across the whole platform -->
          <priority_position>1</priority_position> <!-- only when THEY number/list their priorities -->
          <has_commitment>true</has_commitment>
          <rationale>Listed first of her four priorities; repeated in the Castanet Q&amp;A.</rationale>
          <entries>                             <!-- the statements it rests on; imported as kind plank -->
            <entry>...</entry>
          </entries>
        </plank>
      </planks>
    </candidate>
  </candidates>

  <articles>
    <article>
      <title>Eleven candidates vie for six council seats</title> <!-- required -->
      <url>https://www.saobserver.net/...</url>   <!-- required; the match key -->
      <outlet>Salmon Arm Observer</outlet>
      <published_on>2026-09-15</published_on>
      <summary>One or two neutral sentences.</summary>
      <candidates>
        <candidate>Jane Example</candidate>     <!-- exact <name> of a candidate in the file or on file -->
      </candidates>
    </article>
  </articles>

  <events>
    <event>
      <title>All-candidates forum</title>         <!-- required -->
      <kind>forum</kind>
      <starts_at>2026-10-07T19:00</starts_at>     <!-- required; local time, YYYY-MM-DDTHH:MM -->
      <ends_at>2026-10-07T21:00</ends_at>
      <location>Salmon Arm Recreation Centre</location>
      <url>https://...</url>
      <description>Hosted by ...</description>
    </event>
  </events>
</election>
```

Any of `<candidates>`, `<articles>`, `<events>` may be left out. Order
inside an element doesn't matter. Write `&amp;` for `&` in URLs.

## Controlled values

| Field | Values |
|---|---|
| `office` | `mayor`, `councillor` |
| `status` | `declared` (announced, not yet on the City's official list), `nominated` (on the City's official nominations list), `withdrawn` |
| `kind` | `background` (who they are: career, business, education, community roles, public service, local roots -- written by `backfill-salmon-arm-backgrounds`), `plank` (a platform commitment or position they campaign on), `statement` (something they said that isn't a formal plank), `qa_answer` (an answer to a question: media questionnaire, forum, Facebook comment reply), `prior_record` (incumbents: a council vote, motion or public action in the current/past term), `endorsement` (who endorses them, or whom they endorse), `finance` (campaign finance disclosure facts) |
| `topic` (every kind except `background`) | `housing`, `taxes_budget`, `infrastructure`, `downtown_development`, `transportation`, `environment`, `public_safety`, `recreation_parks`, `economy_business`, `social_services`, `governance_transparency`, `other` |
| `topic` (`background` entries) | `career` (jobs, profession, employers), `business` (businesses they own or run), `education` (schooling, degrees, credentials), `community` (boards, volunteering, clubs, coaching), `public_service` (elected or appointed office, City committees, past runs for office), `local_roots` (how long in Salmon Arm/the Shuswap and what brought them), `other` |
| `source_type` | `candidate_site` (their campaign site), `personal_site` (their own non-campaign website or blog), `linkedin`, `instagram`, `facebook_page`, `facebook_group`, `news`, `forum`, `organization` (a business's, board's or club's own website), `city`, `elections_bc`, `other` |
| plank `tier` | `top` (front and centre: a named priority, or a position they return to across their own material), `also` (clearly stated, less emphasis), `mentioned` (raised once or in passing) |
| event `kind` | `forum`, `deadline`, `advance_voting`, `general_voting`, `other` |

## Import behaviour (what to rely on)

- **Candidates match on `<name>`, exactly.** Spell a returning candidate's
  name exactly as it's on file (the baseline query shows it), or the import
  creates a second candidate.
- **A supplied candidate field replaces what's on file** -- including
  `notes`. To add to a candidate's notes, write their existing notes
  (from the baseline) first and append the new ones; writing only the new
  notes erases the old.
- **An omitted candidate field is left alone, not cleared.** You don't need to
  carry forward the whole baseline row; write only what you found or what
  changed. (The opposite of the market import.) There is no way to clear a
  field by import — say so in the summary if something on file is wrong and
  needs removing by hand.
- **Entries match on (candidate, source URL + quote)**, or source URL +
  summary when there's no quote. Whitespace and case in the quote don't
  matter. Re-supplying an entry that's already on file is a no-op, and
  changing its summary/topic/date while keeping the same URL and quote
  updates it in place. Changing the quote or the URL makes it a new entry —
  so copy quotes exactly.
- **Articles match on URL**; their candidate links are additive.
- **Events match on (title, starts_at).** Changing either makes a new event —
  if a forum moves, mention the old one in the summary so the user can delete
  it.
- **Nothing is ever deleted by an import.** Retracted or wrong items are
  removed by hand on the candidate page; list them in the summary.
- Entries with no `summary`, no http(s) `source_url` or an unknown `kind`
  are skipped. Unknown `topic`/`source_type` fall back to `other` -- and a
  `background` entry's topic is checked against the background list, so a
  background entry with `<topic>housing</topic>` lands under "Other".
- `background` entries show on the candidate page under **About**, grouped
  by their topic, below the `bio` paragraph. Keep `bio` as a short neutral
  summary and put each individual fact in its own `background` entry with
  its own source. All of
  this is reported back after the import, so check the message.
- `bio` without `bio_source_url` imports but is reported as a problem — always
  give the bio's source.

## Planks

- **Planks match on (candidate, `<key>`).** Reuse a plank's key from the
  baseline on a repeat run, or the import makes a second plank. Changing
  its title, tier, rank or rationale under the same key updates it in place.
- **A plank's `<entries>` are its sources** and import as `plank` entries
  linked to it, whatever `<kind>` they say. A statement already on file
  (same source URL + quote/summary) is linked, not duplicated -- so a plank
  can point at a statement imported earlier as a loose `plank` entry.
- **Tier then rank orders the tab**: all `top` planks, then `also`, then
  `mentioned`, each by `rank`. Give every plank a distinct rank across the
  candidate's whole platform.
- **Every plank needs a `<rationale>` and at least one source**; the import
  reports any that don't. Unknown tiers file as `mentioned`, unknown topics
  as `other`.
- Planks are never deleted by an import; a wrong one is deleted by hand on
  the Platform tab (which removes its statements too).

## Community Pulse (`<pulse>`)

Written by `community-pulse-salmon-arm`. Sits beside `<candidates>`, `<articles>` and `<events>`:

```xml
<pulse>
  <taken_on>2026-10-03</taken_on>            <!-- required; the match key -->
  <period_from>2026-09-20</period_from>      <!-- oldest thread read -->
  <period_to>2026-10-03</period_to>
  <threads_read>12</threads_read>
  <commenters>184</commenters>               <!-- distinct people across all threads -->
  <sources>the Salmon Arm Rant and Rave Facebook group</sources>
  <method_note>Anything readers should know about how it was read.</method_note>
  <conclusions>
    <conclusion issue="homelessness">One-sentence takeaway, tied to an issue key.</conclusion>
  </conclusions>
  <issues>
    <issue>
      <key>homelessness</key>                <!-- stable across snapshots, for change over time -->
      <title>Homelessness and downtown encampments</title>
      <topic>social_services</topic>         <!-- Entry::TOPICS; matches candidates' planks -->
      <voices>48</voices>                    <!-- distinct people who raised it -->
      <support_pct>20</support_pct>          <!-- rough split of those voices; optional -->
      <oppose_pct>55</oppose_pct>
      <mixed_pct>25</mixed_pct>
      <heat>high</heat>                      <!-- low | medium | high -->
      <summary>Neutral two-sentence paraphrase.</summary>
      <wants><want>Paraphrased want</want></wants>
      <questions><question>Paraphrased recurring question</question></questions>
    </issue>
  </issues>
  <mentions>
    <mention><candidate>Exact name on file</candidate><mentions>7</mentions><commenters>5</commenters></mention>
  </mentions>
</pulse>
```

- **Re-importing a `taken_on` replaces that snapshot's issues and mentions** (unlike everything else, which merges). A new pass gets a new date.
- Keep issue `<key>`s stable between snapshots so the page can show "+12 people since last time".
- No resident names, quotes or identifying detail anywhere in a pulse.

## Scorecards (`<scorecards>`)

Third-party candidate scorecards, such as Vote4Tomorrow: a fixed list of
statements under the publisher's own category headers, with each candidate's
stance on each statement. They're shown on a candidate's page in a tab named
after the scorecard.

```xml
<scorecards>
  <scorecard>
    <key>vote4tomorrow-2026</key>                  <!-- required; match key -->
    <title>Vote4Tomorrow</title>
    <publisher>Shuswap Climate Action Society</publisher>
    <url>https://vote4tomorrow.ca/election-2026/salmon-arm/</url>
    <about>One neutral paragraph on what it is.</about>
    <retrieved_on>2026-10-02</retrieved_on>
    <categories>
      <category>
        <key>buildings</key>                       <!-- match key within the scorecard -->
        <name>Buildings</name>                     <!-- the publisher's header, verbatim -->
        <intro>The publisher's intro, verbatim.</intro>
        <local_context>Our neutral, sourced note on this area in Salmon Arm today.</local_context>
        <sources><source>https://...</source></sources>
        <items>
          <item><key>electrify-new-buildings</key><statement>Verbatim statement.</statement></item>
        </items>
      </category>
    </categories>
    <responses>
      <response>
        <candidate>Exact name on file</candidate>
        <source_url>https://vote4tomorrow.ca/election-2026/salmon-arm/slug/</source_url>
        <responded>true</responded>              <!-- false = listed as not answering -->
        <answers>
          <answer item="electrify-new-buildings">supportive</answer>  <!-- supportive|neutral|opposed|no_response -->
        </answers>
        <takeaways>
          <takeaway category="buildings">What their stances here could mean in practice for Salmon Arm.</takeaway>
        </takeaways>
      </response>
    </responses>
  </scorecard>
</scorecards>
```

- **Matching.** Categories and statements are matched by key, in file order, and a statement's
  key must be unique across the whole scorecard. A response is matched by candidate.
- **Re-imports.** When a response includes `<answers>` or `<takeaways>`, those replace what's on
  file. Nothing is deleted otherwise.
- **Readings.** Takeaways are AI-assisted, and the page labels them that way.
  - Lead with the one local decision the category turns on, then list the rest briefly.
  - Use only facts that are in the category's `<local_context>` sources.
  - Keep them neutral and conditional ("points to", "no signal on"), never judging whether a position is right.
  - A candidate who didn't respond gets `<responded>false</responded>` and no answers or takeaways. The page says they didn't answer, never that they're opposed.

## Tags (`<vocabulary>`, `<tags>`, `<tagging>`)

Subject tags come from a controlled vocabulary, listed in `references/tags.md`. Each tag files
under one `Entry::TOPICS` heading.

**Vocabulary.** Defining or updating tags goes at the top of a file. Tags match on slug, and a
supplied field replaces what's on file:

```xml
<vocabulary>
  <tag><slug>homelessness</slug><topic>social_services</topic><name>Homelessness</name><description>What belongs here.</description></tag>
</vocabulary>
```

**Tags on a record.** Add `<tags><tag>slug</tag>…</tags>` inside any `<plank>`, `<entry>`,
`<article>`, pulse `<issue>` or scorecard `<item>`.
- When `<tags>` is present, it replaces that record's tags; an empty `<tags/>` clears them.
- When it's left out, the record keeps the tags it has.
- An unknown slug is reported as a problem, never created. Add it to `<vocabulary>` in the same
  file first.

**Tagging records already on file** without repeating them. Each element replaces that record's
tags:

```xml
<tagging>
  <plank candidate="Exact Name" key="plank-key"><tags><tag>wastewater</tag></tags></plank>
  <entry candidate="Exact Name" hash="match_hash"><tags>…</tags></entry>
  <article url="https://…"><tags>…</tags></article>
  <issue taken_on="2026-10-03" key="issue-key"><tags>…</tags></issue>
  <item scorecard="vote4tomorrow-2026" key="statement-key"><tags>…</tags></item>
</tagging>
```

**Tagging rules:**
- Give each item 1 to 3 tags: the most specific ones a reader browsing that subject would expect
  to find it under.
- Tag what the item is about, not who said it.
- Give identical ideas identical tags across candidates.
- Background facts aren't tagged. A plank's statements are reached through the plank.
- Community Pulse matches an issue to candidates' planks by shared tags, so tag pulse issues with
  the same slugs that planks use.

## Plans (`<plan>` on a plank, or `<plans>`)

A plan records what the candidate has said about **how** they'd carry a plank out. It's shown
beside the plank's statements on the Platform tab.

```xml
<plank>
  …
  <plan status="specific|partial|none">
    <summary>One or two neutral sentences on what they'd actually do.</summary>
    <detail aspect="how|funding|timeline|measure|partners|other" source_url="https://…">One concrete element they stated.</detail>
  </plan>
</plank>
```

For planks already on file, use `<plans>` with no need to repeat them:

```xml
<plans>
  <plan candidate="Exact Name" key="plank-key" status="partial">…same children…</plan>
</plans>
```

**Statuses** (about what has been conveyed, never whether the plan is good):
- `specific`: a concrete action **and** at least one of funding, timing, a measurable target, or
  named partners.
- `partial`: at least one concrete action, step, target or funding source, but short of specific.
- `none`: only goals or values; the page shows "No plan conveyed to date".

**Rules:**
- Every `<detail>` needs a `source_url`, which should be one of the plank's own statements.
  Details with no http(s) source are dropped and reported.
- A supplied `<plan>` replaces the plank's plan; leaving it out keeps what's on file.
- Use the candidate's own words only. Don't infer steps they didn't state, and apply the same
  yardstick to everyone.
