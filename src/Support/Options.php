<?php

namespace Cultpantry\Elections\Support;

use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Plank;
use Cultpantry\Elections\Models\ScorecardAnswer;

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
            'backgroundTopics' => Entry::BACKGROUND_TOPICS,
            'sourceTypes' => Entry::SOURCE_TYPES,
            'plankTiers' => Plank::TIERS,
            'planStatuses' => Plank::PLAN_STATUSES,
            'planAspects' => Plank::PLAN_ASPECTS,
            'analysisParts' => Plank::ANALYSIS_PARTS,
            'scorecardStances' => ScorecardAnswer::STANCES,
            'eventKinds' => ElectionEvent::KINDS,
        ];
    }
}
