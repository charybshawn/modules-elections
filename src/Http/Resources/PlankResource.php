<?php

namespace Cultpantry\Elections\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Cultpantry\Elections\Models\Plank
 */
class PlankResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $this->title,
            'topic' => $this->topic,
            'summary' => $this->summary,
            'tier' => $this->tier,
            'rank' => $this->rank,
            'rationale' => $this->rationale,
            'priority_position' => $this->priority_position,
            'has_commitment' => $this->has_commitment,
            // AI analysis: {impact, challenges, risks} => [{text, sources}]; null until written.
            'analysis' => $this->analysis ? [
                'on' => $this->analysis_on?->toDateString(),
                'parts' => $this->analysis,
            ] : null,
            // Null until the plan has been assessed.
            'plan' => $this->plan_status === null ? null : [
                'status' => $this->plan_status,
                'summary' => $this->plan_summary,
                'details' => $this->plan_details ?? [],
            ],
            // Distinct places the candidate said it -- one of the signals.
            'source_count' => $this->entries->pluck('source_url')->unique()->count(),
            'sources' => EntryResource::collection($this->entries)->resolve(),
            'added_at' => $this->created_at?->toIso8601String(),
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)->resolve(), []),
            // Where it has stood over the campaign, oldest first; the page
            // compares the last two to say "Up from #4".
            'history' => $this->whenLoaded('rankHistory', fn () => $this->rankHistory
                ->map(fn ($point) => [
                    'tier' => $point->tier,
                    'rank' => $point->rank,
                    'recorded_at' => $point->recorded_at->toIso8601String(),
                ])
                ->values()
                ->all(), []),
        ];
    }
}
