<?php

namespace Cultpantry\Elections\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Cultpantry\Elections\Models\Entry
 */
class EntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'topic' => $this->topic,
            'summary' => $this->summary,
            'quote' => $this->quote,
            'question' => $this->question,
            'source_url' => $this->source_url,
            'source_type' => $this->source_type,
            'source_name' => $this->source_name,
            'published_on' => $this->published_on?->toDateString(),
            // When it went on file -- what "new since your last visit" compares.
            'added_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
