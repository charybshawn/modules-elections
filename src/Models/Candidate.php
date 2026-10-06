<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Someone seeking a seat in the Salmon Arm 2026 general local election.
 * One election only, so the name alone identifies a candidate (and is the
 * XML import's match key).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $office one of Candidate::OFFICES
 * @property string $status one of Candidate::STATUSES
 * @property bool $is_incumbent
 * @property string|null $occupation
 * @property string|null $bio
 * @property string|null $bio_source_url
 * @property string|null $website
 * @property string|null $facebook_url
 * @property string|null $instagram_url
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $photo_url
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $profile_changed_at
 * @property \Illuminate\Support\Carbon|null $notes_changed_at
 */
class Candidate extends Model
{
    protected $table = 'elections_candidates';

    public const OFFICES = [
        'mayor' => 'Mayor',
        'councillor' => 'Councillor',
        'trustee' => 'School trustee',
    ];

    /** SQL that sorts mayor, then council, then school trustees. */
    public const OFFICE_ORDER_SQL = "case office when 'mayor' then 0 when 'councillor' then 1 else 2 end";

    public const STATUSES = [
        'declared' => 'Declared',
        'nominated' => 'Officially nominated',
        'withdrawn' => 'Withdrawn',
    ];

    protected $fillable = [
        'name',
        'office',
        'status',
        'is_incumbent',
        'occupation',
        'bio',
        'bio_source_url',
        'website',
        'facebook_url',
        'instagram_url',
        'email',
        'phone',
        'photo_url',
        'photo_credit',
        'notes',
    ];

    protected $casts = [
        'is_incumbent' => 'boolean',
        'profile_changed_at' => 'datetime',
        'notes_changed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Slug follows the name, so a renamed candidate's URL follows too.
        static::saving(function (Candidate $candidate) {
            if ($candidate->slug === null || $candidate->isDirty('name')) {
                $candidate->slug = static::uniqueSlug($candidate->name, $candidate->id);
            }

            // When the public profile and the admin notes last changed, kept
            // apart so a notes-only edit doesn't flag the profile as updated
            // for everyone (see the update feed).
            $dirty = array_keys($candidate->getDirty());
            $profile = array_diff($dirty, ['updated_at', 'profile_changed_at', 'notes_changed_at', 'notes', 'slug']);
            if (! $candidate->exists || $profile !== []) {
                $candidate->profile_changed_at = now();
            }
            if (in_array('notes', $dirty, true) || ($candidate->notes !== null && ! $candidate->exists)) {
                $candidate->notes_changed_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    private static function uniqueSlug(string $name, ?int $ignoreId): string
    {
        $base = Str::slug($name) ?: 'candidate';
        $slug = $base;

        for ($i = 2; static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class)
            ->orderByRaw('published_on is null')
            ->orderByDesc('published_on')
            ->orderBy('id');
    }

    /**
     * Their platform, most emphasized first (tier, then rank).
     */
    public function planks(): HasMany
    {
        return $this->hasMany(Plank::class)
            ->orderByRaw("case tier when 'top' then 0 when 'also' then 1 else 2 end")
            ->orderBy('rank')
            ->orderBy('id');
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'elections_article_candidate')
            ->withTimestamps()
            ->orderByDesc('published_on');
    }

    public function scorecardResponses(): HasMany
    {
        return $this->hasMany(ScorecardResponse::class);
    }
}
