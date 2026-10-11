<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * A tag from the module's controlled vocabulary. A *subject* tag files under
 * one Entry::TOPICS heading and is applied, many-to-many, to planks,
 * statements, articles, Community Pulse issues and scorecard statements. A
 * *news* tag names a type of story (personal, legislative, opinion,
 * background) and is applied only to articles, one per article; it has no
 * subject page and never shows in the subject views.
 *
 * @property int $id
 * @property string $slug
 * @property string $kind one of Tag::KINDS
 * @property string $topic
 * @property string $name
 * @property string|null $description
 */
class Tag extends Model
{
    protected $table = 'elections_tags';

    public const KINDS = [
        'subject' => 'Subject',
        'news' => 'Type of news',
    ];

    protected $fillable = ['slug', 'kind', 'topic', 'name', 'description'];

    /** The subject vocabulary: what Browse by subject, the heat map and search list. */
    public function scopeSubjects(Builder $query): Builder
    {
        return $query->where($this->getTable().'.kind', 'subject');
    }

    public function scopeNews(Builder $query): Builder
    {
        return $query->where($this->getTable().'.kind', 'news');
    }

    /**
     * Removes tag links whose record is gone. Deletes happen through
     * database cascades and bulk queries that skip model events, so this
     * runs after every import and every delete rather than per model;
     * nothing reads a dangling link in the meantime (every lookup joins the
     * record's own table).
     */
    public static function pruneOrphans(): int
    {
        $removed = 0;
        foreach (Relation::morphMap() as $alias => $class) {
            if (! str_starts_with($alias, 'elections_')) {
                continue;
            }
            $table = (new $class)->getTable();
            $removed += DB::table('elections_taggables')
                ->where('taggable_type', $alias)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from($table)->whereColumn("{$table}.id", 'elections_taggables.taggable_id'))
                ->delete();
        }

        return $removed;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function planks(): MorphToMany
    {
        return $this->morphedByMany(Plank::class, 'taggable', 'elections_taggables')->withTimestamps();
    }

    public function entries(): MorphToMany
    {
        return $this->morphedByMany(Entry::class, 'taggable', 'elections_taggables')->withTimestamps();
    }

    public function articles(): MorphToMany
    {
        return $this->morphedByMany(Article::class, 'taggable', 'elections_taggables')->withTimestamps();
    }

    public function pulseIssues(): MorphToMany
    {
        return $this->morphedByMany(PulseIssue::class, 'taggable', 'elections_taggables')->withTimestamps();
    }

    public function scorecardItems(): MorphToMany
    {
        return $this->morphedByMany(ScorecardItem::class, 'taggable', 'elections_taggables')->withTimestamps();
    }
}
