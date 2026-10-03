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
            'candidates' => $this->whenLoaded('candidates', fn () => $this->candidates
                ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug])
                ->values()
                ->all()),
        ];
    }
}
