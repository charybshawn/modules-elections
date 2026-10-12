<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A news story about a party as a whole: its leadership, platform, caucus
 * or conduct. Stories about one candidate stay with that candidate.
 *
 * @property int $id
 * @property int $party_id
 * @property string $title
 * @property string $url
 * @property string|null $outlet
 * @property \Illuminate\Support\Carbon|null $published_on
 * @property string|null $summary
 * @property string $type one of PartyArticle::TYPES
 */
class PartyArticle extends Model
{
    /** What the story is: the party's conduct and decisions, an opinion piece, or context (profiles, polls, how to vote). */
    public const TYPES = [
        'legislative' => 'Party conduct and decisions',
        'opinion' => 'Opinion',
        'background' => 'Background',
    ];

    protected $table = 'elections_party_articles';

    protected $fillable = ['title', 'url', 'url_hash', 'outlet', 'published_on', 'summary', 'type'];

    protected function casts(): array
    {
        return ['published_on' => 'date'];
    }

    public static function hashFor(string $url): string
    {
        return hash('sha256', mb_strtolower(rtrim(trim($url), '/')));
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
