<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $response_id
 * @property int $item_id
 * @property string $stance
 */
class ScorecardAnswer extends Model
{
    public const STANCES = [
        'supportive' => 'Supportive',
        'neutral' => 'Neutral',
        'opposed' => 'Opposed',
        'no_response' => 'No response',
    ];

    protected $table = 'elections_scorecard_answers';

    protected $fillable = ['item_id', 'stance'];

    public function response(): BelongsTo
    {
        return $this->belongsTo(ScorecardResponse::class, 'response_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ScorecardItem::class, 'item_id');
    }
}
