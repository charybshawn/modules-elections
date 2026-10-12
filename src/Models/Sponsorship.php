<?php

namespace Cultpantry\Elections\Models;

use Cultpantry\Elections\Models\Concerns\BelongsToElectionThroughCandidate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bill or motion a member introduced themselves (the Legislature records one
 * sponsor per bill; there is no co-sponsor), and how far it got. This is
 * different from a vote: introducing a bill usually has no recorded division.
 *
 * @property int $id
 * @property int $candidate_id
 * @property string $kind one of Sponsorship::KINDS
 * @property string $reference
 * @property string $session
 * @property string $title
 * @property string|null $summary
 * @property \Illuminate\Support\Carbon|null $introduced_on
 * @property string $status one of Sponsorship::STATUSES
 * @property string|null $status_note
 * @property string|null $context
 * @property string $source_url
 * @property string|null $source_name
 */
class Sponsorship extends Model
{
    use BelongsToElectionThroughCandidate;

    protected $table = 'elections_sponsorships';

    public const KINDS = ['bill' => 'Bill', 'motion' => 'Motion'];

    /** How far it got, in order. */
    public const STATUSES = [
        'introduced' => 'Introduced (first reading)',
        'second_reading' => 'Passed second reading',
        'committee' => 'In committee',
        'third_reading' => 'Passed third reading',
        'law' => 'Became law',
        'moved' => 'Moved (not yet decided)',
        'agreed' => 'Agreed to',
        'defeated' => 'Defeated',
        'not_proceeded' => 'Did not proceed',
    ];

    protected $fillable = ['kind', 'reference', 'session', 'title', 'summary', 'introduced_on', 'status', 'status_note', 'context', 'source_url', 'source_name'];

    protected function casts(): array
    {
        return ['introduced_on' => 'date'];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
