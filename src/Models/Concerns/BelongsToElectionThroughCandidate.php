<?php

namespace Cultpantry\Elections\Models\Concerns;

use Cultpantry\Elections\Models\Election;
use Illuminate\Database\Eloquent\Builder;

/**
 * For records that have no election of their own (entries, planks): they
 * belong to the election of their candidate.
 */
trait BelongsToElectionThroughCandidate
{
    public function scopeForElection(Builder $query, Election|int $election): Builder
    {
        return $query->whereHas('candidate', fn (Builder $candidate) => $candidate->forElection($election));
    }
}
