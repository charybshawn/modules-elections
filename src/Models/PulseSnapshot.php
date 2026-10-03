<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Community Pulse pass: what residents were discussing about the
 * election, read from public-to-members discussion (the Rant and Rave group)
 * and summarized. It holds aggregates and paraphrases only -- never a
 * resident's name, words or comment history.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $taken_on
 * @property \Illuminate\Support\Carbon|null $period_from
 * @property \Illuminate\Support\Carbon|null $period_to
 * @property int $threads_read
 * @property int $commenters
 * @property string|null $sources
 * @property string|null $method_note
 * @property array<int, array{text: string, issue: string|null}>|null $conclusions
 */
class PulseSnapshot extends Model
{
    protected $table = 'elections_pulse_snapshots';

    protected $fillable = [
        'taken_on', 'period_from', 'period_to', 'threads_read', 'commenters',
        'sources', 'method_note', 'conclusions',
    ];

    protected $casts = [
        'taken_on' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
        'threads_read' => 'integer',
        'commenters' => 'integer',
        'conclusions' => 'array',
    ];

    /**
     * Most-raised first.
     */
    public function issues(): HasMany
    {
        return $this->hasMany(PulseIssue::class, 'snapshot_id')->orderByDesc('voices')->orderBy('id');
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(PulseMention::class, 'snapshot_id')->orderByDesc('mentions')->orderBy('id');
    }
}
