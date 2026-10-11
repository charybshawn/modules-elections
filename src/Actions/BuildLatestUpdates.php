<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Plank;
use Cultpantry\Elections\Support\UpdateTabs;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The dashboard's "Latest updates" list, newest first: what each candidate
 * gained on a given day (grouped, so one big import reads as one line, not
 * eighty), plus each news story as it arrives.
 */
class BuildLatestUpdates
{
    public const LIMIT = 40;

    /** How many of a day's new items each update links to; the rest are counted. */
    public const ITEM_LIMIT = 4;

    /** Singular and plural labels for each entry kind; the update email uses them too. */
    public const KIND_LABELS = [
        'plank' => ['platform statement', 'platform statements'],
        'statement' => ['statement', 'statements'],
        'qa_answer' => ['Q&A answer', 'Q&A answers'],
        'prior_record' => ['council-record item', 'council-record items'],
        'background' => ['background fact', 'background facts'],
        'endorsement' => ['endorsement', 'endorsements'],
        'finance' => ['finance record', 'finance records'],
    ];

    public function handle(): array
    {
        $kindLabels = self::KIND_LABELS;

        $entries = Entry::query()->whereNotNull('created_at')
            ->with('candidate:id,name,slug,photo_url,status')
            ->get(['id', 'candidate_id', 'plank_id', 'kind', 'topic', 'question', 'quote', 'summary', 'published_on', 'created_at']);
        $allPlanks = Plank::query()->get(['id', 'candidate_id', 'key', 'title', 'created_at']);
        $planks = $allPlanks->whereNotNull('created_at');
        $planksById = $allPlanks->keyBy('id');

        $day = fn ($at) => $at->copy()->timezone(config('app.timezone'))->toDateString();
        $newPlanks = $planks->groupBy(fn (Plank $p) => $p->candidate_id.'|'.$day($p->created_at));

        $candidateUpdates = $entries
            ->filter(fn (Entry $e) => $e->candidate !== null)
            ->groupBy(fn (Entry $e) => $e->candidate_id.'|'.$day($e->created_at))
            ->map(function (Collection $group, string $key) use ($kindLabels, $newPlanks, $planksById) {
                $candidate = $group->first()->candidate;
                $counts = $group->countBy('kind')->sortDesc()->map(fn (int $n, string $kind) => $n.' '.($kindLabels[$kind][$n === 1 ? 0 : 1] ?? $kind))->values()->all();
                $newOnes = ($newPlanks->get($key) ?? collect())->sortBy('id')->values();
                $titles = $newOnes->pluck('title');
                $items = $this->items($group, $newOnes, $planksById, $kindLabels);

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
                    // The new content itself, each a link that opens the candidate's page on it.
                    'items' => $items->take(self::ITEM_LIMIT)->values()->all(),
                    'more_items' => max(0, $items->count() - self::ITEM_LIMIT),
                    // Send readers to the platform when planks are what changed.
                    'tab' => $titles->isNotEmpty() || $group->contains('kind', 'plank') ? 'platform' : null,
                ];
            });

        $articleUpdates = Article::query()->whereNotNull('created_at')->withCount('candidates')->with('candidates:id,name')->get()
            ->map(fn (Article $a) => [
                'type' => 'article',
                'id' => 'a-'.$a->id,
                'at' => $a->created_at->toIso8601String(),
                'title' => $a->title,
                'url' => $a->url,
                // Browsers that support text fragments scroll to and mark where the story names the candidate.
                'link' => $this->withMention($a->url, $a->candidates->first()?->name),
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

    /**
     * One link per piece of new content: new planks first, then everything
     * else, each plank once even when several of its statements are new.
     *
     * @return Collection<int, array{label: string, kind: string, tab: string, focus: string}>
     */
    private function items(Collection $group, Collection $newPlanks, Collection $planksById, array $kindLabels): Collection
    {
        $items = collect();
        $seenPlanks = [];

        $addPlank = function (Plank $plank) use ($items, &$seenPlanks, $kindLabels) {
            if (isset($seenPlanks[$plank->id])) {
                return;
            }
            $seenPlanks[$plank->id] = true;
            $items->push(['label' => $plank->title, 'kind' => $kindLabels['plank'][0], 'tab' => 'platform', 'focus' => 'plank-'.$plank->key]);
        };

        foreach ($newPlanks as $plank) {
            $addPlank($plank);
        }

        foreach ($group->sortBy('id') as $entry) {
            $plank = $entry->plank_id ? $planksById->get($entry->plank_id) : null;
            if ($plank !== null) {
                $addPlank($plank);

                continue;
            }
            $tab = UpdateTabs::forEntry($entry->kind, $entry->topic);
            if ($tab === null) {
                continue;
            }
            $text = $entry->kind === 'qa_answer' && $entry->question ? $entry->question : ($entry->quote ?: $entry->summary);
            $items->push([
                'label' => Str::limit(trim(preg_replace('/\s+/u', ' ', $text)), 90),
                'kind' => $kindLabels[$entry->kind][0] ?? $entry->kind,
                'tab' => $tab,
                'focus' => 'entry-'.$entry->id,
            ]);
        }

        return $items;
    }

    private function withMention(string $url, ?string $name): string
    {
        if ($name === null || str_contains($url, '#')) {
            return $url;
        }
        $surname = Str::afterLast(trim($name), ' ');

        return $url.'#:~:text='.rawurlencode($surname);
    }
}
