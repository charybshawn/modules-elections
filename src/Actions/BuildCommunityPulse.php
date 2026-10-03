<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Http\Resources\TagResource;
use Cultpantry\Elections\Models\Plank;
use Cultpantry\Elections\Models\PulseIssue;
use Cultpantry\Elections\Models\PulseSnapshot;
use Illuminate\Support\Collection;

/**
 * The Community Pulse page: one snapshot (the latest unless a date is
 * asked for), compared with the snapshot before it, and each issue set
 * beside the candidates whose platforms cover the same topic.
 */
class BuildCommunityPulse
{
    public function handle(?string $date = null): array
    {
        $snapshots = PulseSnapshot::orderByDesc('taken_on')->get(['id', 'taken_on']);

        $current = $date
            ? PulseSnapshot::whereDate('taken_on', $date)->first()
            : PulseSnapshot::orderByDesc('taken_on')->first();

        if ($current === null) {
            return ['snapshot' => null, 'snapshots' => $this->dates($snapshots)];
        }

        $current->load(['issues.tags', 'mentions.candidate']);
        $previous = PulseSnapshot::with(['issues', 'mentions'])
            ->whereDate('taken_on', '<', $current->taken_on)
            ->orderByDesc('taken_on')
            ->first();

        $planks = Plank::with(['candidate:id,name,slug', 'tags:id'])->get();
        $planksByTopic = $planks->groupBy('topic');

        return [
            'snapshot' => [
                'id' => $current->id,
                'taken_on' => $current->taken_on->toDateString(),
                'period_from' => $current->period_from?->toDateString(),
                'period_to' => $current->period_to?->toDateString(),
                'threads_read' => $current->threads_read,
                'commenters' => $current->commenters,
                'sources' => $current->sources,
                'method_note' => $current->method_note,
                'conclusions' => $current->conclusions ?? [],
                'previous_taken_on' => $previous?->taken_on->toDateString(),
                'issues' => $current->issues->map(fn (PulseIssue $issue) => [
                    'key' => $issue->key,
                    'title' => $issue->title,
                    'topic' => $issue->topic,
                    'voices' => $issue->voices,
                    'voices_change' => $this->change($issue->voices, $previous?->issues->firstWhere('key', $issue->key)?->voices, $previous !== null),
                    'support_pct' => $issue->support_pct,
                    'oppose_pct' => $issue->oppose_pct,
                    'mixed_pct' => $issue->mixed_pct,
                    'heat' => $issue->heat,
                    'summary' => $issue->summary,
                    'wants' => $issue->wants ?? [],
                    'questions' => $issue->questions ?? [],
                    'tags' => TagResource::collection($issue->tags)->resolve(),
                    // Planks sharing a tag with the issue; without tags, the
                    // broader topic match (and none for "other").
                    'coverage' => match (true) {
                        $issue->tags->isNotEmpty() => $this->coverage($planks->filter(fn (Plank $p) => $p->tags->pluck('id')->intersect($issue->tags->pluck('id'))->isNotEmpty())),
                        $issue->topic === 'other' => null,
                        default => $this->coverage($planksByTopic->get($issue->topic, collect())),
                    },
                    'coverage_by' => $issue->tags->isNotEmpty() ? 'tags' : 'topic',
                ])->values()->all(),
                'mentions' => $current->mentions->map(fn ($m) => [
                    'name' => $m->candidate->name,
                    'slug' => $m->candidate->slug,
                    'mentions' => $m->mentions,
                    'commenters' => $m->commenters,
                    'mentions_change' => $this->change($m->mentions, $previous?->mentions->firstWhere('candidate_id', $m->candidate_id)?->mentions, $previous !== null),
                ])->values()->all(),
            ],
            'snapshots' => $this->dates($snapshots),
        ];
    }

    /**
     * Candidates with a plank on this topic, by tier -- coverage, not a
     * verdict on anyone's position.
     *
     * @param  Collection<int, Plank>  $planks
     */
    private function coverage(Collection $planks): array
    {
        return collect(Plank::TIERS)->keys()->mapWithKeys(fn (string $tier) => [
            $tier => $planks->where('tier', $tier)
                ->sortBy(fn (Plank $p) => $p->candidate->name)
                ->map(fn (Plank $p) => ['name' => $p->candidate->name, 'slug' => $p->candidate->slug, 'plank' => $p->title])
                ->unique('slug')
                ->values()
                ->all(),
        ])->all();
    }

    /**
     * Change since the previous snapshot: null with no previous snapshot,
     * 'new' when the item wasn't there before, otherwise the difference.
     */
    private function change(int $now, ?int $before, bool $hasPrevious): int|string|null
    {
        if (! $hasPrevious) {
            return null;
        }

        return $before === null ? 'new' : $now - $before;
    }

    private function dates(Collection $snapshots): array
    {
        return $snapshots->map(fn ($s) => $s->taken_on->toDateString())->values()->all();
    }
}
