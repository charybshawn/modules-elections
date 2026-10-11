<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Election;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Tag;

/**
 * Every tag in the vocabulary, filed under its heading (Entry::TOPICS order),
 * with how many candidates have a plank on it and how many records carry it.
 */
class BuildTagDirectory
{
    public function handle(): array
    {
        // Municipal records only; provincial ones don't count toward these subjects yet.
        $municipal = Election::municipal();
        $candidateIds = Candidate::forElection($municipal)->pluck('id');
        $tags = Tag::withCount([
            'planks' => fn ($q) => $q->forElection($municipal),
            'entries' => fn ($q) => $q->forElection($municipal),
            'articles' => fn ($q) => $q->forElection($municipal),
            'pulseIssues',
            'scorecardItems',
        ])
            ->with('planks:id,candidate_id')
            ->orderBy('name')
            ->get();

        $row = fn (Tag $tag) => [
            'slug' => $tag->slug,
            'name' => $tag->name,
            'description' => $tag->description,
            'candidates' => $tag->planks->pluck('candidate_id')->unique()->intersect($candidateIds)->count(),
            'records' => $tag->planks_count + $tag->entries_count + $tag->articles_count + $tag->pulse_issues_count + $tag->scorecard_items_count,
        ];

        $headings = collect(Entry::TOPICS)
            ->map(fn (string $title, string $topic) => [
                'topic' => $topic,
                'title' => $title,
                'tags' => $tags->where('topic', $topic)->map($row)->values()->all(),
            ])
            ->filter(fn (array $heading) => $heading['tags'] !== [])
            ->values()
            ->all();

        return [
            'headings' => $headings,
            // What the most candidates are campaigning on.
            'mostCovered' => $tags->map($row)->sortByDesc('candidates')->take(8)->filter(fn ($t) => $t['candidates'] > 0)->values()->all(),
        ];
    }
}
