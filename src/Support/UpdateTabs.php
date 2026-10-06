<?php

namespace Cultpantry\Elections\Support;

use Cultpantry\Elections\Actions\BuildCandidatePortfolio;
use Cultpantry\Elections\Models\Entry;

/**
 * Which tab of a candidate's page an entry is shown on. The update feed and
 * the entry resource both ask here, so an unread count and the tab that
 * lights up for it can never disagree.
 *
 * Tab ids: about, affiliations, platform, scorecards, the portfolio's own
 * section keys (words, record, endorsements, finance), news and notes.
 */
class UpdateTabs
{
    public static function forEntry(?string $kind, ?string $topic): ?string
    {
        if ($kind === 'background') {
            return in_array($topic, Entry::AFFILIATION_TOPICS, true) ? 'affiliations' : 'about';
        }
        if ($kind === 'plank') {
            return 'platform';
        }
        foreach (BuildCandidatePortfolio::SECTIONS as $key => $section) {
            if (in_array($kind, $section['kinds'], true)) {
                return $key;
            }
        }

        // A kind the page doesn't show has no tab, so it never counts as unread.
        return null;
    }
}
