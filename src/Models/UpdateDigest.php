<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A run of `elections:notify-updates`. covers_through is the watermark: the
 * next run reports only what was created after it. A row with no counts is
 * the baseline recorded on the first run, when nothing is emailed.
 *
 * @property \Illuminate\Support\Carbon $covers_through
 * @property array<string, mixed>|null $counts
 * @property int $recipients
 */
class UpdateDigest extends Model
{
    protected $table = 'elections_update_digests';

    protected $fillable = ['covers_through', 'counts', 'recipients'];

    protected $casts = [
        'covers_through' => 'datetime',
        'counts' => 'array',
    ];
}
