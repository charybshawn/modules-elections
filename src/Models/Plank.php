<?php

namespace Cultpantry\Elections\Models;

use Cultpantry\Elections\Models\Concerns\HasTags;
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
    use HasTags;

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
        'plan_status',
        'plan_summary',
        'plan_details',
        'analysis',
        'analysis_on',
    ];

    /**
     * The AI analysis's four parts, in the order the page shows them: the
     * plain-language meaning, the case for, the case against, and a short
     * conclusion.
     */
    public const ANALYSIS_PARTS = [
        'meaning' => 'What this means',
        'works' => 'Why it could work',
        'fails' => 'Why it might not',
        'details' => 'The devil is in the details',
    ];

    /**
     * How much of a plan the candidate has conveyed -- about what's been
     * said publicly, not whether the plan is any good.
     */
    public const PLAN_STATUSES = [
        'specific' => 'Specific plan',
        'partial' => 'Some specifics',
        'none' => 'No plan conveyed to date',
    ];

    /**
     * What a plan detail is about, in the order the page lists them.
     */
    public const PLAN_ASPECTS = [
        'how' => 'How',
        'funding' => 'Paying for it',
        'timeline' => 'When',
        'measure' => 'How we\'d know',
        'partners' => 'Who with',
        'other' => 'Other details',
    ];

    protected $casts = [
        'plan_details' => 'array',
        'analysis' => 'array',
        'analysis_on' => 'date',
        'rank' => 'integer',
        'priority_position' => 'integer',
        'has_commitment' => 'boolean',
    ];

    protected static function booted(): void
    {
        // History: one row when the plank appears, then one per change of
        // tier or rank. A re-import that changes nothing writes nothing.
        static::saved(function (Plank $plank) {
            if ($plank->wasRecentlyCreated || $plank->wasChanged(['tier', 'rank'])) {
                $plank->rankHistory()->create([
                    'tier' => $plank->tier,
                    'rank' => $plank->rank,
                    'recorded_at' => now(),
                ]);
            }
        });
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Every place it has held, oldest first.
     */
    public function rankHistory(): HasMany
    {
        return $this->hasMany(PlankRank::class)->orderBy('recorded_at')->orderBy('id');
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
