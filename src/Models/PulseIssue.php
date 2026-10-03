<?php

namespace Cultpantry\Elections\Models;

use Cultpantry\Elections\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One issue within a pulse snapshot, weighted by how many distinct people
 * raised it (not how many comments), with a rough stance split and
 * paraphrased wants and questions.
 *
 * @property int $id
 * @property int $snapshot_id
 * @property string $key
 * @property string $title
 * @property string $topic one of Entry::TOPICS
 * @property int $voices
 * @property int|null $support_pct
 * @property int|null $oppose_pct
 * @property int|null $mixed_pct
 * @property string $heat one of PulseIssue::HEAT
 * @property string|null $summary
 * @property array<int, string>|null $wants
 * @property array<int, string>|null $questions
 */
class PulseIssue extends Model
{
    use HasTags;

    protected $table = 'elections_pulse_issues';

    public const HEAT = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ];

    protected $fillable = [
        'key', 'title', 'topic', 'voices', 'support_pct', 'oppose_pct', 'mixed_pct',
        'heat', 'summary', 'wants', 'questions',
    ];

    protected $casts = [
        'voices' => 'integer',
        'support_pct' => 'integer',
        'oppose_pct' => 'integer',
        'mixed_pct' => 'integer',
        'wants' => 'array',
        'questions' => 'array',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(PulseSnapshot::class, 'snapshot_id');
    }
}
