<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How often a candidate came up in a pulse snapshot's threads: plain counts,
 * deliberately without sentiment.
 *
 * @property int $id
 * @property int $snapshot_id
 * @property int $candidate_id
 * @property int $mentions
 * @property int $commenters
 */
class PulseMention extends Model
{
    protected $table = 'elections_pulse_mentions';

    protected $fillable = ['candidate_id', 'mentions', 'commenters'];

    protected $casts = [
        'mentions' => 'integer',
        'commenters' => 'integer',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(PulseSnapshot::class, 'snapshot_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
