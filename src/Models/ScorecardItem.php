<?php

namespace Cultpantry\Elections\Models;

use Cultpantry\Elections\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $category_id
 * @property string $key
 * @property string $statement
 * @property int $position
 */
class ScorecardItem extends Model
{
    use HasTags;

    protected $table = 'elections_scorecard_items';

    protected $fillable = ['key', 'statement', 'position'];

    protected $casts = [
        'position' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ScorecardCategory::class, 'category_id');
    }
}
