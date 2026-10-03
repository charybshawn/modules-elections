<?php

namespace Cultpantry\Elections\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Times go out as local wall-clock strings (Y-m-d\TH:i), not ISO with an
 * offset: they were entered as the source stated them, and the page shows
 * them as-is rather than shifting them into the viewer's timezone.
 *
 * @mixin \Cultpantry\Elections\Models\ElectionEvent
 */
class ElectionEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'kind' => $this->kind,
            'starts_at' => $this->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $this->ends_at?->format('Y-m-d\TH:i'),
            'location' => $this->location,
            'url' => $this->url,
            'description' => $this->description,
        ];
    }
}
