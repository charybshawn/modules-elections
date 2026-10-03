<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One position a candidate campaigns on, with the candidate-published
 * statements that make it (its plank-kind entries) as its sources.
 *
 * Tier and rank are the research pass's judgment of how much the candidate
 * themselves emphasizes it -- named as a priority, repeated across their own
 * material, backed by a specific commitment -- never a judgment of whether
 * the position is right. The rationale says why, in a sentence.
 *
 * @property int $id
 * @property int $candidate_id
 * @property string $key
 * @property string $title
 * @property string $topic one of Entry::TOPICS
 * @property string|null $summary
 * @property string $tier one of Plank::TIERS
 * @property int $rank
 * @property string|null $rationale
 * @property int|null $priority_position
 * @property bool $has_commitment
 */
class Plank extends Model
{
    protected $table = 'elections_planks';

    /**
     * Ordered as the Platform tab reads, top to bottom.
     */
    public const TIERS = [
        'top' => 'Top priorities',
        'also' => 'Also campaigning on',
        'mentioned' => 'Mentioned',
    ];

    protected $fillable = [
        'key',
        'title',
        'topic',
        'summary',
        'tier',
        'rank',
        'rationale',
        'priority_position',
        'has_commitment',
    ];

    protected $casts = [
        'rank' => 'integer',
        'priority_position' => 'integer',
        'has_commitment' => 'boolean',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * The statements this plank rests on, newest first.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class)
            ->orderByRaw('published_on is null')
            ->orderByDesc('published_on')
            ->orderBy('id');
    }
}
