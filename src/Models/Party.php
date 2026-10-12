<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * What a voter should know about the party a provincial candidate runs for:
 * who leads it now, how long it has existed, how often it has formed a
 * government, and what it stands for and proposes. Dated (`as_of`) because
 * parties change: a leader can resign, a party can merge or split. Candidates
 * point at it by name (their `party` field).
 *
 * @property int $id
 * @property string $name
 * @property string $slug the party page's address
 * @property string|null $full_name
 * @property string|null $leader
 * @property string|null $leader_note
 * @property string|null $founded
 * @property string|null $founded_note
 * @property int|null $times_in_government
 * @property string|null $government_note
 * @property string|null $principles
 * @property list<string>|null $platform
 * @property \Illuminate\Support\Carbon $as_of
 * @property list<array{label: string, url: string}>|null $sources
 * @property string|null $slate_source_url where the province-wide candidate list comes from
 * @property \Illuminate\Support\Carbon|null $slate_as_of
 */
class Party extends Model
{
    protected $table = 'elections_parties';

    protected $fillable = [
        'name', 'slug', 'full_name', 'leader', 'leader_note', 'founded', 'founded_note',
        'times_in_government', 'government_note', 'principles', 'platform', 'as_of', 'sources',
        'slate_source_url', 'slate_as_of',
    ];

    protected static function booted(): void
    {
        static::saving(function (Party $party) {
            $party->slug ??= Str::slug($party->name);
        });
    }

    protected function casts(): array
    {
        return ['as_of' => 'date', 'slate_as_of' => 'date', 'platform' => 'array', 'sources' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** The "about" part: leader, existence, record in government, principles and platform, dated and sourced. */
    public function profile(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'full_name' => $this->full_name,
            'leader' => $this->leader,
            'leader_note' => $this->leader_note,
            'founded' => $this->founded,
            'founded_note' => $this->founded_note,
            'times_in_government' => $this->times_in_government,
            'government_note' => $this->government_note,
            'principles' => $this->principles,
            'platform' => $this->platform ?? [],
            'as_of' => $this->as_of?->toDateString(),
            'sources' => $this->sources ?? [],
        ];
    }

    /** The fact-check record, in the order it should be read. */
    public function claims(): HasMany
    {
        return $this->hasMany(PartyClaim::class)->orderBy('position')->orderBy('id');
    }

    /** News about the party as a whole, newest first. */
    public function articles(): HasMany
    {
        return $this->hasMany(PartyArticle::class)->orderByRaw('published_on is null')->orderByDesc('published_on')->orderByDesc('id');
    }

    /** Every candidate the party is running across the province, in riding order. */
    public function slate(): HasMany
    {
        return $this->hasMany(PartySlateCandidate::class)->orderBy('position')->orderBy('id');
    }
}
