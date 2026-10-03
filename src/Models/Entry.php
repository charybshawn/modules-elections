<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sourced fact about a candidate: a platform plank, something they said,
 * an answer to a question, a past council vote, an endorsement, a finance
 * filing. Every entry carries the URL it came from -- a portfolio of
 * unsourced claims about a real person running for office is exactly what
 * this module must never become.
 *
 * @property int $id
 * @property int $candidate_id
 * @property string $kind one of Entry::KINDS
 * @property string $topic one of Entry::TOPICS
 * @property string $summary
 * @property string|null $quote
 * @property string|null $question
 * @property string $source_url
 * @property string $source_type one of Entry::SOURCE_TYPES
 * @property string|null $source_name
 * @property \Illuminate\Support\Carbon|null $published_on
 * @property string $match_hash
 */
class Entry extends Model
{
    protected $table = 'elections_entries';

    /**
     * Ordered as the portfolio's sections read, top to bottom.
     */
    public const KINDS = [
        'plank' => 'Platform',
        'statement' => 'Statement',
        'qa_answer' => 'Q&A answer',
        'prior_record' => 'Prior record',
        'endorsement' => 'Endorsement',
        'finance' => 'Campaign finance',
    ];

    public const TOPICS = [
        'housing' => 'Housing',
        'taxes_budget' => 'Taxes & budget',
        'infrastructure' => 'Infrastructure',
        'downtown_development' => 'Downtown & development',
        'transportation' => 'Transportation',
        'environment' => 'Environment',
        'public_safety' => 'Public safety',
        'recreation_parks' => 'Recreation & parks',
        'economy_business' => 'Economy & business',
        'social_services' => 'Social services',
        'governance_transparency' => 'Governance & transparency',
        'other' => 'Other',
    ];

    public const SOURCE_TYPES = [
        'candidate_site' => 'Candidate website',
        'instagram' => 'Instagram',
        'facebook_page' => 'Facebook page',
        'facebook_group' => 'Facebook group',
        'news' => 'News',
        'forum' => 'All-candidates forum',
        'city' => 'City of Salmon Arm',
        'elections_bc' => 'Elections BC',
        'other' => 'Other',
    ];

    protected $fillable = [
        'kind',
        'topic',
        'summary',
        'quote',
        'question',
        'source_url',
        'source_type',
        'source_name',
        'published_on',
    ];

    protected $casts = [
        'published_on' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Entry $entry) {
            $entry->match_hash = static::hashFor($entry->source_url, $entry->quote, $entry->summary);
        });
    }

    /**
     * The import's "same entry" key: the same words from the same source.
     * Whitespace and case are normalized so a re-research that copies the
     * quote with different line breaks still matches.
     */
    public static function hashFor(string $sourceUrl, ?string $quote, string $summary): string
    {
        $normalize = fn (string $s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
        $text = $quote !== null && trim($quote) !== '' ? $quote : $summary;

        return sha1(trim($sourceUrl).'|'.$normalize($text));
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
