<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One research file that `elections:import-pending` has loaded (or marked as
 * already loaded). The hash is the file's SHA-256, so editing a file after
 * it was imported makes it pending again.
 *
 * @property int $id
 * @property string $filename
 * @property string $hash
 * @property string $summary
 * @property array<int, string>|null $problems
 * @property bool $marked_only
 * @property \Illuminate\Support\Carbon $imported_at
 */
class ImportRecord extends Model
{
    protected $table = 'elections_imports';

    protected $fillable = ['filename', 'hash', 'summary', 'problems', 'marked_only', 'imported_at'];

    protected $casts = [
        'problems' => 'array',
        'marked_only' => 'boolean',
        'imported_at' => 'datetime',
    ];
}
