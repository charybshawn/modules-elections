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
| event `kind` | `forum`, `deadline`, `advance_voting`, `general_voting`, `other` |

## Import behaviour (what to rely on)

- **Candidates match on `<name>`, exactly.** Spell a returning candidate's
  name exactly as it's on file (the baseline query shows it), or the import
  creates a second candidate.
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
