<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\BillNote;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Election;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Party;
use Cultpantry\Elections\Models\PartyArticle;
use Cultpantry\Elections\Models\PartyClaim;
use Cultpantry\Elections\Models\Plank;
use Cultpantry\Elections\Models\Poll;
use Cultpantry\Elections\Models\RidingProjection;
use Cultpantry\Elections\Models\PulseIssue;
use Cultpantry\Elections\Models\PulseSnapshot;
use Cultpantry\Elections\Models\Scorecard;
use Cultpantry\Elections\Models\Sponsorship;
use Cultpantry\Elections\Models\ScorecardAnswer;
use Cultpantry\Elections\Models\ScorecardItem;
use Cultpantry\Elections\Models\ScorecardResponse;
use Cultpantry\Elections\Models\Tag;
use Cultpantry\Elections\Models\Vote;
use Cultpantry\Elections\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;

/**
 * Imports an <election> XML file -- the format the
 * research-salmon-arm-candidates skill writes (see its
 * references/elections-xml-schema.md) and ExportElectionToXml produces.
 *
 * Match keys: candidates on name, entries on (candidate, source URL + quote
 * or summary), planks on (candidate, key), articles on URL, events on
 * (title, starts_at), Community Pulse snapshots on taken_on, scorecards on
 * key (their categories and statements on key within them, a candidate's
 * response on the candidate). Re-importing the same file is a no-op --
 * except that a pulse snapshot is replaced as a whole (its issues and
 * mentions) each time its date is imported, and a scorecard response's
 * <answers> or <takeaways>, when supplied, replace what's on file.
 *
 * Unlike the market import, a candidate field left out of the file is left
 * alone rather than cleared: research passes are incremental (one finds the
 * bio, a later one the Instagram), and a pass that didn't look for
 * something shouldn't wipe what an earlier pass found. Nothing is ever
 * deleted by an import either -- removing an entry is a deliberate act on
 * the candidate page.
 *
 * Anything that can't be imported as written (no source, unknown office,
 * an article naming a candidate who isn't on file) is skipped or defaulted
 * and reported back in 'problems', never guessed at.
 */
class ImportElectionFromXml
{
    private const CANDIDATE_FIELDS = [
        'party', 'occupation', 'bio', 'bio_source_url', 'website', 'facebook_url',
        'instagram_url', 'email', 'phone', 'photo_url', 'photo_credit', 'notes',
    ];

    private const URL_FIELDS = ['bio_source_url', 'website', 'facebook_url', 'instagram_url', 'photo_url'];

    /** @var array<int, string> */
    private array $problems = [];

    /** The election this file is for: its root's id, else the municipal one every file was before. */
    private Election $election;

    /**
     * @return array{candidates: array{created: int, updated: int, unchanged: int}, entries: array{created: int, updated: int, unchanged: int}, planks: array{created: int, updated: int, unchanged: int}, articles: array{created: int, updated: int, unchanged: int}, events: array{created: int, updated: int, unchanged: int}, problems: array<int, string>}
     */
    public function handle(UploadedFile $file, ?string $source = null): array
    {
        return $this->handleString((string) file_get_contents($file->getRealPath()), $source ?? $file->getClientOriginalName());
    }

    /**
     * @return array{candidates: array{created: int, updated: int, unchanged: int}, entries: array{created: int, updated: int, unchanged: int}, planks: array{created: int, updated: int, unchanged: int}, articles: array{created: int, updated: int, unchanged: int}, events: array{created: int, updated: int, unchanged: int}, problems: array<int, string>}
     */
    public function handleString(string $contents, ?string $source = null): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents);

        if ($xml === false) {
            $errors = collect(libxml_get_errors())->pluck('message')->map(trim(...))->implode('; ');
            libxml_clear_errors();
            throw new RuntimeException($errors !== '' ? "Invalid XML: {$errors}" : 'Invalid XML: could not be parsed.');
        }

        if ($xml->getName() !== 'election') {
            throw new RuntimeException("Invalid XML: expected an <election> root element, found <{$xml->getName()}>.");
        }

        $id = trim((string) $xml['id']);
        $this->election = $id === '' ? Election::municipal() : (Election::bySlug($id)
            ?? throw new RuntimeException("Unknown election id \"{$id}\" on the <election> element."));

        $this->problems = [];
        $counts = fn () => ['created' => 0, 'updated' => 0, 'unchanged' => 0];
        $result = [
            'candidates' => $counts(),
            'entries' => $counts(),
            'votes' => $counts(),
            'bill_notes' => $counts(),
            'parties' => $counts(),
            'party_claims' => $counts(),
            'party_news' => $counts(),
            'party_slate' => $counts(),
            'polls' => $counts(),
            'projections' => $counts(),
            'sponsored' => $counts(),
            'planks' => $counts(),
            'articles' => $counts(),
            'events' => $counts(),
            'pulse' => $counts(),
            'scorecards' => $counts(),
            'responses' => $counts(),
            'tags' => $counts(),
            'tagged' => $counts(),
            'plans' => $counts(),
            'analyses' => $counts(),
            'changes' => $counts(),
        ];

        DB::transaction(function () use ($xml, &$result) {
            // Parties, so a candidate's party can be matched to its profile (provincial only).
            foreach ($xml->parties->party ?? [] as $node) {
                $this->importParty($node, $result);
            }
            // Province-wide polls and the model's riding projections (provincial only).
            foreach ($xml->polls->poll ?? [] as $node) {
                $this->importPoll($node, $result);
            }
            foreach ($xml->projections->projection ?? [] as $node) {
                $this->importProjection($node, $result);
            }
            // The vocabulary first, so everything below can use new tags.
            foreach ($xml->vocabulary->tag ?? [] as $node) {
                $this->importTag($node, $result);
            }
            foreach ($xml->candidates->candidate ?? [] as $node) {
                $this->importCandidate($node, $result);
            }
            foreach ($xml->articles->article ?? [] as $node) {
                $this->importArticle($node, $result);
            }
            foreach ($xml->events->event ?? [] as $node) {
                $this->importEvent($node, $result);
            }
            // Pulse snapshots and scorecards are municipal-only for now.
            if ($this->election->isProvincial()) {
                if (isset($xml->pulse) || isset($xml->scorecards)) {
                    $this->problems[] = '<pulse> and <scorecards> are not supported for the provincial election yet -- skipped.';
                }
            } else {
                foreach ($xml->pulse ?? [] as $node) {
                    $this->importPulse($node, $result);
                }
                foreach ($xml->scorecards->scorecard ?? [] as $node) {
                    $this->importScorecard($node, $result);
                }
            }
            foreach ($xml->tagging ?? [] as $node) {
                $this->importTagging($node, $result);
            }
            foreach ($xml->plans->plan ?? [] as $node) {
                $this->importPlan($node, $result);
            }
            foreach ($xml->analyses->analysis ?? [] as $node) {
                $this->importAnalysis($node, $result);
            }
            // Corrections last, so they apply to what this file just loaded too.
            foreach ($xml->changes ?? [] as $node) {
                $this->applyChanges($node, $result);
            }
        });

        Tag::pruneOrphans();

        $result['problems'] = $this->problems;

        // One audit entry per import (not per record). Inside a dry run's
        // transaction it's rolled back with everything else.
        Audit::record('elections.import', 'Elections research imported'.($source ? ": {$source}" : ''), null, [
            'file' => $source,
            'hash' => hash('sha256', $contents),
            'summary' => $this->summarize([...$result, 'problems' => []]),
            'problems' => count($result['problems']),
        ], $result['problems'] === [] ? 'info' : 'warning');

        return $result;
    }

    /**
     * One flash-sized sentence per kind of record, then the problems.
     */
    public function summarize(array $result): string
    {
        $parts = [];
        foreach (['tags', 'parties', 'party_claims', 'party_news', 'party_slate', 'polls', 'projections', 'candidates', 'entries', 'votes', 'bill_notes', 'sponsored', 'planks', 'articles', 'events', 'pulse', 'scorecards', 'responses', 'tagged', 'plans', 'analyses'] as $type) {
            ['created' => $created, 'updated' => $updated, 'unchanged' => $unchanged] = $result[$type];
            if ($created + $updated + $unchanged === 0) {
                continue;
            }
            $parts[] = "{$type}: {$created} new, {$updated} updated, {$unchanged} unchanged";
        }

        $changes = $result['changes'] ?? ['updated' => 0];
        if ($changes['updated'] > 0) {
            $parts[] = "changes: {$changes['updated']} applied";
        }

        $message = $parts === [] ? 'Nothing to import.' : 'Imported '.implode('; ', $parts).'.';

        if ($result['problems'] !== []) {
            $shown = array_slice($result['problems'], 0, 5);
            $more = count($result['problems']) - count($shown);
            $message .= ' Problems: '.implode(' ', $shown).($more > 0 ? " (+{$more} more)" : '');
        }

        return $message;
    }

    private function importCandidate(SimpleXMLElement $node, array &$result): void
    {
        $name = $this->text($node, 'name');
        if ($name === null) {
            $this->problems[] = 'Skipped a candidate with no <name>.';

            return;
        }

        $candidate = Candidate::forElection($this->election)->where('name', $name)->first() ?? new Candidate(['name' => $name, 'election_id' => $this->election->id]);

        $office = $this->text($node, 'office');
        if ($office !== null && ! array_key_exists($office, $this->election->offices())) {
            $this->problems[] = "{$name}: unknown office \"{$office}\".";
            $office = null;
        }
        if ($office !== null) {
            $candidate->office = $office;
        }
        if (! $candidate->exists && $candidate->office === null) {
            $this->problems[] = "Skipped new candidate {$name}: no valid <office> (".implode(', ', array_keys($this->election->offices())).").";

            return;
        }

        $status = $this->text($node, 'status');
        if ($status !== null && ! array_key_exists($status, Candidate::STATUSES)) {
            $this->problems[] = "{$name}: unknown status \"{$status}\", left as it was.";
            $status = null;
        }
        if ($status !== null) {
            $candidate->status = $status;
        } elseif (! $candidate->exists) {
            $candidate->status = 'declared';
        }

        $incumbent = $this->bool($node, 'is_incumbent');
        if ($incumbent !== null) {
            $candidate->is_incumbent = $incumbent;
        }

        foreach (self::CANDIDATE_FIELDS as $field) {
            $value = $this->text($node, $field);
            if ($value === null) {
                continue;
            }
            // A photo may also be a file in the app itself (/images/...).
            $isLocalPhoto = $field === 'photo_url' && preg_match('#^/[^/\s][^\s]*$#', $value);
            if (in_array($field, self::URL_FIELDS, true) && ! $this->isHttpUrl($value) && ! $isLocalPhoto) {
                $this->problems[] = "{$name}: <{$field}> isn't an http(s) URL, left as it was.";

                continue;
            }
            $candidate->{$field} = $value;
        }

        if ($candidate->bio !== null && $candidate->bio_source_url === null) {
            $this->problems[] = "{$name}: bio has no <bio_source_url> -- add one.";
        }

        $this->tally($candidate, $result['candidates']);

        foreach ($node->entries->entry ?? [] as $entryNode) {
            $this->importEntry($candidate, $entryNode, $result);
        }

        foreach ($node->planks->plank ?? [] as $plankNode) {
            $this->importPlank($candidate, $plankNode, $result);
        }

        foreach ($node->votes->vote ?? [] as $voteNode) {
            $this->importVote($candidate, $voteNode, $result);
        }

        foreach ($node->bill_notes->bill_note ?? [] as $noteNode) {
            $this->importBillNote($candidate, $noteNode, $result);
        }

        foreach ($node->sponsored->item ?? [] as $itemNode) {
            $this->importSponsorship($candidate, $itemNode, $result);
        }
    }

    /**
     * A bill or motion the candidate introduced: <sponsored><item><kind>bill</kind><reference>Bill M246</reference>
     * <session>43rd Parliament, 2nd session</session><title>Small Farms Act</title><summary>…</summary>
     * <introduced_on>2026-05-21</introduced_on><status>introduced</status><status_note>…</status_note>
     * <context>…</context><source_url>…</source_url><source_name>…</source_name></item></sponsored>.
     * The same kind, session and reference is the same item.
     */
    private function importSponsorship(Candidate $candidate, SimpleXMLElement $node, array &$result): void
    {
        $label = "{$candidate->name}: sponsored item";
        $kind = $this->text($node, 'kind');
        $reference = $this->text($node, 'reference');
        $session = $this->text($node, 'session');
        $title = $this->text($node, 'title');
        $status = $this->text($node, 'status');
        $sourceUrl = $this->text($node, 'source_url');

        if (! array_key_exists((string) $kind, Sponsorship::KINDS) || $reference === null || $session === null || $title === null) {
            $this->problems[] = "{$label}: skipped an item with no valid <kind> (bill or motion), <reference>, <session> or <title>.";

            return;
        }
        if (! array_key_exists((string) $status, Sponsorship::STATUSES)) {
            $this->problems[] = "{$label} \"{$reference}\": unknown or missing <status> \"{$status}\", skipped.";

            return;
        }
        if ($sourceUrl === null || ! $this->isHttpUrl($sourceUrl)) {
            $this->problems[] = "{$label} \"{$reference}\": no valid <source_url>, skipped.";

            return;
        }

        $item = $candidate->sponsorships()->where(['kind' => $kind, 'session' => $session, 'reference' => $reference])->first()
            ?? $candidate->sponsorships()->make();
        $item->fill([
            'kind' => $kind,
            'reference' => $reference,
            'session' => $session,
            'title' => $title,
            'summary' => $this->text($node, 'summary'),
            'introduced_on' => $this->date($node, 'introduced_on', $label),
            'status' => $status,
            'status_note' => $this->text($node, 'status_note'),
            'context' => $this->text($node, 'context'),
            'source_url' => $sourceUrl,
            'source_name' => $this->text($node, 'source_name'),
        ]);

        $this->tally($item, $result['sponsored']);
    }

    /**
     * The synopses under one bill: <bill_note><bill_key>43rd1st:Bill 7</bill_key><purpose>…</purpose>
     * <vote_analysis>…</vote_analysis><basis>spoke</basis><sources><source><label>…</label><url>…</url></source></sources>
     * </bill_note>. A note needs a bill the candidate has votes on, and at least one of the two synopses.
     */
    private function importBillNote(Candidate $candidate, SimpleXMLElement $node, array &$result): void
    {
        $label = "{$candidate->name}: bill note";
        $key = $this->text($node, 'bill_key');
        $purpose = $this->text($node, 'purpose');
        $analysis = $this->text($node, 'vote_analysis');

        if ($key === null || ($purpose === null && $analysis === null)) {
            $this->problems[] = "{$label}: skipped a note with no <bill_key> or no synopsis.";

            return;
        }
        if (! $candidate->votes()->where('bill_key', $key)->exists()) {
            $this->problems[] = "{$label} \"{$key}\": no vote on that bill is on file for {$candidate->name}; import the votes first. Skipped.";

            return;
        }

        $basis = $this->text($node, 'basis');
        if ($basis !== null && ! array_key_exists($basis, BillNote::BASES)) {
            $this->problems[] = "{$label} \"{$key}\": unknown basis \"{$basis}\", left out.";
            $basis = null;
        }

        $sources = [];
        foreach ($node->sources->source ?? [] as $s) {
            $url = $this->text($s, 'url');
            if ($url === null || ! $this->isHttpUrl($url)) {
                $this->problems[] = "{$label} \"{$key}\": a source with no valid <url>, left out.";

                continue;
            }
            $sources[] = ['label' => $this->text($s, 'label') ?? $url, 'url' => $url];
        }

        $note = $candidate->billNotes()->where('bill_key', $key)->first() ?? $candidate->billNotes()->make();
        $note->fill([
            'bill_key' => $key,
            'purpose' => $purpose,
            'vote_analysis' => $analysis,
            'basis' => $basis,
            'sources' => $sources === [] ? null : $sources,
        ]);

        $this->tally($note, $result['bill_notes']);
    }

    /**
     * One recorded vote: <vote><body>legislature</body><voted_on>2025-04-15</voted_on>
     * <title>…</title><reference>Bill 7</reference><bill_key>43rd1st:Bill 7</bill_key><bill_title>Bill 7: …</bill_title>
     * <stage>third_reading</stage><description>the record's wording</description>
     * <vote>yea</vote><outcome>passed</outcome><tally_for>46</tally_for><tally_against>43</tally_against>
     * <source_url>…</source_url><source_name>…</source_name><tags>…</tags></vote>.
     * The same record, day, question and wording is the same vote.
     */
    private function importVote(Candidate $candidate, SimpleXMLElement $node, array &$result): void
    {
        $label = "{$candidate->name}: vote";
        $title = $this->text($node, 'title');
        $sourceUrl = $this->text($node, 'source_url');
        $votedOn = $this->date($node, 'voted_on', $label);
        $how = $this->text($node, 'vote');
        $body = $this->text($node, 'body');

        if ($title === null || $votedOn === null) {
            $this->problems[] = "{$label}: skipped a vote with no <title> or a valid <voted_on>.";

            return;
        }
        if ($sourceUrl === null || ! $this->isHttpUrl($sourceUrl)) {
            $this->problems[] = "{$label} \"".mb_strimwidth($title, 0, 60, '…').'" skipped -- no http(s) <source_url>.';

            return;
        }
        if (! array_key_exists((string) $how, Vote::VOTES)) {
            $this->problems[] = "{$label} \"".mb_strimwidth($title, 0, 60, '…')."\" skipped -- <vote> must be one of: ".implode(', ', array_keys(Vote::VOTES)).'.';

            return;
        }
        if (! array_key_exists((string) $body, Vote::BODIES)) {
            $this->problems[] = "{$label} \"".mb_strimwidth($title, 0, 60, '…')."\" skipped -- <body> must be one of: ".implode(', ', array_keys(Vote::BODIES)).'.';

            return;
        }

        $outcome = $this->text($node, 'outcome');
        if ($outcome !== null && ! array_key_exists($outcome, Vote::OUTCOMES)) {
            $this->problems[] = "{$label} \"".mb_strimwidth($title, 0, 60, '…')."\": unknown outcome \"{$outcome}\", left out.";
            $outcome = null;
        }

        $stage = $this->text($node, 'stage');
        if ($stage !== null && ! array_key_exists($stage, Vote::STAGES)) {
            $this->problems[] = "{$label} \"".mb_strimwidth($title, 0, 60, '…')."\": unknown stage \"{$stage}\", left out.";
            $stage = null;
        }

        $reference = $this->text($node, 'reference');
        $description = $this->text($node, 'description');
        $hash = Vote::hashFor($sourceUrl, $votedOn, $reference, $description ?? $title);
        $vote = $candidate->votes()->where('match_hash', $hash)->first() ?? $candidate->votes()->make();

        $vote->fill([
            'body' => $body,
            'voted_on' => $votedOn,
            'title' => $title,
            'reference' => $reference,
            'bill_key' => $this->text($node, 'bill_key'),
            'bill_title' => $this->text($node, 'bill_title'),
            'stage' => $stage,
            'description' => $description,
            'vote' => $how,
            'outcome' => $outcome,
            'tally_for' => $this->positiveInt($node, 'tally_for'),
            'tally_against' => $this->positiveInt($node, 'tally_against'),
            'source_url' => $sourceUrl,
            'source_name' => $this->text($node, 'source_name'),
        ]);

        $this->tally($vote, $result['votes']);
        $this->syncTags($vote, $node, "{$label} \"".mb_strimwidth($title, 0, 50, '…').'"', $result);
    }

    /**
     * A plank and the statements it rests on. Its <entries> are imported as
     * plank-kind entries linked to it (whatever <kind> they give), so a
     * statement already on file just gets linked rather than duplicated.
     */
    private function importPlank(Candidate $candidate, SimpleXMLElement $node, array &$result): void
    {
        $label = $candidate->name;
        $key = $this->text($node, 'key');
        $title = $this->text($node, 'title');

        if ($key === null || $title === null) {
            $this->problems[] = "{$label}: skipped a plank missing a <key> or <title>.";

            return;
        }

        $topic = $this->text($node, 'topic') ?? 'other';
        if (! array_key_exists($topic, Entry::TOPICS)) {
            $this->problems[] = "{$label}: plank \"{$title}\" has unknown topic \"{$topic}\", filed under \"other\".";
            $topic = 'other';
        }

        $tier = $this->text($node, 'tier') ?? 'mentioned';
        if (! array_key_exists($tier, Plank::TIERS)) {
            $this->problems[] = "{$label}: plank \"{$title}\" has unknown tier \"{$tier}\", filed as \"mentioned\".";
            $tier = 'mentioned';
        }

        $plank = $candidate->planks()->where('key', $key)->first() ?? $candidate->planks()->make(['key' => $key]);
        $plank->fill([
            'title' => $title,
            'topic' => $topic,
            'summary' => $this->text($node, 'summary'),
            'tier' => $tier,
            'rank' => $this->positiveInt($node, 'rank') ?? 999,
            'rationale' => $this->text($node, 'rationale'),
            'priority_position' => $this->positiveInt($node, 'priority_position'),
            'has_commitment' => (bool) $this->bool($node, 'has_commitment'),
        ]);

        if ($plank->rationale === null) {
            $this->problems[] = "{$label}: plank \"{$title}\" has no <rationale> -- say why it ranks where it does.";
        }

        if (isset($node->plan)) {
            $this->applyPlan($plank, $node->plan, "{$label}: plank \"{$title}\"");
        }
        if (isset($node->analysis)) {
            $this->applyAnalysis($plank, $node->analysis, "{$label}: plank \"{$title}\"");
        }

        $this->tally($plank, $result['planks']);
        $this->syncTags($plank, $node, "{$candidate->name}: plank \"{$plank->title}\"", $result);

        $sources = 0;
        foreach ($node->entries->entry ?? [] as $entryNode) {
            $sources += (int) $this->importEntry($candidate, $entryNode, $result, $plank);
        }
        if ($sources === 0 && $plank->entries()->doesntExist()) {
            $this->problems[] = "{$label}: plank \"{$title}\" has no sourced statement behind it.";
        }
    }

    /**
     * @return bool whether the entry was imported (not skipped)
     */
    private function importEntry(Candidate $candidate, SimpleXMLElement $node, array &$result, ?Plank $plank = null): bool
    {
        $label = $candidate->name;
        $summary = $this->text($node, 'summary');
        $sourceUrl = $this->text($node, 'source_url');

        if ($summary === null) {
            $this->problems[] = "{$label}: skipped an entry with no <summary>.";

            return false;
        }
        if ($sourceUrl === null || ! $this->isHttpUrl($sourceUrl)) {
            $this->problems[] = "{$label}: skipped \"".mb_strimwidth($summary, 0, 60, '…').'" -- no http(s) <source_url>.';

            return false;
        }

        // A plank's statements are plank entries whatever the file says.
        $kind = $plank !== null ? 'plank' : $this->text($node, 'kind');
        if (! array_key_exists((string) $kind, Entry::KINDS)) {
            $this->problems[] = "{$label}: skipped an entry with unknown kind \"{$kind}\".";

            return false;
        }

        $sourceType = $this->text($node, 'source_type');
        if (! array_key_exists((string) $sourceType, Entry::SOURCE_TYPES)) {
            $this->problems[] = "{$label}: unknown source_type \"{$sourceType}\", filed as \"other\".";
            $sourceType = 'other';
        }

        $topic = $this->text($node, 'topic') ?? 'other';
        if (! array_key_exists($topic, Entry::topicsFor($kind))) {
            $this->problems[] = "{$label}: unknown topic \"{$topic}\", filed under \"other\".";
            $topic = 'other';
        }

        $quote = $this->text($node, 'quote');
        $hash = Entry::hashFor($sourceUrl, $quote, $summary);
        $entry = $candidate->entries()->where('match_hash', $hash)->first() ?? $candidate->entries()->make();

        $entry->fill([
            'kind' => $kind,
            'topic' => $topic,
            'summary' => $summary,
            'quote' => $quote,
            'question' => $this->text($node, 'question'),
            'source_url' => $sourceUrl,
            'source_type' => $sourceType,
            'source_name' => $this->text($node, 'source_name'),
            'published_on' => $this->date($node, 'published_on', $label),
        ]);
        if ($plank !== null) {
            $entry->plank_id = $plank->id;
        }

        $this->tally($entry, $result['entries']);
        $this->syncTags($entry, $node, "{$candidate->name}: entry", $result);

        return true;
    }

    private function importArticle(SimpleXMLElement $node, array &$result): void
    {
        $url = $this->text($node, 'url');
        $title = $this->text($node, 'title');

        if ($url === null || ! $this->isHttpUrl($url) || $title === null) {
            $this->problems[] = 'Skipped an article missing a <title> or an http(s) <url>.';

            return;
        }

        $article = Article::forElection($this->election)->where('url_hash', Article::hashFor($url))->first() ?? new Article(['url' => $url, 'election_id' => $this->election->id]);
        $article->fill([
            'title' => $title,
            'outlet' => $this->text($node, 'outlet') ?? $article->outlet,
            'published_on' => $this->date($node, 'published_on', $title) ?? $article->published_on,
            'summary' => $this->text($node, 'summary') ?? $article->summary,
        ]);

        $isNew = ! $article->exists;
        $changed = $article->isDirty();
        if ($isNew || $changed) {
            $article->save();
        }

        $this->syncTags($article, $node, "Article \"{$title}\"", $result);

        $ids = [];
        foreach ($node->candidates->candidate ?? [] as $nameNode) {
            $name = trim((string) $nameNode);
            $id = Candidate::forElection($this->election)->where('name', $name)->value('id');
            if ($id === null) {
                $this->problems[] = "Article \"{$title}\" names {$name}, who isn't on file -- not linked.";

                continue;
            }
            $ids[] = $id;
        }

        // Additive: a later pass that spots one more candidate in the same
        // story adds the link without needing to repeat the others.
        $attached = $article->candidates()->syncWithoutDetaching($ids)['attached'];

        $result['articles'][match (true) {
            $isNew => 'created',
            $changed || $attached !== [] => 'updated',
            default => 'unchanged',
        }]++;
    }

    private function importEvent(SimpleXMLElement $node, array &$result): void
    {
        $title = $this->text($node, 'title');
        $startsAt = $this->dateTime($node, 'starts_at', (string) $title);

        if ($title === null || $startsAt === null) {
            $this->problems[] = 'Skipped an event missing a <title> or a valid <starts_at>.';

            return;
        }

        $kind = $this->text($node, 'kind') ?? 'other';
        if (! array_key_exists($kind, ElectionEvent::KINDS)) {
            $this->problems[] = "Event \"{$title}\": unknown kind \"{$kind}\", filed as \"other\".";
            $kind = 'other';
        }

        $url = $this->text($node, 'url');
        if ($url !== null && ! $this->isHttpUrl($url)) {
            $this->problems[] = "Event \"{$title}\": <url> isn't an http(s) URL, left out.";
            $url = null;
        }

        $event = ElectionEvent::forElection($this->election)->where('title', $title)->where('starts_at', $startsAt)->first()
            ?? new ElectionEvent(['title' => $title, 'starts_at' => $startsAt, 'election_id' => $this->election->id]);

        $event->fill([
            'kind' => $kind,
            'ends_at' => $this->dateTime($node, 'ends_at', $title),
            'location' => $this->text($node, 'location'),
            'url' => $url,
            'description' => $this->text($node, 'description'),
        ]);

        $this->tally($event, $result['events']);
    }

    /**
     * A Community Pulse snapshot. It's one summary of one reading pass, so a
     * re-import of the same date replaces its issues and mentions wholesale
     * rather than merging -- a later pass that drops an issue should drop it.
     */
    private function importPulse(SimpleXMLElement $node, array &$result): void
    {
        $takenOn = $this->date($node, 'taken_on', 'Pulse snapshot');
        if ($takenOn === null) {
            $this->problems[] = 'Skipped a <pulse> snapshot with no valid <taken_on> (YYYY-MM-DD).';

            return;
        }

        $snapshot = PulseSnapshot::whereDate('taken_on', $takenOn)->first();
        $isNew = $snapshot === null;
        $snapshot ??= new PulseSnapshot(['taken_on' => $takenOn]);

        $conclusions = [];
        foreach ($node->conclusions->conclusion ?? [] as $c) {
            $text = trim((string) $c);
            if ($text !== '') {
                $conclusions[] = ['text' => $text, 'issue' => ((string) $c['issue']) ?: null];
            }
        }

        $snapshot->fill([
            'period_from' => $this->date($node, 'period_from', 'Pulse snapshot'),
            'period_to' => $this->date($node, 'period_to', 'Pulse snapshot'),
            'threads_read' => (int) $this->text($node, 'threads_read'),
            'commenters' => (int) $this->text($node, 'commenters'),
            'sources' => $this->text($node, 'sources'),
            'method_note' => $this->text($node, 'method_note'),
            'conclusions' => $conclusions,
        ]);
        $snapshot->save();

        // Issues and mentions are rebuilt on every import, which stamps fresh
        // rows even when nothing changed. Remember what was there, so a
        // re-import of identical content doesn't read as an update.
        $before = $isNew ? null : $this->pulseContent($snapshot);

        $snapshot->issues()->delete();
        $snapshot->mentions()->delete();

        $keys = [];
        foreach ($node->issues->issue ?? [] as $i) {
            $key = $this->text($i, 'key');
            $title = $this->text($i, 'title');
            if ($key === null || $title === null || isset($keys[$key])) {
                $this->problems[] = 'Pulse '.$takenOn.': skipped an issue with no <key>/<title> or a repeated key.';

                continue;
            }
            $keys[$key] = true;

            $topic = $this->text($i, 'topic') ?? 'other';
            if (! array_key_exists($topic, Entry::TOPICS)) {
                $this->problems[] = "Pulse {$takenOn}: issue \"{$title}\" has unknown topic \"{$topic}\", filed under \"other\".";
                $topic = 'other';
            }
            $heat = $this->text($i, 'heat') ?? 'medium';
            if (! array_key_exists($heat, PulseIssue::HEAT)) {
                $heat = 'medium';
            }
            $pct = fn (string $child) => ($v = $this->text($i, $child)) === null ? null : max(0, min(100, (int) $v));

            $issue = $snapshot->issues()->create([
                'key' => $key,
                'title' => $title,
                'topic' => $topic,
                'voices' => (int) $this->text($i, 'voices'),
                'support_pct' => $pct('support_pct'),
                'oppose_pct' => $pct('oppose_pct'),
                'mixed_pct' => $pct('mixed_pct'),
                'heat' => $heat,
                'summary' => $this->text($i, 'summary'),
                'wants' => $this->list($i->wants, 'want'),
                'questions' => $this->list($i->questions, 'question'),
            ]);
            $this->syncTags($issue, $i, "Pulse {$takenOn}: issue \"{$title}\"", $result);
        }

        foreach ($node->mentions->mention ?? [] as $m) {
            $name = $this->text($m, 'candidate');
            $candidateId = $name === null ? null : Candidate::forElection($this->election)->where('name', $name)->value('id');
            if ($candidateId === null) {
                $this->problems[] = "Pulse {$takenOn}: mention of \"{$name}\", who isn't on file -- skipped.";

                continue;
            }
            $snapshot->mentions()->updateOrCreate(['candidate_id' => $candidateId], [
                'mentions' => (int) $this->text($m, 'mentions'),
                'commenters' => (int) $this->text($m, 'commenters'),
            ]);
        }

        // The snapshot's own updated_at is what the update feed reads: bump it
        // only when its issues or mentions really changed.
        if ($before !== null && $before !== $this->pulseContent($snapshot)) {
            $snapshot->touch();
        }

        $result['pulse'][$isNew ? 'created' : 'updated']++;
    }

    /**
     * A snapshot's issues and mentions as comparable text, ignoring row ids
     * and timestamps.
     */
    private function pulseContent(PulseSnapshot $snapshot): string
    {
        $issues = $snapshot->issues()->with('tags')->get()->sortBy('key')->map(fn (PulseIssue $i) => [
            $i->key, $i->title, $i->topic, $i->voices, $i->support_pct, $i->oppose_pct, $i->mixed_pct, $i->heat, $i->summary,
            $i->wants, $i->questions, $i->tags->pluck('slug')->sort()->values()->all(),
        ])->values()->all();
        $mentions = $snapshot->mentions()->get()->sortBy('candidate_id')->map(fn ($m) => [$m->candidate_id, $m->mentions, $m->commenters])->values()->all();

        return json_encode([$issues, $mentions]);
    }

    /**
     * @return array<int, string>
     */
    /**
     * A scorecard: its fields, then categories and their statements (matched
     * on key, in file order), then each candidate's response. Nothing missing
     * from the file is deleted -- a statement the publisher dropped has to be
     * removed by hand.
     */
    private function importScorecard(SimpleXMLElement $node, array &$result): void
    {
        $key = $this->text($node, 'key');
        if ($key === null) {
            $this->problems[] = 'Skipped a <scorecard> with no <key>.';

            return;
        }

        $scorecard = Scorecard::firstOrNew(['key' => $key]);
        foreach (['title', 'publisher', 'about'] as $field) {
            if (($value = $this->text($node, $field)) !== null) {
                $scorecard->{$field} = $value;
            }
        }
        if (($url = $this->text($node, 'url')) !== null) {
            if ($this->isHttpUrl($url)) {
                $scorecard->url = $url;
            } else {
                $this->problems[] = "Scorecard {$key}: <url> isn't an http(s) URL.";
            }
        }
        if (($retrieved = $this->date($node, 'retrieved_on', "Scorecard {$key}")) !== null) {
            $scorecard->retrieved_on = $retrieved;
        }
        if ($scorecard->title === null) {
            $this->problems[] = "Skipped new scorecard {$key}: no <title>.";

            return;
        }
        $this->tally($scorecard, $result['scorecards']);

        $categories = [];
        $items = [];
        foreach ($node->categories->category ?? [] as $position => $c) {
            $catKey = $this->text($c, 'key');
            $name = $this->text($c, 'name');
            if ($catKey === null || $name === null) {
                $this->problems[] = "Scorecard {$key}: skipped a category with no <key>/<name>.";

                continue;
            }
            $category = $scorecard->categories()->firstOrNew(['key' => $catKey]);
            $category->fill([
                'name' => $name,
                'intro' => $this->text($c, 'intro') ?? $category->intro,
                'local_context' => $this->text($c, 'local_context') ?? $category->local_context,
                'position' => count($categories),
            ]);
            if (isset($c->sources)) {
                $sources = array_values(array_filter($this->list($c->sources, 'source'), $this->isHttpUrl(...)));
                $category->sources = $sources === [] ? null : $sources;
            }
            $category->save();
            $categories[$catKey] = $category;

            foreach ($c->items->item ?? [] as $i) {
                $itemKey = $this->text($i, 'key');
                $statement = $this->text($i, 'statement');
                if ($itemKey === null || $statement === null || isset($items[$itemKey])) {
                    $this->problems[] = "Scorecard {$key}: skipped a statement with no <key>/<statement> or a key already used.";

                    continue;
                }
                $items[$itemKey] = $category->items()->updateOrCreate(['key' => $itemKey], [
                    'statement' => $statement,
                    'position' => count(array_filter($items, fn ($item) => $item->category_id === $category->id)),
                ]);
                $this->syncTags($items[$itemKey], $i, "Scorecard {$key}: statement \"{$itemKey}\"", $result);
            }
        }

        // Answers can name statements from an earlier import of this scorecard.
        foreach ($scorecard->categories()->with('items')->get() as $category) {
            $categories[$category->key] ??= $category;
            foreach ($category->items as $item) {
                $items[$item->key] ??= $item;
            }
        }

        foreach ($node->responses->response ?? [] as $r) {
            $this->importScorecardResponse($scorecard, $r, $categories, $items, $result);
        }
    }

    /**
     * @param  array<string, \Cultpantry\Elections\Models\ScorecardCategory>  $categories
     * @param  array<string, \Cultpantry\Elections\Models\ScorecardItem>  $items
     */
    private function importScorecardResponse(Scorecard $scorecard, SimpleXMLElement $node, array $categories, array $items, array &$result): void
    {
        $name = $this->text($node, 'candidate');
        $candidateId = $name === null ? null : Candidate::forElection($this->election)->where('name', $name)->value('id');
        if ($candidateId === null) {
            $this->problems[] = "Scorecard {$scorecard->key}: response from \"{$name}\", who isn't on file -- skipped.";

            return;
        }

        /** @var ScorecardResponse $response */
        $response = $scorecard->responses()->firstOrNew(['candidate_id' => $candidateId]);
        if (($url = $this->text($node, 'source_url')) !== null) {
            if ($this->isHttpUrl($url)) {
                $response->source_url = $url;
            } else {
                $this->problems[] = "{$name}: scorecard <source_url> isn't an http(s) URL.";
            }
        }
        $responded = $this->bool($node, 'responded');
        $response->responded = $responded ?? $response->responded ?? true;
        $changed = ! $response->exists || $response->isDirty();
        $response->save();

        if (isset($node->answers)) {
            $before = $response->answers()->orderBy('item_id')->pluck('stance', 'item_id')->all();
            $after = [];
            foreach ($node->answers->answer ?? [] as $a) {
                $itemKey = (string) $a['item'];
                $stance = trim((string) $a);
                if (! isset($items[$itemKey])) {
                    $this->problems[] = "{$name}: answer to unknown statement \"{$itemKey}\" -- skipped.";

                    continue;
                }
                if (! array_key_exists($stance, ScorecardAnswer::STANCES)) {
                    $this->problems[] = "{$name}: unknown stance \"{$stance}\" on \"{$itemKey}\" -- skipped.";

                    continue;
                }
                $after[$items[$itemKey]->id] = $stance;
            }
            ksort($after);
            if ($after !== $before) {
                $response->answers()->delete();
                foreach ($after as $itemId => $stance) {
                    $response->answers()->create(['item_id' => $itemId, 'stance' => $stance]);
                }
                $changed = true;
            }
        }

        if (isset($node->takeaways)) {
            $before = $response->takeaways()->orderBy('category_id')->pluck('summary', 'category_id')->all();
            $after = [];
            foreach ($node->takeaways->takeaway ?? [] as $t) {
                $catKey = (string) $t['category'];
                $summary = trim((string) $t);
                if (! isset($categories[$catKey]) || $summary === '') {
                    $this->problems[] = "{$name}: takeaway for unknown category \"{$catKey}\" or empty -- skipped.";

                    continue;
                }
                $after[$categories[$catKey]->id] = $summary;
            }
            ksort($after);
            if ($after !== $before) {
                $response->takeaways()->delete();
                foreach ($after as $categoryId => $summary) {
                    $response->takeaways()->create(['category_id' => $categoryId, 'summary' => $summary]);
                }
                $changed = true;
            }
        }

        $result['responses'][$response->wasRecentlyCreated ? 'created' : ($changed ? 'updated' : 'unchanged')]++;
    }

    /**
     * A party profile: <party><name>BC NDP</name><full_name/><leader/><leader_note/><founded/><founded_note/>
     * <times_in_government>3</times_in_government><government_note/><principles/><platform><point>…</point></platform>
     * <as_of>2026-10-12</as_of><sources><source><label/><url/></source></sources></party>. Matched by <name>, the
     * name candidates carry in their party field. Needs a name and an as_of date; the rest is optional.
     */
    private function importParty(SimpleXMLElement $node, array &$result): void
    {
        $name = $this->text($node, 'name');
        if ($name === null) {
            $this->problems[] = 'Skipped a party with no <name>.';

            return;
        }
        if (! $this->election->isProvincial()) {
            $this->problems[] = "Party \"{$name}\": party profiles are provincial only; skipped.";

            return;
        }

        $party = Party::where('name', $name)->first() ?? new Party;
        $asOf = $this->date($node, 'as_of', "Party \"{$name}\"");
        if (! $party->exists && $asOf === null) {
            $this->problems[] = "Party \"{$name}\": a new party needs a valid <as_of> date; skipped.";

            return;
        }

        // Only the fields the file carries are updated, so a file of claims or news
        // for a party doesn't blank the profile written by an earlier file.
        $fields = ['name' => $name];
        foreach (['full_name', 'leader', 'leader_note', 'founded', 'founded_note', 'government_note', 'principles'] as $field) {
            if (isset($node->{$field})) {
                $fields[$field] = $this->text($node, $field);
            }
        }
        if (isset($node->times_in_government)) {
            $times = $this->text($node, 'times_in_government');
            if ($times !== null && ! ctype_digit($times)) {
                $this->problems[] = "Party \"{$name}\": <times_in_government> must be a whole number; left out.";
                $times = null;
            }
            $fields['times_in_government'] = $times === null ? null : (int) $times;
        }
        if ($asOf !== null) {
            $fields['as_of'] = $asOf;
        }
        if (isset($node->platform)) {
            $platform = [];
            foreach ($node->platform->point ?? [] as $point) {
                $p = trim((string) $point);
                if ($p !== '') {
                    $platform[] = $p;
                }
            }
            $fields['platform'] = $platform === [] ? null : $platform;
        }
        if (isset($node->sources)) {
            $sources = [];
            foreach ($node->sources->source ?? [] as $src) {
                $url = $this->text($src, 'url');
                if ($url === null || ! $this->isHttpUrl($url)) {
                    $this->problems[] = "Party \"{$name}\": a source with no valid <url>, left out.";

                    continue;
                }
                $sources[] = ['label' => $this->text($src, 'label') ?? $url, 'url' => $url];
            }
            $fields['sources'] = $sources === [] ? null : $sources;
        }

        $party->fill($fields);

        $this->tally($party, $result['parties']);

        $position = 0;
        foreach ($node->claims->claim ?? [] as $claimNode) {
            $this->importPartyClaim($party, $claimNode, $position++, $result);
        }
        foreach ($node->news->article ?? [] as $articleNode) {
            $this->importPartyArticle($party, $articleNode, $result);
        }
        if (isset($node->slate)) {
            $this->importPartySlate($party, $node->slate, $result);
        }
    }

    /**
     * Every candidate the party is running province-wide, from the official list:
     * <slate><source_url/><as_of/><candidate><riding/><name/><incumbent_riding/></candidate>…</slate>
     * (<incumbent_riding> only for a sitting MLA running again: the seat they hold now).
     * The file is the whole list, so a candidate it no longer names is removed.
     */
    private function importPartySlate(Party $party, SimpleXMLElement $node, array &$result): void
    {
        $label = "Party {$party->name}: slate";
        $source = $this->text($node, 'source_url');
        $asOf = $this->date($node, 'as_of', $label);
        if ($source === null || ! $this->isHttpUrl($source) || $asOf === null) {
            $this->problems[] = "{$label}: skipped, it needs an http(s) <source_url> and a valid <as_of>.";

            return;
        }

        $party->fill(['slate_source_url' => $source, 'slate_as_of' => $asOf]);
        if ($party->isDirty()) {
            $party->save();
        }

        $keep = [];
        $position = 0;
        foreach ($node->candidate ?? [] as $c) {
            $riding = $this->text($c, 'riding');
            $name = $this->text($c, 'name');
            if ($riding === null || $name === null) {
                $this->problems[] = "{$label}: skipped a candidate with no <riding> or <name>.";

                continue;
            }
            $model = $party->slate()->where('riding', $riding)->where('name', $name)->first() ?? $party->slate()->make();
            $model->fill(['riding' => $riding, 'name' => $name, 'incumbent_riding' => $this->text($c, 'incumbent_riding'), 'position' => $position++]);
            $this->tally($model, $result['party_slate']);
            $keep[] = $model->id;
        }
        $party->slate()->whereNotIn('id', $keep)->delete();
    }

    /**
     * One published poll: <poll><pollster/><fielded_from/><fielded_to/><published_on/><sample_size/><method/>
     * <results><result><party>BC NDP</party><percent>37</percent></result>…</results><source_url/><source_name/><note/></poll>.
     * Matched on pollster and the last day in the field. Provincial only.
     */
    private function importPoll(SimpleXMLElement $node, array &$result): void
    {
        $pollster = $this->text($node, 'pollster');
        $label = 'Poll '.($pollster ?? '?');
        $fieldedTo = $this->date($node, 'fielded_to', $label);
        $source = $this->text($node, 'source_url');
        if ($pollster === null || $fieldedTo === null || $source === null || ! $this->isHttpUrl($source)) {
            $this->problems[] = "{$label}: skipped, it needs a <pollster>, a valid <fielded_to> and an http(s) <source_url>.";

            return;
        }
        if (! $this->election->isProvincial()) {
            $this->problems[] = "{$label}: polls are provincial only, skipped.";

            return;
        }

        $results = [];
        foreach ($node->results->result ?? [] as $r) {
            $party = $this->text($r, 'party');
            $percent = $this->text($r, 'percent');
            if ($party === null || $percent === null || ! is_numeric($percent) || $percent < 0 || $percent > 100) {
                $this->problems[] = "{$label}: a result with no <party> or a <percent> outside 0-100 was left out.";

                continue;
            }
            $results[$party] = (float) $percent;
        }
        if (count($results) < 2) {
            $this->problems[] = "{$label}: skipped, it needs results for at least two parties.";

            return;
        }

        $model = Poll::where('pollster', $pollster)->whereDate('fielded_to', $fieldedTo)->first() ?? new Poll;
        $model->fill([
            'pollster' => $pollster,
            'fielded_from' => $this->date($node, 'fielded_from', $label),
            'fielded_to' => $fieldedTo,
            'published_on' => $this->date($node, 'published_on', $label),
            'sample_size' => $this->positiveInt($node, 'sample_size'),
            'method' => $this->text($node, 'method'),
            'results' => $results,
            'source_url' => $source,
            'source_name' => $this->text($node, 'source_name'),
            'note' => $this->text($node, 'note'),
        ]);

        $this->tally($model, $result['polls']);
    }

    /**
     * A riding's modelled vote shares: <projection><riding/><rating/><leader/><as_of/><source_url/>
     * <shares><share><party/><percent/><margin/></share>…</shares></projection>. Matched on riding. Provincial only.
     */
    private function importProjection(SimpleXMLElement $node, array &$result): void
    {
        $riding = $this->text($node, 'riding');
        $label = 'Projection '.($riding ?? '?');
        $asOf = $this->date($node, 'as_of', $label);
        $source = $this->text($node, 'source_url');
        if ($riding === null || $asOf === null || $source === null || ! $this->isHttpUrl($source)) {
            $this->problems[] = "{$label}: skipped, it needs a <riding>, a valid <as_of> and an http(s) <source_url>.";

            return;
        }
        if (! $this->election->isProvincial()) {
            $this->problems[] = "{$label}: projections are provincial only, skipped.";

            return;
        }

        $shares = [];
        foreach ($node->shares->share ?? [] as $sh) {
            $party = $this->text($sh, 'party');
            $percent = $this->text($sh, 'percent');
            if ($party === null || $percent === null || ! is_numeric($percent)) {
                continue;
            }
            $margin = $this->text($sh, 'margin');
            $shares[] = ['party' => $party, 'percent' => (float) $percent, 'margin' => is_numeric($margin) ? (float) $margin : null];
        }
        if ($shares === []) {
            $this->problems[] = "{$label}: skipped, it has no usable <share>.";

            return;
        }
        usort($shares, fn ($a, $b) => $b['percent'] <=> $a['percent']);

        $model = RidingProjection::where('riding', $riding)->first() ?? new RidingProjection;
        $model->fill([
            'riding' => $riding,
            'rating' => $this->text($node, 'rating'),
            'leader' => $this->text($node, 'leader') ?? $shares[0]['party'],
            'shares' => $shares,
            'as_of' => $asOf,
            'source_url' => $source,
        ]);

        $this->tally($model, $result['projections']);
    }

    /**
     * One fact-check under a party: <claim><key>ndp-jobs</key><kind>claim</kind><claim>…</claim><source_url/><source_name/>
     * <topic/><rating>mostly_holds_up</rating><summary>what the evidence shows</summary>
     * <evidence><item><kind>votes</kind><text/><label/><url/></item></evidence><checked_on>2026-10-12</checked_on></claim>.
     * Matched by <key> within the party; needs the claim, where the party says it, a rating, a summary and the check date.
     */
    private function importPartyClaim(Party $party, SimpleXMLElement $node, int $position, array &$result): void
    {
        $label = "Party {$party->name}: claim";
        $key = $this->text($node, 'key');
        $claim = $this->text($node, 'claim');
        $sourceUrl = $this->text($node, 'source_url');
        $rating = $this->text($node, 'rating');
        $summary = $this->text($node, 'summary');
        $checkedOn = $this->date($node, 'checked_on', $label);

        if ($key === null || $claim === null || $summary === null || $checkedOn === null) {
            $this->problems[] = "{$label}: skipped a claim with no <key>, <claim>, <summary> or valid <checked_on>.";

            return;
        }
        if (! array_key_exists((string) $rating, PartyClaim::RATINGS)) {
            $this->problems[] = "{$label} \"{$key}\": unknown or missing <rating> \"{$rating}\", skipped.";

            return;
        }
        if ($sourceUrl === null || ! $this->isHttpUrl($sourceUrl)) {
            $this->problems[] = "{$label} \"{$key}\": no valid <source_url> for where the party says it, skipped.";

            return;
        }
        $kind = $this->text($node, 'kind') ?? 'claim';
        if (! array_key_exists($kind, PartyClaim::KINDS)) {
            $this->problems[] = "{$label} \"{$key}\": unknown <kind> \"{$kind}\", treated as a claim.";
            $kind = 'claim';
        }

        $evidence = [];
        foreach ($node->evidence->item ?? [] as $item) {
            $ek = $this->text($item, 'kind');
            $text = $this->text($item, 'text');
            $url = $this->text($item, 'url');
            if (! array_key_exists((string) $ek, PartyClaim::EVIDENCE) || $text === null) {
                $this->problems[] = "{$label} \"{$key}\": an evidence item with no valid <kind> or no <text>, left out.";

                continue;
            }
            if ($url !== null && ! $this->isHttpUrl($url)) {
                $this->problems[] = "{$label} \"{$key}\": an evidence <url> that isn't http(s), left out of that item.";
                $url = null;
            }
            $evidence[] = ['kind' => $ek, 'text' => $text, 'label' => $this->text($item, 'label'), 'url' => $url];
        }

        $model = $party->claims()->where('key', $key)->first() ?? $party->claims()->make();
        $model->fill([
            'key' => $key,
            'position' => $position,
            'kind' => $kind,
            'claim' => $claim,
            'source_url' => $sourceUrl,
            'source_name' => $this->text($node, 'source_name'),
            'topic' => $this->text($node, 'topic'),
            'rating' => $rating,
            'summary' => $summary,
            'evidence' => $evidence === [] ? null : $evidence,
            'checked_on' => $checkedOn,
        ]);

        $this->tally($model, $result['party_claims']);
    }

    /**
     * A story about the party as a whole: <article><title/><url/><outlet/><published_on/><summary/><type>legislative</type></article>.
     * Matched by its address within the party.
     */
    private function importPartyArticle(Party $party, SimpleXMLElement $node, array &$result): void
    {
        $label = "Party {$party->name}: news";
        $title = $this->text($node, 'title');
        $url = $this->text($node, 'url');
        if ($title === null || $url === null || ! $this->isHttpUrl($url)) {
            $this->problems[] = "{$label}: skipped a story with no <title> or valid <url>.";

            return;
        }
        $type = $this->text($node, 'type') ?? 'legislative';
        if (! array_key_exists($type, PartyArticle::TYPES)) {
            $this->problems[] = "{$label} \"".mb_strimwidth($title, 0, 50, '…')."\": unknown <type> \"{$type}\", filed as legislative.";
            $type = 'legislative';
        }

        $hash = PartyArticle::hashFor($url);
        $article = $party->articles()->where('url_hash', $hash)->first() ?? $party->articles()->make();
        $article->fill([
            'title' => $title,
            'url' => $url,
            'url_hash' => $hash,
            'outlet' => $this->text($node, 'outlet'),
            'published_on' => $this->date($node, 'published_on', $label),
            'summary' => $this->text($node, 'summary'),
            'type' => $type,
        ]);

        $this->tally($article, $result['party_news']);
    }

    /**
     * A vocabulary entry: <tag><slug/><topic/><name/><description/></tag>.
     * Matched on slug; supplied fields replace, omitted ones are left alone.
     */
    private function importTag(SimpleXMLElement $node, array &$result): void
    {
        $slug = $this->text($node, 'slug');
        if ($slug === null || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $this->problems[] = 'Skipped a vocabulary tag with no <slug> or one that isn\'t lowercase-kebab-case.';

            return;
        }

        $tag = Tag::firstOrNew(['slug' => $slug]);
        $topic = $this->text($node, 'topic');
        if ($topic !== null && ! array_key_exists($topic, Entry::TOPICS)) {
            $this->problems[] = "Tag {$slug}: unknown topic \"{$topic}\", left as it was.";
            $topic = null;
        }
        $kind = $this->text($node, 'kind');
        if ($kind !== null && ! array_key_exists($kind, Tag::KINDS)) {
            $this->problems[] = "Tag {$slug}: unknown kind \"{$kind}\", left as it was.";
            $kind = null;
        }
        $tag->kind = $kind ?? $tag->kind ?? 'subject';
        $tag->topic = $topic ?? $tag->topic ?? 'other';
        $tag->name = $this->text($node, 'name') ?? $tag->name ?? $slug;
        $tag->description = $this->text($node, 'description') ?? $tag->description;

        $this->tally($tag, $result['tags']);
    }

    /**
     * Replaces a record's tags with its <tags><tag>slug</tag></tags>, when
     * the element is there at all -- an empty <tags/> clears them, a record
     * without one keeps what it has. Unknown slugs are reported, not created.
     */
    private function syncTags(\Illuminate\Database\Eloquent\Model $model, SimpleXMLElement $node, string $label, array &$result): void
    {
        if (! isset($node->tags)) {
            return;
        }

        $slugs = $this->list($node->tags, 'tag');
        $found = $slugs === [] ? collect() : Tag::whereIn('slug', $slugs)->get(['id', 'slug', 'kind'])->keyBy('slug');
        foreach (array_diff($slugs, $found->keys()->all()) as $unknown) {
            $this->problems[] = "{$label}: unknown tag \"{$unknown}\" -- add it to <vocabulary> first.";
        }

        // A story is one type of news, so it sits in one column: of several news-type
        // tags on an article, the first listed is kept and the rest are reported.
        if ($model instanceof Article) {
            $news = array_values(array_filter($slugs, fn ($slug) => ($found[$slug]->kind ?? null) === 'news'));
            if (count($news) > 1) {
                $this->problems[] = "{$label}: more than one news-type tag (".implode(', ', $news).") -- kept {$news[0]}, a story has one type.";
                $drop = array_slice($news, 1);
                $found = $found->reject(fn ($tag) => in_array($tag->slug, $drop, true));
            }
        }
        $ids = $found->pluck('id', 'slug');

        /** @var \Illuminate\Database\Eloquent\Relations\MorphToMany $relation */
        $relation = $model->tags();
        $changes = $relation->sync($ids->values()->all());
        $changed = $changes['attached'] !== [] || $changes['detached'] !== [];
        $result['tagged'][$changed ? 'updated' : 'unchanged']++;
    }

    /**
     * Tags for records already on file, without repeating the records:
     *
     *   <tagging>
     *     <plank candidate="Name" key="plank-key"><tags><tag>slug</tag></tags></plank>
     *     <entry candidate="Name" hash="match_hash"><tags>…</tags></entry>
     *     <vote candidate="Name" hash="match_hash"><tags>…</tags></vote>
     *     <article url="https://…"><tags>…</tags></article>
     *     <issue taken_on="YYYY-MM-DD" key="issue-key"><tags>…</tags></issue>
     *     <item scorecard="scorecard-key" key="statement-key"><tags>…</tags></item>
     *   </tagging>
     *
     * Each record's tags are replaced by the ones listed.
     */
    private function importTagging(SimpleXMLElement $node, array &$result): void
    {
        foreach ($node->children() as $ref) {
            $a = fn (string $name) => trim((string) $ref[$name]);
            $model = match ($ref->getName()) {
                'plank' => Plank::whereHas('candidate', fn ($q) => $q->forElection($this->election)->where('name', $a('candidate')))->where('key', $a('key'))->first(),
                'vote' => Vote::whereHas('candidate', fn ($q) => $q->forElection($this->election)->where('name', $a('candidate')))->where('match_hash', $a('hash'))->first(),
                'entry' => Entry::whereHas('candidate', fn ($q) => $q->forElection($this->election)->where('name', $a('candidate')))->where('match_hash', $a('hash'))->first(),
                'article' => Article::forElection($this->election)->where('url_hash', Article::hashFor($a('url')))->first(),
                'issue' => PulseIssue::whereHas('snapshot', fn ($q) => $q->whereDate('taken_on', $a('taken_on')))->where('key', $a('key'))->first(),
                'item' => ScorecardItem::whereHas('category.scorecard', fn ($q) => $q->where('key', $a('scorecard')))->where('key', $a('key'))->first(),
                default => false,
            };
            $attributes = [];
            foreach ($ref->attributes() as $name => $value) {
                $attributes[] = "{$name}=\"{$value}\"";
            }
            $label = '<tagging> '.$ref->getName().' '.implode(' ', $attributes);
            if ($model === false) {
                $this->problems[] = "Unknown element in <tagging>: <{$ref->getName()}>.";

                continue;
            }
            if ($model === null) {
                $this->problems[] = "{$label}: no such record on file -- skipped.";

                continue;
            }
            $this->syncTags($model, $ref, $label, $result);
        }
    }

    /**
     * <plan status="specific|partial|none"><summary/><detail aspect="how"
     * source_url="https://…">text</detail>…</plan> -- replaces the plank's
     * plan as a whole. A detail without an http(s) source is dropped: every
     * plan detail has to point at where it was said.
     */
    private function applyPlan(Plank $plank, SimpleXMLElement $node, string $label): void
    {
        $status = trim((string) $node['status']);
        if (! array_key_exists($status, Plank::PLAN_STATUSES)) {
            $this->problems[] = "{$label}: plan has unknown status \"{$status}\" -- plan left as it was.";

            return;
        }

        $details = [];
        foreach ($node->detail ?? [] as $d) {
            $text = trim((string) $d);
            $aspect = trim((string) $d['aspect']) ?: 'other';
            $source = trim((string) $d['source_url']);
            if ($text === '') {
                continue;
            }
            if (! $this->isHttpUrl($source)) {
                $this->problems[] = "{$label}: plan detail \"".mb_strimwidth($text, 0, 50, '…').'" has no http(s) source_url -- dropped.';

                continue;
            }
            if (! array_key_exists($aspect, Plank::PLAN_ASPECTS)) {
                $this->problems[] = "{$label}: plan detail aspect \"{$aspect}\" unknown, filed under \"other\".";
                $aspect = 'other';
            }
            $details[] = ['aspect' => $aspect, 'text' => $text, 'source_url' => $source];
        }

        if ($status !== 'none' && $details === []) {
            $this->problems[] = "{$label}: plan marked \"{$status}\" but has no sourced details.";
        }

        $plank->plan_status = $status;
        $plank->plan_summary = $this->text($node, 'summary');
        $plank->plan_details = $details === [] ? null : $details;
    }

    /**
     * <analysis on="YYYY-MM-DD"><meaning><point source_url="…">text</point>…
     * </meaning><works>…</works><fails>…</fails><details>…</details></analysis> --
     * replaces the plank's analysis. A point may carry several
     * source_url-N attributes; points without any source are kept (they
     * reason from the sourced ones) but the analysis must cite at least
     * one source overall.
     */
    private function applyAnalysis(Plank $plank, SimpleXMLElement $node, string $label): void
    {
        // <analysis remove="true"/> takes an analysis off a plank.
        if (filter_var((string) $node['remove'], FILTER_VALIDATE_BOOLEAN)) {
            $plank->analysis = null;
            $plank->analysis_on = null;

            return;
        }

        $analysis = [];
        $cited = 0;
        foreach (array_keys(Plank::ANALYSIS_PARTS) as $part) {
            $points = [];
            foreach ($node->{$part}->point ?? [] as $p) {
                $text = trim((string) $p);
                if ($text === '') {
                    continue;
                }
                $sources = [];
                foreach ($p->attributes() as $name => $value) {
                    $value = trim((string) $value);
                    if (str_starts_with((string) $name, 'source_url') && $value !== '') {
                        if ($this->isHttpUrl($value)) {
                            $sources[] = $value;
                        } else {
                            $this->problems[] = "{$label}: analysis source \"{$value}\" isn't an http(s) URL -- dropped.";
                        }
                    }
                }
                $cited += count($sources);
                $points[] = ['text' => $text, 'sources' => $sources];
            }
            $analysis[$part] = $points;
        }

        if (array_sum(array_map('count', $analysis)) === 0) {
            $this->problems[] = "{$label}: <analysis> has no points -- left as it was.";

            return;
        }
        if ($cited === 0) {
            $this->problems[] = "{$label}: <analysis> cites no sources -- left as it was.";

            return;
        }

        $on = trim((string) $node['on']);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $on);
        if ($on !== '' && ($date === false || $date->format('Y-m-d') !== $on)) {
            $this->problems[] = "{$label}: analysis date \"{$on}\" isn't YYYY-MM-DD -- using today.";
            $on = '';
        }

        $plank->analysis = $analysis;
        $plank->analysis_on = $on !== '' ? $on : now()->toDateString();
    }

    /**
     * Analyses for planks already on file: <analyses><analysis
     * candidate="Name" key="plank-key" on="YYYY-MM-DD">…</analysis></analyses>.
     */
    private function importAnalysis(SimpleXMLElement $node, array &$result): void
    {
        $name = trim((string) $node['candidate']);
        $key = trim((string) $node['key']);
        $plank = Plank::whereHas('candidate', fn ($q) => $q->forElection($this->election)->where('name', $name))->where('key', $key)->first();
        if ($plank === null) {
            $this->problems[] = "<analyses>: no plank \"{$key}\" for \"{$name}\" on file -- skipped.";

            return;
        }

        $this->applyAnalysis($plank, $node, "{$name}: plank \"{$plank->title}\"");
        $this->tally($plank, $result['analyses']);
    }

    /**
     * Plans for planks already on file: <plans><plan candidate="Name"
     * key="plank-key" status="…">…</plan></plans>.
     */
    private function importPlan(SimpleXMLElement $node, array &$result): void
    {
        $name = trim((string) $node['candidate']);
        $key = trim((string) $node['key']);
        $plank = Plank::whereHas('candidate', fn ($q) => $q->forElection($this->election)->where('name', $name))->where('key', $key)->first();
        if ($plank === null) {
            $this->problems[] = "<plans>: no plank \"{$key}\" for \"{$name}\" on file -- skipped.";

            return;
        }

        $this->applyPlan($plank, $node, "{$name}: plank \"{$plank->title}\"");
        $this->tally($plank, $result['plans']);
    }

    /**
     * Corrections the rest of the format can't express, applied after
     * everything else in the file:
     *
     *   <changes>
     *     <detach_entries candidate="Name" plank="key|*" before="YYYY-MM-DD" kind="prior_record"/>
     *     <remove_plank candidate="Name" key="plank-key"/>
     *     <remove_entry candidate="Name" hash="match_hash"/>
     *   </changes>
     *
     * detach_entries takes a plank's statements off it (all planks with "*",
     * optionally only those published before a date) and refiles them as
     * `kind` (default statement). remove_plank deletes a plank, refiling any
     * statements still on it as statements, and closes the rank gap.
     * remove_entry deletes one entry. Unknown targets are reported, never
     * guessed at.
     */
    private function applyChanges(SimpleXMLElement $changes, array &$result): void
    {
        foreach ($changes->children() as $node) {
            $name = trim((string) $node['candidate']);
            $candidate = Candidate::forElection($this->election)->where('name', $name)->first();
            if ($candidate === null) {
                $this->problems[] = "<changes>: no candidate \"{$name}\" on file -- skipped <{$node->getName()}>.";

                continue;
            }

            match ($node->getName()) {
                'detach_entries' => $this->detachEntries($candidate, $node, $result),
                'remove_plank' => $this->removePlank($candidate, $node, $result),
                'remove_entry' => $this->removeEntry($candidate, $node, $result),
                default => $this->problems[] = "<changes>: unknown change <{$node->getName()}> -- skipped.",
            };
        }
    }

    private function detachEntries(Candidate $candidate, SimpleXMLElement $node, array &$result): void
    {
        $key = trim((string) $node['plank']);
        $kind = trim((string) $node['kind']) ?: 'statement';
        if (! array_key_exists($kind, Entry::KINDS) || in_array($kind, ['plank', 'background'], true)) {
            $this->problems[] = "<changes>: {$candidate->name}: can't refile statements as \"{$kind}\" -- skipped.";

            return;
        }

        $planks = $candidate->planks()->when($key !== '*', fn ($q) => $q->where('key', $key))->pluck('id');
        if ($planks->isEmpty()) {
            $this->problems[] = "<changes>: {$candidate->name}: no plank \"{$key}\" on file -- skipped detach_entries.";

            return;
        }

        $before = trim((string) $node['before']);
        if ($before !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $before)) {
            $this->problems[] = "<changes>: {$candidate->name}: before=\"{$before}\" isn't YYYY-MM-DD -- skipped detach_entries.";

            return;
        }

        $entries = $candidate->entries()->whereIn('plank_id', $planks)
            ->when($before !== '', fn ($q) => $q->whereNotNull('published_on')->where('published_on', '<', $before))
            ->get();
        foreach ($entries as $entry) {
            $entry->forceFill(['plank_id' => null, 'kind' => $kind])->save();
        }
        $result['changes']['updated'] += $entries->count();
    }

    private function removePlank(Candidate $candidate, SimpleXMLElement $node, array &$result): void
    {
        $key = trim((string) $node['key']);
        $plank = $candidate->planks()->where('key', $key)->first();
        if ($plank === null) {
            $this->problems[] = "<changes>: {$candidate->name}: no plank \"{$key}\" on file -- skipped remove_plank.";

            return;
        }

        $candidate->entries()->where('plank_id', $plank->id)->update(['plank_id' => null, 'kind' => 'statement']);
        $plank->tags()->detach();
        $plank->delete();

        foreach ($candidate->planks()->orderBy('rank')->get()->values() as $i => $remaining) {
            if ($remaining->rank !== $i + 1) {
                $remaining->forceFill(['rank' => $i + 1])->save();
            }
        }
        $result['changes']['updated']++;
    }

    private function removeEntry(Candidate $candidate, SimpleXMLElement $node, array &$result): void
    {
        $hash = trim((string) $node['hash']);
        $entry = $candidate->entries()->where('match_hash', $hash)->first();
        if ($entry === null) {
            $this->problems[] = "<changes>: {$candidate->name}: no entry with hash \"{$hash}\" on file -- skipped remove_entry.";

            return;
        }

        $entry->tags()->detach();
        $entry->delete();
        $result['changes']['updated']++;
    }

    private function list(?SimpleXMLElement $parent, string $child): array
    {
        $items = [];
        foreach ($parent?->{$child} ?? [] as $item) {
            $text = trim((string) $item);
            if ($text !== '') {
                $items[] = $text;
            }
        }

        return $items;
    }

    /**
     * Saves only when something actually changed, and counts which it was.
     *
     * @param  array{created: int, updated: int, unchanged: int}  $counts
     */
    private function tally(\Illuminate\Database\Eloquent\Model $model, array &$counts): void
    {
        if (! $model->exists) {
            $model->save();
            $counts['created']++;
        } elseif ($model->isDirty()) {
            $model->save();
            $counts['updated']++;
        } else {
            $counts['unchanged']++;
        }
    }

    private function text(SimpleXMLElement $node, string $child): ?string
    {
        if (! isset($node->{$child})) {
            return null;
        }

        $value = trim((string) $node->{$child});

        return $value === '' ? null : $value;
    }

    private function positiveInt(SimpleXMLElement $node, string $child): ?int
    {
        $value = filter_var($this->text($node, $child), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 255]]);

        return $value === false ? null : $value;
    }

    private function bool(SimpleXMLElement $node, string $child): ?bool
    {
        $value = $this->text($node, $child);

        return $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function date(SimpleXMLElement $node, string $child, string $label): ?string
    {
        $value = $this->text($node, $child);
        if ($value === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            $this->problems[] = "{$label}: <{$child}> \"{$value}\" isn't YYYY-MM-DD, left out.";

            return null;
        }

        return $value;
    }

    private function dateTime(SimpleXMLElement $node, string $child, string $label): ?Carbon
    {
        $value = $this->text($node, $child);
        if ($value === null) {
            return null;
        }

        // Plain DateTime parsing (Carbon's createFromFormat throws rather
        // than returning false), round-tripped so "2026-02-30" is refused
        // instead of silently rolling over into March.
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat("!{$format}", $value);
            if ($parsed !== false && $parsed->format($format) === $value) {
                return Carbon::instance($parsed);
            }
        }

        $this->problems[] = "{$label}: <{$child}> \"{$value}\" isn't YYYY-MM-DDTHH:MM.";

        return null;
    }

    private function isHttpUrl(string $value): bool
    {
        return (bool) preg_match('#^https?://\S+$#i', $value);
    }
}
