<?php

namespace Cultpantry\Elections\Actions;

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
        $tags = Tag::withCount(['planks', 'entries', 'articles', 'pulseIssues', 'scorecardItems'])
            ->with('planks:id,candidate_id')
            ->orderBy('name')
            ->get();

        $row = fn (Tag $tag) => [
            'slug' => $tag->slug,
            'name' => $tag->name,
            'description' => $tag->description,
            'candidates' => $tag->planks->pluck('candidate_id')->unique()->count(),
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
