<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Plank;
use Cultpantry\Elections\Models\PulseSnapshot;
use Cultpantry\Elections\Models\Scorecard;
use SimpleXMLElement;

/**
 * The whole dataset as the <election> XML ImportElectionFromXml reads, so a
 * file exported here imports cleanly anywhere (another server, or back into
 * this one as a no-op). Null fields are omitted, matching the hand-written
 * research files.
 */
class ExportElectionToXml
{
    private const CANDIDATE_FIELDS = [
        'name', 'office', 'status', 'occupation', 'bio', 'bio_source_url', 'website',
        'facebook_url', 'instagram_url', 'email', 'phone', 'photo_url', 'notes',
    ];

    public function handle(): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><election></election>');

        $candidatesNode = $xml->addChild('candidates');
        foreach (Candidate::with(['entries', 'planks.entries'])->orderBy('name')->get() as $candidate) {
            $node = $candidatesNode->addChild('candidate');
            foreach (self::CANDIDATE_FIELDS as $field) {
                $this->addChild($node, $field, $candidate->{$field});
            }
            $this->addChild($node, 'is_incumbent', $candidate->is_incumbent ? 'true' : 'false');

            // A plank's statements are written inside it, not here.
            $loose = $candidate->entries->whereNull('plank_id');
            if ($loose->isNotEmpty()) {
                $entriesNode = $node->addChild('entries');
                foreach ($loose as $entry) {
                    $this->appendEntry($entriesNode, $entry);
                }
            }

            if ($candidate->planks->isNotEmpty()) {
                $planksNode = $node->addChild('planks');
                foreach ($candidate->planks as $plank) {
                    $this->appendPlank($planksNode, $plank);
                }
            }
        }

        $articlesNode = $xml->addChild('articles');
        foreach (Article::with('candidates')->orderBy('published_on')->orderBy('id')->get() as $article) {
            $node = $articlesNode->addChild('article');
            $this->addChild($node, 'title', $article->title);
            $this->addChild($node, 'url', $article->url);
            $this->addChild($node, 'outlet', $article->outlet);
            $this->addChild($node, 'published_on', $article->published_on?->toDateString());
            $this->addChild($node, 'summary', $article->summary);
            if ($article->candidates->isNotEmpty()) {
                $names = $node->addChild('candidates');
                foreach ($article->candidates as $candidate) {
                    $this->addChild($names, 'candidate', $candidate->name);
                }
            }
        }

        $eventsNode = $xml->addChild('events');
        foreach (ElectionEvent::orderBy('starts_at')->get() as $event) {
            $node = $eventsNode->addChild('event');
            $this->addChild($node, 'title', $event->title);
            $this->addChild($node, 'kind', $event->kind);
            $this->addChild($node, 'starts_at', $event->starts_at->format('Y-m-d\TH:i'));
            $this->addChild($node, 'ends_at', $event->ends_at?->format('Y-m-d\TH:i'));
            $this->addChild($node, 'location', $event->location);
            $this->addChild($node, 'url', $event->url);
            $this->addChild($node, 'description', $event->description);
        }

        foreach (PulseSnapshot::with(['issues', 'mentions.candidate'])->orderBy('taken_on')->get() as $snapshot) {
            $node = $xml->addChild('pulse');
            $this->addChild($node, 'taken_on', $snapshot->taken_on->toDateString());
            $this->addChild($node, 'period_from', $snapshot->period_from?->toDateString());
            $this->addChild($node, 'period_to', $snapshot->period_to?->toDateString());
            $this->addChild($node, 'threads_read', (string) $snapshot->threads_read);
            $this->addChild($node, 'commenters', (string) $snapshot->commenters);
            $this->addChild($node, 'sources', $snapshot->sources);
            $this->addChild($node, 'method_note', $snapshot->method_note);
            if ($snapshot->conclusions) {
                $list = $node->addChild('conclusions');
                foreach ($snapshot->conclusions as $conclusion) {
                    $this->addChild($list, 'conclusion', $conclusion['text']);
                    if ($conclusion['issue'] ?? null) {
                        $list->conclusion[count($list->conclusion) - 1]['issue'] = $conclusion['issue'];
                    }
                }
            }
            $issues = $node->addChild('issues');
            foreach ($snapshot->issues as $issue) {
                $i = $issues->addChild('issue');
                foreach (['key', 'title', 'topic', 'heat', 'summary'] as $field) {
                    $this->addChild($i, $field, $issue->{$field});
                }
                foreach (['voices', 'support_pct', 'oppose_pct', 'mixed_pct'] as $field) {
                    $this->addChild($i, $field, $issue->{$field} === null ? null : (string) $issue->{$field});
                }
                foreach (['wants' => 'want', 'questions' => 'question'] as $field => $child) {
                    if ($issue->{$field}) {
                        $list = $i->addChild($field);
                        foreach ($issue->{$field} as $item) {
                            $this->addChild($list, $child, $item);
                        }
                    }
                }
            }
            $mentions = $node->addChild('mentions');
            foreach ($snapshot->mentions as $mention) {
                $m = $mentions->addChild('mention');
                $this->addChild($m, 'candidate', $mention->candidate->name);
                $this->addChild($m, 'mentions', (string) $mention->mentions);
                $this->addChild($m, 'commenters', (string) $mention->commenters);
            }
        }

        $scorecards = Scorecard::with(['categories.items', 'responses.candidate', 'responses.answers', 'responses.takeaways'])->orderBy('key')->get();
        if ($scorecards->isNotEmpty()) {
            $scorecardsNode = $xml->addChild('scorecards');
            foreach ($scorecards as $scorecard) {
                $this->appendScorecard($scorecardsNode, $scorecard);
            }
        }

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
    }

    private function appendScorecard(SimpleXMLElement $parent, Scorecard $scorecard): void
    {
        $node = $parent->addChild('scorecard');
        foreach (['key', 'title', 'publisher', 'url', 'about'] as $field) {
            $this->addChild($node, $field, $scorecard->{$field});
        }
        $this->addChild($node, 'retrieved_on', $scorecard->retrieved_on?->toDateString());

        $itemKeys = [];
        $categoryKeys = [];
        $categoriesNode = $node->addChild('categories');
        foreach ($scorecard->categories as $category) {
            $categoryKeys[$category->id] = $category->key;
            $c = $categoriesNode->addChild('category');
            foreach (['key', 'name', 'intro', 'local_context'] as $field) {
                $this->addChild($c, $field, $category->{$field});
            }
            if ($category->sources) {
                $sources = $c->addChild('sources');
                foreach ($category->sources as $url) {
                    $this->addChild($sources, 'source', $url);
                }
            }
            $items = $c->addChild('items');
            foreach ($category->items as $item) {
                $itemKeys[$item->id] = $item->key;
                $i = $items->addChild('item');
                $this->addChild($i, 'key', $item->key);
                $this->addChild($i, 'statement', $item->statement);
            }
        }

        $responsesNode = $node->addChild('responses');
        foreach ($scorecard->responses->sortBy(fn ($r) => $r->candidate->name) as $response) {
            $r = $responsesNode->addChild('response');
            $this->addChild($r, 'candidate', $response->candidate->name);
            $this->addChild($r, 'source_url', $response->source_url);
            $this->addChild($r, 'responded', $response->responded ? 'true' : 'false');
            if ($response->answers->isNotEmpty()) {
                $answers = $r->addChild('answers');
                foreach ($response->answers->sortBy('item_id') as $answer) {
                    $this->addChild($answers, 'answer', $answer->stance);
                    $answers->answer[count($answers->answer) - 1]['item'] = $itemKeys[$answer->item_id] ?? '';
                }
            }
            if ($response->takeaways->isNotEmpty()) {
                $takeaways = $r->addChild('takeaways');
                foreach ($response->takeaways->sortBy('category_id') as $takeaway) {
                    $this->addChild($takeaways, 'takeaway', $takeaway->summary);
                    $takeaways->takeaway[count($takeaways->takeaway) - 1]['category'] = $categoryKeys[$takeaway->category_id] ?? '';
                }
            }
        }
    }

    private function appendPlank(SimpleXMLElement $planksNode, Plank $plank): void
    {
        $node = $planksNode->addChild('plank');
        $this->addChild($node, 'key', $plank->key);
        $this->addChild($node, 'title', $plank->title);
        $this->addChild($node, 'topic', $plank->topic);
        $this->addChild($node, 'summary', $plank->summary);
        $this->addChild($node, 'tier', $plank->tier);
        $this->addChild($node, 'rank', (string) $plank->rank);
        $this->addChild($node, 'rationale', $plank->rationale);
        $this->addChild($node, 'priority_position', $plank->priority_position === null ? null : (string) $plank->priority_position);
        $this->addChild($node, 'has_commitment', $plank->has_commitment ? 'true' : 'false');

        if ($plank->entries->isNotEmpty()) {
            $entriesNode = $node->addChild('entries');
            foreach ($plank->entries as $entry) {
                $this->appendEntry($entriesNode, $entry);
            }
        }
    }

    private function appendEntry(SimpleXMLElement $entriesNode, Entry $entry): void
    {
        $node = $entriesNode->addChild('entry');
        $this->addChild($node, 'kind', $entry->kind);
        $this->addChild($node, 'topic', $entry->topic);
        $this->addChild($node, 'summary', $entry->summary);
        $this->addChild($node, 'quote', $entry->quote);
        $this->addChild($node, 'question', $entry->question);
        $this->addChild($node, 'source_url', $entry->source_url);
        $this->addChild($node, 'source_type', $entry->source_type);
        $this->addChild($node, 'source_name', $entry->source_name);
        $this->addChild($node, 'published_on', $entry->published_on?->toDateString());
    }

    /**
     * Assigning through the DOM text node rather than addChild()'s value
     * argument, which doesn't escape "&" -- a URL with a query string would
     * otherwise produce broken XML.
     */
    private function addChild(SimpleXMLElement $node, string $name, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $child = $node->addChild($name);
        $child[0] = $value;
    }
}
