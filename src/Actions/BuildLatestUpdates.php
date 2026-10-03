<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Plank;
use Illuminate\Support\Collection;

/**
 * The dashboard's "Latest updates" list, newest first: what each candidate
 * gained on a given day (grouped, so one big import reads as one line, not
 * eighty), plus each news story as it arrives.
 */
class BuildLatestUpdates
{
    public const LIMIT = 40;

    public function handle(): array
    {
        $kindLabels = [
            'plank' => ['platform statement', 'platform statements'],
            'statement' => ['statement', 'statements'],
            'qa_answer' => ['Q&A answer', 'Q&A answers'],
            'prior_record' => ['council-record item', 'council-record items'],
            'background' => ['background fact', 'background facts'],
            'endorsement' => ['endorsement', 'endorsements'],
            'finance' => ['finance record', 'finance records'],
        ];

        $entries = Entry::query()->whereNotNull('created_at')
            ->with('candidate:id,name,slug,photo_url,status')
            ->get(['id', 'candidate_id', 'kind', 'created_at']);
        $planks = Plank::query()->whereNotNull('created_at')->get(['id', 'candidate_id', 'key', 'title', 'created_at']);

        $day = fn ($at) => $at->copy()->timezone(config('app.timezone'))->toDateString();
        $newPlanks = $planks->groupBy(fn (Plank $p) => $p->candidate_id.'|'.$day($p->created_at));

        $candidateUpdates = $entries
            ->filter(fn (Entry $e) => $e->candidate !== null)
            ->groupBy(fn (Entry $e) => $e->candidate_id.'|'.$day($e->created_at))
            ->map(function (Collection $group, string $key) use ($kindLabels, $newPlanks) {
                $candidate = $group->first()->candidate;
                $counts = $group->countBy('kind')->sortDesc()->map(fn (int $n, string $kind) => $n.' '.($kindLabels[$kind][$n === 1 ? 0 : 1] ?? $kind))->values()->all();
                $titles = ($newPlanks->get($key) ?? collect())->sortBy('id')->pluck('title')->values();

                return [
                    'type' => 'candidate',
                    'id' => 'c-'.$key,
                    'at' => $group->max('created_at')->toIso8601String(),
                    'name' => $candidate->name,
                    'slug' => $candidate->slug,
                    'photo_url' => $candidate->photo_url,
                    'total' => $group->count(),
                    'counts' => $counts,
                    'planks' => $titles->take(1)->all(),
                    'more_planks' => max(0, $titles->count() - 1),
                    // Send readers to the platform when planks are what changed.
                    'tab' => $titles->isNotEmpty() || $group->contains('kind', 'plank') ? 'platform' : null,
                ];
            });

        $articleUpdates = Article::query()->whereNotNull('created_at')->withCount('candidates')->get()
            ->map(fn (Article $a) => [
                'type' => 'article',
                'id' => 'a-'.$a->id,
                'at' => $a->created_at->toIso8601String(),
                'title' => $a->title,
                'url' => $a->url,
                'outlet' => $a->outlet,
                'published_on' => $a->published_on?->toDateString(),
                'candidates' => $a->candidates_count,
            ]);

        return $candidateUpdates->values()
            ->concat($articleUpdates)
            ->sortByDesc('at')
            ->take(self::LIMIT)
            ->values()
            ->all();
    }
}
