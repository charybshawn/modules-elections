<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A model's projected vote shares for one riding. A projection, not a poll:
 * it applies province-wide and regional poll swings to the last election's
 * result in the riding.
 *
 * @property int $id
 * @property string $riding
 * @property string|null $rating
 * @property string|null $leader
 * @property list<array{party: string, percent: float, margin: float|null}> $shares
 * @property \Illuminate\Support\Carbon $as_of
 * @property string $source_url
 */
class RidingProjection extends Model
{
    protected $table = 'elections_riding_projections';

    protected $fillable = ['riding', 'rating', 'leader', 'shares', 'as_of', 'source_url'];

    protected function casts(): array
    {
        return ['shares' => 'array', 'as_of' => 'date'];
    }
}
