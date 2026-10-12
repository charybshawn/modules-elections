<?php

namespace Cultpantry\Elections\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One candidate a party is running in one riding, province-wide, as the
 * official candidate list names them. Only the riding's own candidates have
 * full candidate pages; the rest are a name and a riding.
 *
 * @property int $id
 * @property int $party_id
 * @property string $riding
 * @property string $name
 * @property string|null $incumbent_riding the seat they hold, when a sitting MLA is running for re-election
 */
class PartySlateCandidate extends Model
{
    protected $table = 'elections_party_slate';

    protected $fillable = ['riding', 'name', 'incumbent_riding', 'position'];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
