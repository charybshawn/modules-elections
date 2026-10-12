<?php

namespace Cultpantry\Elections\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Cultpantry\Elections\Models\Vote
 */
class VoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'voted_on' => $this->voted_on?->toDateString(),
            'year' => $this->voted_on?->year,
            'title' => $this->title,
            'reference' => $this->reference,
            'bill_key' => $this->bill_key,
            'bill_title' => $this->bill_title,
            'stage' => $this->stage,
            'description' => $this->description,
            'vote' => $this->vote,
            'outcome' => $this->outcome,
            'tally_for' => $this->tally_for,
            'tally_against' => $this->tally_against,
            'source_url' => $this->source_url,
            'source_name' => $this->source_name,
            // When it went on file, and the tab it appears on, for "new since your last visit".
            'added_at' => $this->created_at?->toIso8601String(),
            'tab' => 'record',
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)->resolve(), []),
        ];
    }
}
