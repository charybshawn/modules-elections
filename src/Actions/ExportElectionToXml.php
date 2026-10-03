<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Models\Entry;
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
        foreach (Candidate::with('entries')->orderBy('name')->get() as $candidate) {
            $node = $candidatesNode->addChild('candidate');
            foreach (self::CANDIDATE_FIELDS as $field) {
                $this->addChild($node, $field, $candidate->{$field});
            }
            $this->addChild($node, 'is_incumbent', $candidate->is_incumbent ? 'true' : 'false');

            if ($candidate->entries->isNotEmpty()) {
                $entriesNode = $node->addChild('entries');
                foreach ($candidate->entries as $entry) {
                    $this->appendEntry($entriesNode, $entry);
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

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
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
