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
            // Distinct places the candidate said it -- one of the signals.
            'source_count' => $this->entries->pluck('source_url')->unique()->count(),
            'sources' => EntryResource::collection($this->entries)->resolve(),
            'added_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
