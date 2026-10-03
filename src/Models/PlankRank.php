<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One point in a plank's rank history: the tier and rank it held from
 * recorded_at until the next row.
 *
 * @property int $id
 * @property int $plank_id
 * @property string $tier one of Plank::TIERS
 * @property int $rank
 * @property \Illuminate\Support\Carbon $recorded_at
 */
class PlankRank extends Model
{
    protected $table = 'elections_plank_ranks';

    public $timestamps = false;

    protected $fillable = ['tier', 'rank', 'recorded_at'];

    protected $casts = [
        'rank' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function plank(): BelongsTo
    {
        return $this->belongsTo(Plank::class);
    }
}
