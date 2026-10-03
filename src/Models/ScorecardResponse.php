<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One candidate's answers to a scorecard, and our per-category reading of
 * them. responded = false means the publisher lists them as not answering.
 *
 * @property int $id
 * @property int $scorecard_id
 * @property int $candidate_id
 * @property string|null $source_url
 * @property bool $responded
 */
class ScorecardResponse extends Model
{
    protected $table = 'elections_scorecard_responses';

    protected $fillable = ['candidate_id', 'source_url', 'responded'];

    protected $casts = [
        'responded' => 'boolean',
    ];

    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(Scorecard::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ScorecardAnswer::class, 'response_id');
    }

    public function takeaways(): HasMany
    {
        return $this->hasMany(ScorecardTakeaway::class, 'response_id');
    }
}
