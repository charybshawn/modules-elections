<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Http\Resources\CandidateResource;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Plank;
use Cultpantry\Elections\Models\Scorecard;
use Cultpantry\Elections\Models\ScorecardAnswer;
use Illuminate\Support\Collection;

/**
 * Two or three candidates side by side: who they are, their own top
 * priorities, the subjects they share (and what only one of them raises),
 * and their scorecard answers statement by statement.
 */
class BuildCandidateComparison
{
    public const MAX = 3;

    /**
     * @param  Collection<int, Candidate>  $candidates  in the order picked
     */
    public function handle(Collection $candidates): array
    {
        $candidates->load(['planks.tags']);
        $tierOrder = array_flip(array_keys(Plank::TIERS));

        $people = $candidates->map(fn (Candidate $c) => [
            ...CandidateResource::make($c)->resolve(),
            'planks' => $c->planks
                ->sortBy(fn (Plank $p) => [$tierOrder[$p->tier] ?? 9, $p->rank])
                ->map(fn (Plank $p) => [
                    'key' => $p->key,
                    'title' => $p->title,
                    'summary' => $p->summary,
                    'tier' => $p->tier,
                    'tags' => $p->tags->map(fn ($t) => ['slug' => $t->slug, 'name' => $t->name])->values()->all(),
                ])
                ->values()
                ->all(),
        ])->values()->all();

        return [
            'people' => $people,
            'subjects' => $this->subjects($candidates, $tierOrder),
            'scorecards' => $this->scorecards($candidates),
        ];
    }

    /**
     * Every subject at least one of them campaigns on: per candidate their
     * strongest tier and the planks behind it. Shared subjects first (by
     * how many share it, then combined emphasis), then the ones only one
     * candidate raises.
     */
    private function subjects(Collection $candidates, array $tierOrder): array
    {
        $bySubject = [];
        foreach ($candidates as $candidate) {
            $planks = $candidate->planks->sortBy(fn (Plank $p) => [$tierOrder[$p->tier] ?? 9, $p->rank]);
            foreach ($planks as $plank) {
                foreach ($plank->tags as $tag) {
                    $bySubject[$tag->slug] ??= ['slug' => $tag->slug, 'name' => $tag->name, 'by' => []];
                    $bySubject[$tag->slug]['by'][$candidate->id] ??= ['tier' => $plank->tier, 'planks' => []];
                    $bySubject[$tag->slug]['by'][$candidate->id]['planks'][] = $plank->title;
                }
            }
        }

        return collect($bySubject)
            ->sortBy(fn (array $row) => [
                -count($row['by']),
                collect($row['by'])->sum(fn ($cell) => $tierOrder[$cell['tier']] ?? 9),
                $row['name'],
            ])
            ->map(fn (array $row) => [...$row, 'shared' => count($row['by']), 'by' => (object) $row['by']])
            ->values()
            ->all();
    }

    /**
     * Each scorecard with the publisher's categories and statements, and
     * every compared candidate's stance on each (or that they didn't
     * respond).
     */
    private function scorecards(Collection $candidates): array
    {
        return Scorecard::with(['categories.items', 'responses' => fn ($q) => $q->whereIn('candidate_id', $candidates->pluck('id'))])
            ->orderBy('title')
            ->get()
            ->map(function (Scorecard $scorecard) use ($candidates) {
                $responses = $scorecard->responses->keyBy('candidate_id');
                $answers = ScorecardAnswer::whereIn('response_id', $responses->pluck('id'))->get()->groupBy('response_id');

                return [
                    'key' => $scorecard->key,
                    'title' => $scorecard->title,
                    'publisher' => $scorecard->publisher,
                    // candidate id => true / false / null (nothing on file)
                    'responded' => (object) $candidates->mapWithKeys(fn ($c) => [$c->id => $responses->get($c->id)?->responded])->all(),
                    'categories' => $scorecard->categories->map(fn ($category) => [
                        'key' => $category->key,
                        'name' => $category->name,
                        'items' => $category->items->map(function ($item) use ($candidates, $responses, $answers) {
                            $stances = $candidates->mapWithKeys(function ($c) use ($item, $responses, $answers) {
                                $response = $responses->get($c->id);
                                $stance = $response?->responded
                                    ? ($answers->get($response->id)?->firstWhere('item_id', $item->id)?->stance ?? 'no_response')
                                    : 'no_response';

                                return [$c->id => $stance];
                            });

                            return [
                                'key' => $item->key,
                                'statement' => $item->statement,
                                'stances' => (object) $stances->all(),
                                // Among those who answered, did they disagree?
                                'differs' => $stances->reject(fn ($s) => $s === 'no_response')->unique()->count() > 1,
                            ];
                        })->values()->all(),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
