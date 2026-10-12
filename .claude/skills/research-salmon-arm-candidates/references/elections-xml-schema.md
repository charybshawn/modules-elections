# Elections XML schema

What `Cultpantry\Elections\Actions\ImportElectionFromXml` reads (Admin →
Elections → Import XML) and `ExportElectionToXml` writes. The controlled value
lists below are validated server-side against the constants on
`Cultpantry\Elections\Models\Candidate`, `Entry` and `ElectionEvent` — don't
invent new values.

## Which election

The module tracks two elections side by side: the Salmon Arm municipal election
(`salmon-arm-2026`) and the BC provincial election in the Salmon Arm-Shuswap
riding (`bc-provincial-salmon-arm-shuswap`). The root says which one a file is
for with an `id` attribute: `<election id="bc-provincial-salmon-arm-shuswap">`.
**No `id` means `salmon-arm-2026`**, so every municipal file works unchanged.
An unknown `id` is rejected. Candidates, articles and events belong to the
election named on their file, and every by-name reference in the file (article
candidate lists, plans, analyses, tagging, `<changes>`) is looked up within
that same election, so the same name can stand in both. `<pulse>` and
`<scorecards>` are municipal-only for now; a provincial file that carries them
is skipped with a note.

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
      <party>BC NDP</party>                     <!-- provincial only: the party on the ballot, as Elections BC prints it -->
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
| `office` | Municipal election: `mayor`, `councillor`, `trustee` (a School District 83 trustee seat Salmon Arm voters elect). Provincial election: `mla`. An office from the other election is rejected |
| `party` | Free text, provincial candidates only: the ballot affiliation exactly as Elections BC prints it (`BC NDP`, `Conservative Party`, `BC Green Party`, `OneBC`, `CentreBC`, `Independent`, ...). The pages colour the badge for the parties they know and show any other name in grey |
| vocabulary tag `kind` | `subject` (the default: a subject that files under a `topic` heading and shows in Browse by subject) or `news` (a type of story: it labels articles only, has no subject page and never shows in subject views). The news tags on file are `news-personal` (their own personal life and personal legal matters, as reported, for example an arrest or a court case against them as a private person; never family), `news-legislative` (anything that relates to their public office: council, committee and Legislature business, votes, bills, policy positions, and their public statements, posts and appearances as an office-holder -- if it relates to the office, it is legislative, even when controversial), `news-opinion` (opinion pieces, editorials, letters) and `news-background` (who they are and the context of the race: profiles, introductions, announcements, forums, how to vote). A story carries **one** news tag (it sits in one column); a second is dropped with a note. It can carry any number of subject tags beside it. A `<tags>` list replaces all of a record's tags, so repeat the subject slugs a story already has |
| `status` | `declared` (announced, not yet on the City's official list), `nominated` (on the City's official nominations list), `withdrawn` |
| `kind` | `background` (who they are: career, business, education, community roles, public service, local roots -- written by `backfill-salmon-arm-backgrounds`), `plank` (a platform commitment or position they campaign on), `statement` (something they said that isn't a formal plank), `qa_answer` (an answer to a question: media questionnaire, forum, Facebook comment reply), `prior_record` (incumbents: a council vote, motion or public action in the current/past term), `endorsement` (who endorses them, or whom they endorse), `finance` (campaign finance disclosure facts) |
| `topic` (every kind except `background`) | `housing`, `taxes_budget`, `infrastructure`, `downtown_development`, `transportation`, `environment`, `public_safety`, `recreation_parks`, `economy_business`, `social_services`, `governance_transparency`, `education` (schools and the school district; mostly trustees), `other` |
| `topic` (`background` entries) | `career` (jobs, profession, employers), `business` (businesses they own or run), `education` (schooling, degrees, credentials), `community` (boards, volunteering, clubs, coaching), `public_service` (elected or appointed office, City committees, past runs for office), `local_roots` (how long in Salmon Arm/the Shuswap and what brought them), `affiliation` (churches and other faith communities, service clubs, societies, unions, associations, parties or slates they belong or belonged to, when a public source says so), `past_activity` (public things they did before running: projects, causes, events, delegations, awards), `corporate_roles` (company boards, directorships and officer roles, from the corporate registry or company filings), `holdings` (property, shares, company ownership and other assets, from public records and mandated disclosures), `political_giving` (donations the person themselves made to parties, candidates or campaigns, from public filings), `other`. `community`, `public_service`, `affiliation`, `past_activity`, `corporate_roles`, `holdings` and `political_giving` show on the Background & Affiliations tab |
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

## Candidate photos

`<photo_url>` is either an http(s) link, or a path to a file in the app itself, such as
`/images/elections/candidates/jane-example.jpg`. Those files live in the private app repo, never in
this public module.

`<photo_credit>` names whose photo it is (e.g. `Friday AM`). It's shown as "Photo: …" under the
picture.

## Analyses (`<analysis>` on a plank, or `<analyses>`)

An AI analysis of a **pillar** (a top-tier plank), shown as a collapsible "AI analysis" panel with
numbered footnotes on the plank card and on subject pages.

```xml
<plank>
  …
  <analysis on="2026-10-03">
    <meaning><point source_url="https://…">The proposal in plain language.</point></meaning>
    <works><point source_url="https://…">Why it could work.</point></works>
    <fails><point source_url="https://…" source_url-2="https://…">Why it might not.</point></fails>
    <details><point source_url="https://…">A concise conclusion.</point></details>
  </analysis>
</plank>
```

For planks already on file, or to take an analysis off a plank:

```xml
<analyses>
  <analysis candidate="Exact Name" key="plank-key" on="2026-10-03">…same children…</analysis>
  <analysis candidate="Exact Name" key="other-plank" remove="true"/>
</analyses>
```

- **Sources:** a point may carry several sources: `source_url`, `source_url-2`, and so on.
  They're numbered as footnotes in order of first citation.
- **Replacement:** a supplied analysis replaces the plank's analysis; leaving it out keeps it, and
  `remove="true"` clears it.
- **Rejected or dropped:**
  - An analysis with no points, or one that cites no sources at all, is rejected and reported.
  - Non-http(s) sources are dropped and reported.
- **Date:** `on` is the date it was written; if it's missing, the import uses today.
- **Retired shape:** analyses in the earlier impact/challenges/risks format are hidden on the
  page.

## Votes and bill notes (`<votes>`, `<bill_notes>` under a `<candidate>`)

Provincial only (a legislature's recorded divisions). Written by `pull-bc-voting-record`;
shown on the candidate's Voting record tab, one entry per bill.

```xml
<candidate>
  <name>David L Williams</name>
  <votes>
    <vote>
      <body>legislature</body>               <!-- legislature | council | board -->
      <voted_on>2025-05-28</voted_on>        <!-- required -->
      <title>Bill 7: … — third reading</title>   <!-- required -->
      <reference>Bill 7</reference>
      <bill_key>43rd1st:Bill 7</bill_key>    <!-- groups a bill's votes; unique within a session -->
      <bill_title>Bill 7: Economic Stabilization (Tariff Response) Act</bill_title>
      <stage>third_reading</stage>           <!-- first_reading second_reading committal report_stage third_reading | amendment clause procedural -->
      <description>the record's own wording</description>
      <vote>nay</vote>                        <!-- required: yea | nay | abstained -->
      <outcome>passed</outcome>               <!-- passed | defeated -->
      <tally_for>48</tally_for><tally_against>44</tally_against>
      <source_url>https://…</source_url>      <!-- required, http(s) -->
      <source_name>Votes and Proceedings, …</source_name>
      <tags><tag>slug</tag></tags>
    </vote>
  </votes>
  <bill_notes>
    <bill_note>
      <bill_key>43rd1st:Bill 7</bill_key>     <!-- required; the candidate must have votes on this bill -->
      <purpose>What the bill does.</purpose>
      <vote_analysis>Why they voted as they did, and how the question ended.</vote_analysis>
      <basis>spoke</basis>                    <!-- spoke | caucus | record: what the "why" rests on -->
      <sources><source><label>Bill text</label><url>https://…</url></source></sources>
    </bill_note>
  </bill_notes>
</candidate>
```

Bills and motions the candidate introduced themselves (provincial; the Legislature records
one sponsor per bill, so there are no co-sponsors) go in a `<sponsored>` block:

```xml
<sponsored>
  <item>
    <kind>bill</kind>                          <!-- required: bill | motion -->
    <reference>Bill M246</reference>           <!-- required; with kind and session, the match key -->
    <session>43rd Parliament, 2nd session</session>   <!-- required -->
    <title>Small Farms Act</title>             <!-- required -->
    <summary>One or two sentences from the bill's own note.</summary>
    <introduced_on>2026-05-21</introduced_on>
    <status>introduced</status>                <!-- required: introduced | second_reading | committee | third_reading | law | moved | agreed | defeated | not_proceeded -->
    <status_note>First reading only; no further stage recorded</status_note>
    <context>30 of 93 MLAs introduced a private member's bill this Parliament; 6 became law.</context>
    <source_url>https://…</source_url>         <!-- required, http(s) -->
    <source_name>Bill text (first reading)</source_name>
  </item>
</sponsored>
```

A vote matches on its source, day, reference and wording, so a re-import updates it. A
bill note matches on `bill_key` per candidate. A note needs a `<bill_key>` the candidate has
votes on and at least one of `<purpose>` / `<vote_analysis>`; an unknown `<basis>` or a
source without an http(s) `<url>` is left out with a note. Votes for bills with no
`<bill_key>` (motions) stand alone on the page.

## Party profiles (`<parties>`)

Provincial only. One `<party>` per party, matched by `<name>`, which must equal the name candidates carry in their `<party>` field (as Elections BC prints it). The provincial candidate page shows it under the Occupation row, in this order: current leader, how long the party has existed, times in government, then what it stands for and its platform. Parties change (leaders resign, parties split and merge, platforms are released), so each profile is dated and **must be re-checked** before an election and when the news says something changed.

```xml
<parties>
  <party>
    <name>BC NDP</name>                              <!-- required; same as the candidate's <party> -->
    <full_name>British Columbia New Democratic Party (BC NDP)</full_name>
    <leader>David Eby</leader>
    <leader_note>Since when, and any interim or recent change.</leader_note>
    <founded>1933 (93 years), …</founded>            <!-- how long it has existed -->
    <founded_note>Lineage, renamings, splits.</founded_note>
    <times_in_government>3</times_in_government>     <!-- whole number; 0 = never -->
    <government_note>Which governments, when, or "never".</government_note>
    <principles>What it stands for, in its own terms, with outside descriptions attributed.</principles>
    <platform><point>One current commitment.</point></platform>
    <as_of>2026-10-12</as_of>                        <!-- required: the day it was checked -->
    <sources><source><label>…</label><url>https://…</url></source></sources>
  </party>
</parties>
```

Sourcing: the party's own site for leader, principles and platform (say so when a page describes a record, not pledges, or says the full platform is still coming), the Legislature or reputable press for leadership changes, Wikipedia only for history and for outside descriptions of ideology, attributed. State what a source does not say (an interim leader, a platform not yet published).

### Updating a profile, and a party's fact-checks and news

A `<party>` is **updated field by field**: only the elements present in the file change, so a correction can carry just `<leader_note>`, `<sources>` and `<as_of>`. A *new* party needs `<name>` and `<as_of>`. The same `<party>` element can also carry `<claims>` and `<news>`; a file of only those leaves the profile alone. The party's own page (`/admin/elections/provincial/parties/{slug}`) shows the profile, the riding's candidates for it, the fact-check and the news.

```xml
<party>
  <name>BC NDP</name>
  <claims>
    <claim>
      <key>jobs-since-2017</key>                     <!-- required; matched on this within the party -->
      <kind>claim</kind>                             <!-- claim (something said about the record) | pledge (a promise) -->
      <claim>The sentence, as the party words it.</claim>
      <source_url>https://…</source_url>             <!-- required: where the party says it -->
      <source_name>Party page, read Oct. 12, 2026</source_name>
      <topic>Economy</topic>
      <rating>holds_up</rating>                      <!-- holds_up | mostly_holds_up | mixed | mostly_does_not | does_not_hold_up | untestable -->
      <summary>What the evidence shows, neutrally, including what the claim leaves out.</summary>
      <evidence>
        <item><kind>government_record</kind>         <!-- votes | government_record | past_statement | reporting -->
              <text>The fact, with figures and dates.</text><label>Source name</label><url>https://…</url></item>
      </evidence>
      <checked_on>2026-10-12</checked_on>            <!-- required -->
    </claim>
  </claims>
  <news>
    <article><title/><url/><outlet/><published_on/><type>legislative</type><summary/></article>
                                                     <!-- type: legislative | opinion | background; matched on url within the party -->
  </news>
</party>
```

A party can also carry its **province-wide slate**, the whole official list, replaced on each import (a candidate the file no longer names is removed). It shows as a fixed-height, scrolling list on the party's About tab:

```xml
<slate>
  <source_url>https://elections.bc.ca/2026-provincial-election/candidate-list/</source_url>   <!-- required -->
  <as_of>2026-10-12</as_of>                                                                 <!-- required -->
  <candidate><riding>Abbotsford South</riding><name>Leah Ballantyne</name></candidate>
  <!-- A sitting MLA running again: name the seat they hold now (it can differ from the riding they run in). -->
  <candidate><riding>Surrey South</riding><name>Elenore Sturko</name><incumbent_riding>Surrey-Cloverdale</incumbent_riding></candidate>
</slate>
```

Rules: a claim with no key, claim text, summary, valid `<checked_on>`, known `<rating>` or `http(s)` `<source_url>` is skipped and reported; an evidence item with an unknown kind or no text is dropped. Only **party-level** stories go under `<news>`; a candidate's stay on the candidate. Ratings are explained in `check-bc-party-claims`.

## Polls and riding projections (`<polls>`, `<projections>`)

Provincial only, top-level in the file beside `<parties>`. They feed the provincial dashboard: the poll panel (an average plus every poll, in a fixed-height scrolling list) and the riding projections list (this riding first).

```xml
<polls>
  <poll>
    <pollster>Liaison Strategies</pollster>            <!-- required; with fielded_to, how a poll is matched -->
    <fielded_from>2026-10-07</fielded_from>
    <fielded_to>2026-10-08</fielded_to>                <!-- required: last day in the field (or the date an aggregator gives) -->
    <published_on>2026-10-09</published_on>
    <sample_size>1000</sample_size><method>IVR telephone</method>
    <results>                                          <!-- at least two parties; names exactly as in <parties>; percent of decided and leaning voters -->
      <result><party>BC NDP</party><percent>37</percent></result>
    </results>
    <source_url>https://…</source_url>                 <!-- required -->
    <source_name>Pollster release, Oct. 9, 2026</source_name>
    <note>Anything a reader should know: where a figure came from, what the poll doesn't report.</note>
  </poll>
</polls>
<projections>
  <projection>
    <riding>Salmon Arm-Shuswap</riding>                <!-- required; as Elections BC prints it -->
    <rating>safe</rating>                              <!-- the model's own label: safe | likely | leaning | toss up -->
    <leader>Conservative Party</leader>                <!-- defaults to the largest share -->
    <as_of>2026-10-11</as_of>                          <!-- required -->
    <source_url>https://338canada.com/bc/1064e.htm</source_url>
    <shares><share><party>Conservative Party</party><percent>55</percent><margin>8</margin></share></shares>
  </projection>
</projections>
```

The dashboard averages **each pollster's latest poll that ended in the field within 14 days of the newest poll on file**, unweighted; a party a pollster didn't report is averaged over those that did. Projections are modelled shares, not polls, and are labelled so on the page.

## Site change log (`<changelog>`)

What changed on the site, in plain language, for the change-log page and the "What's new on the site" section of the update email. **Written by hand, very briefly (a title and a sentence), never copied from commit messages.** Not tied to one election; it can sit in any file.

```xml
<changelog>
  <entry>
    <key>party-pages</key>                              <!-- required; matched on this, so a re-import updates it -->
    <committed_at>2026-10-11T22:32:50-07:00</committed_at>   <!-- required: when the change went in (the commit's time) -->
    <title>Party pages</title>                          <!-- required, a few words -->
    <body>Each party gets a page: leadership, record, news and a fact-check of its big claims.</body>   <!-- required, one sentence -->
    <scope>provincial</scope>                           <!-- both (default) | provincial | municipal -->
    <commits>c48c4bd</commits>                          <!-- optional, for the maintainer; never shown -->
  </entry>
</changelog>
```

The update email announces the entries whose `committed_at` falls **between the last email and the current one**, so give each the time of the latest commit behind it. Only user-visible changes belong; skip tests, skills and refactors. When committing user-visible work, add its entry in the same pass (the host's `database/elections/` folder, next file number).

## Corrections (`<changes>`)

The rest of the format only adds and updates. For the few corrections it can't express, put a
`<changes>` block in the file; it runs after everything else in the same file:

```xml
<changes>
  <!-- Take statements off a plank (or every plank with "*"), optionally only ones published
       before a date, and refile them as another kind (default statement). E.g. an incumbent's
       pre-campaign remarks belong in their record, not their platform. -->
  <detach_entries candidate="Alan Harrison" plank="*" before="2026-08-25" kind="prior_record"/>
  <!-- Delete a plank. Statements still on it are refiled as statements, never deleted, and
       the candidate's remaining ranks close up. -->
  <remove_plank candidate="Alan Harrison" key="rec-facility-not-now"/>
  <!-- Delete one entry, by its match_hash (from the baseline query). -->
  <remove_entry candidate="Robin Wiens" hash="…"/>
</changes>
```

Unknown candidates, planks or hashes are reported as problems, never guessed at. Never edit the
database directly: a change that isn't in a file won't reach staging.

## Saving and importing

Research files live in the private app repo, in `database/elections/` (in
`/Users/shawn/Documents/code/cultpantry`), and travel with every push:

1. **Name** the file `YYYY-MM-DD-NN-<what>.xml`, where `NN` is the next two-digit sequence number
   for that day (`ls database/elections`). Files are imported in name order, so a roster or a
   vocabulary must sort before the research that needs it.
2. **Back up** the local database: copy `database/database.sqlite` into the scratchpad.
3. **Dry run:** `php artisan elections:import-pending --dry-run` imports every pending file and
   rolls it all back. Fix every problem it reports.
4. **Import:** `php artisan elections:import-pending`, then report the summary in the hand-off.
   Each file is all-or-nothing, the run stops at the first failure, and the ledger
   (`elections_imports`) records each file by content hash.
5. **Commit** the file with the rest of the work. On deploy, staging runs the same command (from
   the deploy script, and hourly), so committed files reach staging on their own.

Never edit a file after it's imported unless you mean to re-import it: a changed file gets a new
hash and is imported again (harmless for adds and updates, since the import matches what's on
file). Files under `database/elections/archive/` are history only and are never imported.
`elections:import` (one named file) still exists for one-off checks; don't use it for research
that should reach staging.
