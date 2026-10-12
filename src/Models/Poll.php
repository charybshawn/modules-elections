<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One published province-wide voting-intention poll, in percent of decided
 * (and leaning) voters, keyed by the party's name as the parties table has it.
 *
 * @property int $id
 * @property string $pollster
 * @property \Illuminate\Support\Carbon|null $fielded_from
 * @property \Illuminate\Support\Carbon $fielded_to
 * @property \Illuminate\Support\Carbon|null $published_on
 * @property int|null $sample_size
 * @property string|null $method
 * @property array<string, float> $results
 * @property string $source_url
 * @property string|null $source_name
 * @property string|null $note
 */
class Poll extends Model
{
    protected $table = 'elections_polls';

    protected $fillable = ['pollster', 'fielded_from', 'fielded_to', 'published_on', 'sample_size', 'method', 'results', 'source_url', 'source_name', 'note'];

    protected function casts(): array
    {
        return ['fielded_from' => 'date', 'fielded_to' => 'date', 'published_on' => 'date', 'results' => 'array'];
    }
}
