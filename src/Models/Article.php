<?php

namespace Cultpantry\Elections\Models;

use Cultpantry\Elections\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A news story about the election (Salmon Arm Observer, Castanet, ...),
 * linked to every candidate it covers. Matched on URL.
 *
 * @property int $id
 * @property string $title
 * @property string $url
 * @property string $url_hash
 * @property string|null $outlet
 * @property \Illuminate\Support\Carbon|null $published_on
 * @property string|null $summary
 */
class Article extends Model
{
    use HasTags;

    protected $table = 'elections_articles';

    protected $fillable = [
        'title',
        'url',
        'outlet',
        'published_on',
        'summary',
    ];

    protected $casts = [
        'published_on' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Article $article) {
            $article->url_hash = static::hashFor($article->url);
        });
    }

    public static function hashFor(string $url): string
    {
        return sha1(trim($url));
    }

    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(Candidate::class, 'elections_article_candidate')->withTimestamps()->orderBy('name');
    }
}
