<?php

namespace Cultpantry\Elections\Models;

use Cultpantry\Elections\Models\Concerns\BelongsToElectionThroughCandidate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The two synopses shown under a bill on a member's voting record: what the
 * bill is for, and why the member voted as they did and how the question
 * ended. Written from the legislative record and the member's own words in
 * debate, and labelled as AI-written on the page; `basis` says what the
 * "why" rests on so the page can be straight about it.
 *
 * @property int $id
 * @property int $candidate_id
 * @property string $bill_key matches Vote::$bill_key
 * @property string|null $purpose
 * @property string|null $vote_analysis
 * @property string|null $basis one of BillNote::BASES
 * @property list<array{label: string, url: string}>|null $sources
 */
class BillNote extends Model
{
    use BelongsToElectionThroughCandidate;

    protected $table = 'elections_bill_notes';

    /** What the "why" rests on, most to least direct. */
    public const BASES = [
        'spoke' => 'Their own words in debate',
        'caucus' => 'Their caucus position, as stated in debate',
        'record' => 'The recorded votes and the bill alone',
    ];

    protected $fillable = ['bill_key', 'purpose', 'vote_analysis', 'basis', 'sources'];

    protected function casts(): array
    {
        return ['sources' => 'array'];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
