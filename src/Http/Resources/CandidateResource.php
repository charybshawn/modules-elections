<?php

namespace Cultpantry\Elections\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Cultpantry\Elections\Models\Candidate
 */
class CandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'office' => $this->office,
            'status' => $this->status,
            'is_incumbent' => $this->is_incumbent,
            'occupation' => $this->occupation,
            'bio' => $this->bio,
            'bio_source_url' => $this->bio_source_url,
            'website' => $this->website,
            'facebook_url' => $this->facebook_url,
            'instagram_url' => $this->instagram_url,
            'email' => $this->email,
            'phone' => $this->phone,
            'photo_url' => $this->photo_url,
            'photo_credit' => $this->photo_credit,
            // Research notes (doubts, conflicting sources, follow-ups) are
            // working material for admins, not for invited viewers.
            'notes' => $this->when((bool) $request->user()?->isAdmin(), $this->notes),
            'entries_count' => $this->whenCounted('entries'),
            'articles_count' => $this->whenCounted('articles'),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // When the public profile last changed (not the notes), and the
            // notes -- admins only, like the notes themselves.
            'profile_changed_at' => max($this->created_at, $this->profile_changed_at ?? $this->created_at)?->toIso8601String(),
            'notes_changed_at' => $this->when((bool) $request->user()?->isAdmin(), fn () => $this->notes_changed_at?->toIso8601String()),
        ];
    }
}
