<?php

namespace Cultpantry\Elections\Models;

use Cultpantry\Elections\Models\Concerns\BelongsToElectionThroughCandidate;
use Cultpantry\Elections\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How someone voted on one recorded question: a division in the Legislature
 * or a recorded vote on a council or board. Only votes a public record names
 * the member in are here; a voice vote has no names, so it has no row. Each
 * row carries the record it came from, and can be tagged by subject.
 *
 * @property int $id
 * @property int $candidate_id
 * @property string $body one of Vote::BODIES
 * @property \Illuminate\Support\Carbon $voted_on
 * @property string $title
 * @property string|null $reference
 * @property string|null $bill_key groups a bill's votes; unique within a session
 * @property string|null $bill_title
 * @property string|null $stage one of Vote::STAGES
 * @property string|null $description the record's own wording
 * @property string $vote one of Vote::VOTES
 * @property string|null $outcome one of Vote::OUTCOMES
 * @property int|null $tally_for
 * @property int|null $tally_against
 * @property string $source_url
 * @property string|null $source_name
 * @property string $match_hash
 */
class Vote extends Model
{
    use BelongsToElectionThroughCandidate;
    use HasTags;

    protected $table = 'elections_votes';

    public const BODIES = [
        'legislature' => 'Legislative Assembly',
        'council' => 'Council',
        'board' => 'Board',
    ];

    public const VOTES = [
        'yea' => 'Yea',
        'nay' => 'Nay',
        'abstained' => 'Abstained',
    ];

    /**
     * Where in a bill's life the vote was. The first group are a bill's own stages and
     * read up front; the rest (amendments, clause votes, procedural motions about the
     * bill) fold under it.
     */
    public const STAGES = [
        'motion' => 'Motion',
        'first_reading' => 'First reading',
        'second_reading' => 'Second reading',
        'committal' => 'Committal',
        'report_stage' => 'Report stage',
        'third_reading' => 'Third reading',
        'amendment' => 'Amendment',
        'clause' => 'Committee clause',
        'procedural' => 'Procedural',
    ];

    /** The stages shown as the bill's own votes; every other stage folds under "amendments and committee votes". */
    public const MAIN_STAGES = ['motion', 'first_reading', 'second_reading', 'committal', 'report_stage', 'third_reading'];

    public const OUTCOMES = [
        'passed' => 'Passed',
        'defeated' => 'Defeated',
    ];

    protected $fillable = [
        'body',
        'voted_on',
        'title',
        'reference',
        'bill_key',
        'bill_title',
        'stage',
        'description',
        'vote',
        'outcome',
        'tally_for',
        'tally_against',
        'source_url',
        'source_name',
    ];

    protected $casts = [
        'voted_on' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Vote $vote) {
            $vote->match_hash = static::hashFor($vote->source_url, $vote->voted_on?->toDateString() ?? '', $vote->reference, $vote->description ?? $vote->title);
        });
    }

    /** The same vote: the same record, day, question and wording. Whitespace and case don't matter. */
    public static function hashFor(string $sourceUrl, string $votedOn, ?string $reference, string $wording): string
    {
        $normalize = fn (string $s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));

        return sha1(trim($sourceUrl).'|'.$votedOn.'|'.$normalize((string) $reference).'|'.$normalize($wording));
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
