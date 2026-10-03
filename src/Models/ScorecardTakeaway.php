<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Our AI-assisted reading of what a candidate's stances in one category
 * could mean in practice for Salmon Arm -- labelled as such on the page,
 * never presented as the candidate's words.
 *
 * @property int $id
 * @property int $response_id
 * @property int $category_id
 * @property string $summary
 */
class ScorecardTakeaway extends Model
{
    protected $table = 'elections_scorecard_takeaways';

    protected $fillable = ['category_id', 'summary'];

    public function response(): BelongsTo
    {
        return $this->belongsTo(ScorecardResponse::class, 'response_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ScorecardCategory::class, 'category_id');
    }
}
