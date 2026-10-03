<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Plank;
use Cultpantry\Elections\Models\Tag;

/**
 * Who campaigns on what: for every candidate and subject tag, the
 * strongest emphasis they give it (their best plank tier on that tag) and
 * the planks behind it. Drives the subject matrix and the dashboard's
 * "who campaigns on this" highlighting.
 */
class BuildSubjectCoverage
{
    public function handle(): array
    {
        $tierOrder = array_flip(array_keys(Plank::TIERS));
        $planks = Plank::with(['tags:id,slug', 'candidate:id,status'])
            ->get()
            ->filter(fn (Plank $p) => $p->candidate?->status !== 'withdrawn')
            ->sortBy(fn (Plank $p) => [$tierOrder[$p->tier] ?? 9, $p->rank]);

        $cells = [];
        $count = [];
        foreach ($planks as $plank) {
            foreach ($plank->tags as $tag) {
                $cell = &$cells[$plank->candidate_id][$tag->slug];
                if ($cell === null) {
                    // Planks are sorted strongest first, so the first sets the tier.
                    $cell = ['tier' => $plank->tier, 'planks' => []];
                    $count[$tag->slug] = ($count[$tag->slug] ?? 0) + 1;
                }
                $cell['planks'][] = $plank->title;
                unset($cell);
            }
        }

        $tags = Tag::orderBy('name')->get(['slug', 'name', 'topic'])
            ->map(fn (Tag $t) => [
                'slug' => $t->slug,
                'name' => $t->name,
                'topic' => $t->topic,
                'candidates' => $count[$t->slug] ?? 0,
            ])
            ->sortBy([['candidates', 'desc'], ['name', 'asc']])
            ->values()
            ->all();

        return [
            'tags' => $tags,
            // candidate id => tag slug => {tier, planks}; JSON object even when empty.
            'cells' => (object) $cells,
        ];
    }
}
