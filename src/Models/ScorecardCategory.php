<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $scorecard_id
 * @property string $key
 * @property string $name
 * @property string|null $intro
 * @property string|null $local_context
 * @property array<int, string>|null $sources
 * @property int $position
 */
class ScorecardCategory extends Model
{
    protected $table = 'elections_scorecard_categories';

    protected $fillable = ['key', 'name', 'intro', 'local_context', 'sources', 'position'];

    protected $casts = [
        'sources' => 'array',
        'position' => 'integer',
    ];

    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(Scorecard::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ScorecardItem::class, 'category_id')->orderBy('position')->orderBy('id');
    }
}
