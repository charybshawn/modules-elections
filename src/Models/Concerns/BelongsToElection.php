<?php

namespace Cultpantry\Elections\Models\Concerns;

use Cultpantry\Elections\Models\Election;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For the models that belong to an election (candidates, articles, events).
 * A row created without one belongs to the municipal election, which is what
 * every row was before the provincial election existed.
 */
trait BelongsToElection
{
    public static function bootBelongsToElection(): void
    {
        static::creating(function ($model) {
            $model->election_id ??= Election::municipal()->id;
        });
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function scopeForElection(Builder $query, Election|int $election): Builder
    {
        return $query->where($this->getTable().'.election_id', $election instanceof Election ? $election->id : $election);
    }
}
