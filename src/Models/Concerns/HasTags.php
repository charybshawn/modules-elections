<?php

namespace Cultpantry\Elections\Models\Concerns;

use Cultpantry\Elections\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Subject tags from the module's vocabulary (see Tag).
 */
trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable', 'elections_taggables')
            ->withTimestamps()
            ->orderBy('elections_tags.name');
    }
}
