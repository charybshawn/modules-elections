<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Election;
use Cultpantry\Elections\Models\Party;
use Cultpantry\Elections\Models\PartyArticle;
use Cultpantry\Elections\Models\PartyClaim;

/**
 * A party's own page, apart from any candidate: who it is and who leads it,
 * the riding's candidates for it, news about the party as a whole, and the
 * fact-check record that sets what it claims against what its record and
 * votes show.
 */
class BuildPartyPage
{
    public function handle(Party $party): array
    {
        $election = Election::provincial();

        $candidates = Candidate::forElection($election)
            ->where('party', $party->name)
            ->where('status', '!=', 'withdrawn')
            ->orderBy('name')
            ->get()
            ->map(fn (Candidate $c) => [
                'name' => $c->name,
                'slug' => $c->slug,
                'photo_url' => $c->photo_url,
                'party' => $c->party,
                'is_incumbent' => (bool) $c->is_incumbent,
                'occupation' => $c->occupation,
            ])->all();

        $claims = $party->claims->map(fn (PartyClaim $c) => [
            'id' => $c->id,
            'key' => $c->key,
            'kind' => $c->kind,
            'claim' => $c->claim,
            'source_url' => $c->source_url,
            'source_name' => $c->source_name,
            'topic' => $c->topic,
            'rating' => $c->rating,
            'summary' => $c->summary,
            'evidence' => $c->evidence ?? [],
            'checked_on' => $c->checked_on?->toDateString(),
        ])->all();

        return [
            'election' => $election->toPage(),
            'party' => $party->profile(),
            'candidates' => $candidates,
            'claims' => $claims,
            // How the ratings split, for the summary strip at the top of the fact-check.
            // Every candidate the party is running across the province; the page scrolls through them.
            'slate' => [
                'candidates' => $party->slate->map(fn ($c) => ['id' => $c->id, 'riding' => $c->riding, 'name' => $c->name, 'incumbent_riding' => $c->incumbent_riding])->all(),
                'source_url' => $party->slate_source_url,
                'as_of' => $party->slate_as_of?->toDateString(),
                'local' => collect($candidates)->pluck('slug', 'name')->all(),
                'local_riding' => $election->riding,
            ],
            'ratingCounts' => $party->claims->countBy('rating')->all(),
            'articles' => $party->articles->map(fn (PartyArticle $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'url' => $a->url,
                'outlet' => $a->outlet,
                'published_on' => $a->published_on?->toDateString(),
                'summary' => $a->summary,
                'type' => $a->type,
            ])->all(),
            'options' => [
                'ratings' => PartyClaim::RATINGS,
                'claimKinds' => PartyClaim::KINDS,
                'evidenceKinds' => PartyClaim::EVIDENCE,
                'newsTypes' => PartyArticle::TYPES,
            ],
        ];
    }
}
