<?php

namespace Cultpantry\Elections\Support;

use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Models\Entry;

/**
 * Every controlled value list the pages need for labels and selects, in one
 * prop, so a list can't be passed to one page and forgotten on another.
 */
class Options
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        return [
            'offices' => Candidate::OFFICES,
            'statuses' => Candidate::STATUSES,
            'kinds' => Entry::KINDS,
            'topics' => Entry::TOPICS,
            'sourceTypes' => Entry::SOURCE_TYPES,
            'eventKinds' => ElectionEvent::KINDS,
        ];
    }
}
