<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A third-party candidate scorecard such as Vote4Tomorrow: fixed statements
 * under the publisher's category headers, and each candidate's stance.
 *
 * @property int $id
 * @property string $key
 * @property string $title
 * @property string|null $publisher
 * @property string|null $url
 * @property string|null $about
 * @property \Illuminate\Support\Carbon|null $retrieved_on
 */
class Scorecard extends Model
{
    protected $table = 'elections_scorecards';

    protected $fillable = ['key', 'title', 'publisher', 'url', 'about', 'retrieved_on'];

    protected $casts = [
        'retrieved_on' => 'date',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(ScorecardCategory::class)->orderBy('position')->orderBy('id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ScorecardResponse::class);
    }
}
