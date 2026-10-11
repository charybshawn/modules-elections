<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One election the module tracks: the Salmon Arm municipal election or the
 * BC provincial election in the Salmon Arm-Shuswap riding. Candidates,
 * articles and events belong to one; everything else (entries, planks,
 * pulse mentions, scorecard responses) follows its candidate.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $kind municipal or provincial
 * @property string|null $riding
 */
class Election extends Model
{
    public const MUNICIPAL = 'salmon-arm-2026';

    public const PROVINCIAL = 'bc-provincial-salmon-arm-shuswap';

    protected $table = 'elections_elections';

    protected $fillable = ['slug', 'name', 'kind', 'riding'];

    /** What a candidate can run for, by kind of election. */
    public const OFFICES_BY_KIND = [
        'municipal' => ['mayor', 'councillor', 'trustee'],
        'provincial' => ['mla'],
    ];

    public static function municipal(): self
    {
        return static::where('slug', self::MUNICIPAL)->firstOrFail();
    }

    public static function provincial(): self
    {
        return static::where('slug', self::PROVINCIAL)->firstOrFail();
    }

    public static function bySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    public function isProvincial(): bool
    {
        return $this->kind === 'provincial';
    }

    /**
     * The offices open in this election, as value => label.
     *
     * @return array<string, string>
     */
    public function offices(): array
    {
        return array_intersect_key(Candidate::OFFICES, array_flip(self::OFFICES_BY_KIND[$this->kind] ?? []));
    }

    /**
     * What the pages need to know about this election.
     *
     * @return array{slug: string, name: string, kind: string, riding: string|null, offices: array<string, string>}
     */
    public function toPage(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'kind' => $this->kind,
            'riding' => $this->riding,
            'offices' => $this->offices(),
        ];
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ElectionEvent::class);
    }
}
