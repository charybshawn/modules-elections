<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing a party says, set against what the record shows. A claim is a
 * statement of fact the party makes (about itself, its record or its
 * opponents) or a pledge; the rating says how far the record supports it,
 * and the evidence lists the votes, government record, past statements and
 * reporting it rests on, each with its source. Dated: it is true as of
 * `checked_on`.
 *
 * @property int $id
 * @property int $party_id
 * @property string $key
 * @property string $kind one of PartyClaim::KINDS
 * @property string $claim
 * @property string $source_url
 * @property string|null $source_name
 * @property string|null $topic
 * @property string $rating one of PartyClaim::RATINGS
 * @property string $summary
 * @property list<array{kind: string, text: string, label: string|null, url: string|null}>|null $evidence
 * @property \Illuminate\Support\Carbon $checked_on
 */
class PartyClaim extends Model
{
    public const KINDS = ['claim' => 'Claim', 'pledge' => 'Pledge'];

    /** How far the record supports the claim, best to worst, then the one that is not a judgement. */
    public const RATINGS = [
        'holds_up' => 'Holds up',
        'mostly_holds_up' => 'Mostly holds up',
        'mixed' => 'Mixed',
        'mostly_does_not' => 'Mostly doesn\'t hold up',
        'does_not_hold_up' => 'Doesn\'t hold up',
        'untestable' => 'Can\'t be tested yet',
    ];

    /** The kinds of record a check can rest on. */
    public const EVIDENCE = [
        'votes' => 'Recorded votes',
        'government_record' => 'Government record',
        'past_statement' => 'Past statements',
        'reporting' => 'Independent reporting',
    ];

    protected $table = 'elections_party_claims';

    protected $fillable = ['key', 'position', 'kind', 'claim', 'source_url', 'source_name', 'topic', 'rating', 'summary', 'evidence', 'checked_on'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'checked_on' => 'date'];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
