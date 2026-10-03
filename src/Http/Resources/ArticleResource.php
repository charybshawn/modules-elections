<?php

namespace Cultpantry\Elections\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Cultpantry\Elections\Models\Article
 */
class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
            'outlet' => $this->outlet,
            'published_on' => $this->published_on?->toDateString(),
            'summary' => $this->summary,
            // When it was linked to the candidate whose page this is (only
            // present when loaded through Candidate::articles).
            'linked_at' => $this->whenPivotLoaded('elections_article_candidate', fn () => $this->pivot->created_at?->toIso8601String()),
            'candidates' => $this->whenLoaded('candidates', fn () => $this->candidates
                ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug])
                ->values()
                ->all()),
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)->resolve(), []),
        ];
    }
}
