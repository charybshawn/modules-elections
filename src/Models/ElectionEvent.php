<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A dated election happening: an all-candidates forum, a nomination
 * deadline, advance or general voting. Listed on the module's dashboard.
 * Named ElectionEvent so it never gets confused with the host's own Event
 * (audit log) model.
 *
 * @property int $id
 * @property string $title
 * @property string $kind one of ElectionEvent::KINDS
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property string|null $location
 * @property string|null $url
 * @property string|null $description
 */
class ElectionEvent extends Model
{
    protected $table = 'elections_events';

    public const KINDS = [
        'forum' => 'All-candidates forum',
        'deadline' => 'Deadline',
        'advance_voting' => 'Advance voting',
        'general_voting' => 'General voting day',
        'other' => 'Other',
    ];

    protected $fillable = [
        'title',
        'kind',
        'starts_at',
        'ends_at',
        'location',
        'url',
        'description',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Still upcoming or under way today -- an event stays listed until its
     * day is over, not the minute it starts.
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where(fn ($q) => $q
            ->where('starts_at', '>=', now()->startOfDay())
            ->orWhere('ends_at', '>=', now()->startOfDay()));
    }
}
