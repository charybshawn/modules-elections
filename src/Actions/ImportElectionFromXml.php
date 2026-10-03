<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Plank;
use Cultpantry\Elections\Models\PulseIssue;
use Cultpantry\Elections\Models\PulseSnapshot;
use Cultpantry\Elections\Models\Scorecard;
use Cultpantry\Elections\Models\ScorecardAnswer;
use Cultpantry\Elections\Models\ScorecardItem;
use Cultpantry\Elections\Models\ScorecardResponse;
use Cultpantry\Elections\Models\Tag;
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
        'occupation', 'bio', 'bio_source_url', 'website', 'facebook_url',
        'instagram_url', 'email', 'phone', 'photo_url', 'notes',
    ];

    private const URL_FIELDS = ['bio_source_url', 'website', 'facebook_url', 'instagram_url', 'photo_url'];

    /** @var array<int, string> */
    private array $problems = [];

    /**
     * @return array{candidates: array{created: int, updated: int, unchanged: int}, entries: array{created: int, updated: int, unchanged: int}, planks: array{created: int, updated: int, unchanged: int}, articles: array{created: int, updated: int, unchanged: int}, events: array{created: int, updated: int, unchanged: int}, problems: array<int, string>}
     */
    public function handle(UploadedFile $file): array
    {
        return $this->handleString((string) file_get_contents($file->getRealPath()));
    }

    /**
     * @return array{candidates: array{created: int, updated: int, unchanged: int}, entries: array{created: int, updated: int, unchanged: int}, planks: array{created: int, updated: int, unchanged: int}, articles: array{created: int, updated: int, unchanged: int}, events: array{created: int, updated: int, unchanged: int}, problems: array<int, string>}
     */
    public function handleString(string $contents): array
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

        $this->problems = [];
        $counts = fn () => ['created' => 0, 'updated' => 0, 'unchanged' => 0];
        $result = [
            'candidates' => $counts(),
            'entries' => $counts(),
            'planks' => $counts(),
            'articles' => $counts(),
            'events' => $counts(),
            'pulse' => $counts(),
            'scorecards' => $counts(),
            'responses' => $counts(),
            'tags' => $counts(),
            'tagged' => $counts(),
        ];

        DB::transaction(function () use ($xml, &$result) {
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
            foreach ($xml->pulse ?? [] as $node) {
                $this->importPulse($node, $result);
            }
            foreach ($xml->scorecards->scorecard ?? [] as $node) {
                $this->importScorecard($node, $result);
            }
            foreach ($xml->tagging ?? [] as $node) {
                $this->importTagging($node, $result);
            }
        });

        Tag::pruneOrphans();

        $result['problems'] = $this->problems;

        return $result;
    }

    /**
     * One flash-sized sentence per kind of record, then the problems.
     */
    public function summarize(array $result): string
    {
        $parts = [];
        foreach (['tags', 'candidates', 'entries', 'planks', 'articles', 'events', 'pulse', 'scorecards', 'responses', 'tagged'] as $type) {
            ['created' => $created, 'updated' => $updated, 'unchanged' => $unchanged] = $result[$type];
            if ($created + $updated + $unchanged === 0) {
                continue;
            }
            $parts[] = "{$type}: {$created} new, {$updated} updated, {$unchanged} unchanged";
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

        $candidate = Candidate::where('name', $name)->first() ?? new Candidate(['name' => $name]);

        $office = $this->text($node, 'office');
        if ($office !== null && ! array_key_exists($office, Candidate::OFFICES)) {
            $this->problems[] = "{$name}: unknown office \"{$office}\".";
            $office = null;
        }
        if ($office !== null) {
            $candidate->office = $office;
        }
        if (! $candidate->exists && $candidate->office === null) {
            $this->problems[] = "Skipped new candidate {$name}: no valid <office> (mayor or councillor).";

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
            if (in_array($field, self::URL_FIELDS, true) && ! $this->isHttpUrl($value)) {
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

        $article = Article::where('url_hash', Article::hashFor($url))->first() ?? new Article(['url' => $url]);
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
            $id = Candidate::where('name', $name)->value('id');
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

        $event = ElectionEvent::where('title', $title)->where('starts_at', $startsAt)->first()
            ?? new ElectionEvent(['title' => $title, 'starts_at' => $startsAt]);

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
            $candidateId = $name === null ? null : Candidate::where('name', $name)->value('id');
            if ($candidateId === null) {
                $this->problems[] = "Pulse {$takenOn}: mention of \"{$name}\", who isn't on file -- skipped.";

                continue;
            }
            $snapshot->mentions()->updateOrCreate(['candidate_id' => $candidateId], [
                'mentions' => (int) $this->text($m, 'mentions'),
                'commenters' => (int) $this->text($m, 'commenters'),
            ]);
        }

        $result['pulse'][$isNew ? 'created' : 'updated']++;
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
        $candidateId = $name === null ? null : Candidate::where('name', $name)->value('id');
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
        $ids = $slugs === [] ? collect() : Tag::whereIn('slug', $slugs)->pluck('id', 'slug');
        foreach (array_diff($slugs, $ids->keys()->all()) as $unknown) {
            $this->problems[] = "{$label}: unknown tag \"{$unknown}\" -- add it to <vocabulary> first.";
        }

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
                'plank' => Plank::whereHas('candidate', fn ($q) => $q->where('name', $a('candidate')))->where('key', $a('key'))->first(),
                'entry' => Entry::whereHas('candidate', fn ($q) => $q->where('name', $a('candidate')))->where('match_hash', $a('hash'))->first(),
                'article' => Article::where('url_hash', Article::hashFor($a('url')))->first(),
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
