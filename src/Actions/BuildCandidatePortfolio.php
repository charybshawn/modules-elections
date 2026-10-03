<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Http\Resources\ArticleResource;
use Cultpantry\Elections\Http\Resources\CandidateResource;
use Cultpantry\Elections\Http\Resources\EntryResource;
use Cultpantry\Elections\Http\Resources\PlankResource;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Plank;
use Illuminate\Support\Collection;

/**
 * A candidate's portfolio as the page reads it, top to bottom:
 *
 *   About               -- the bio, then background facts grouped by
 *                          Entry::BACKGROUND_TOPICS ('background')
 *   Platform            -- planks in Plank::TIERS, ranked by the candidate's
 *                          own emphasis, each with the statements behind it
 *                          ('platform'; null when there's nothing yet)
 *   In their own words  -- statements and Q&A answers, grouped by topic
 *   Prior record        -- incumbents' past council record
 *   Endorsements
 *   Campaign finance
 *
 * plus the news coverage linked to them. Topic groups follow their list's
 * order (not alphabetical, not by count) so two candidates' pages always
 * list topics in the same place. Empty sections and groups are left out.
 */
class BuildCandidatePortfolio
{
    private const SECTIONS = [
        'words' => ['title' => 'In their own words', 'kinds' => ['statement', 'qa_answer'], 'grouped' => true],
        'record' => ['title' => 'Prior record', 'kinds' => ['prior_record'], 'grouped' => true],
        'endorsements' => ['title' => 'Endorsements', 'kinds' => ['endorsement'], 'grouped' => false],
        'finance' => ['title' => 'Campaign finance', 'kinds' => ['finance'], 'grouped' => false],
    ];

    public function handle(Candidate $candidate): array
    {
        $candidate->load(['entries', 'articles', 'planks.entries']);

        $sections = [];
        foreach (self::SECTIONS as $key => $section) {
            $entries = $candidate->entries->whereIn('kind', $section['kinds'])->values();
            if ($entries->isEmpty()) {
                continue;
            }

            $sections[] = [
                'key' => $key,
                'title' => $section['title'],
                'groups' => $section['grouped'] ? $this->byTopic($entries, Entry::TOPICS) : [[
                    'topic' => null,
                    'entries' => EntryResource::collection($entries)->resolve(),
                ]],
            ];
        }

        return [
            'candidate' => CandidateResource::make($candidate)->resolve(),
            'background' => $this->byTopic($candidate->entries->where('kind', 'background')->values(), Entry::BACKGROUND_TOPICS),
            'platform' => $this->platform($candidate),
            'sections' => $sections,
            'articles' => ArticleResource::collection($candidate->articles)->resolve(),
            'entryCount' => $candidate->entries->count(),
            'now' => now()->getTimestamp(),
        ];
    }

    /**
     * The Platform tab: planks by tier (empty tiers left out), plus any plank
     * statement not yet tied to a plank, and whether the whole platform
     * rests on too little to rank with confidence.
     */
    private function platform(Candidate $candidate): ?array
    {
        $unranked = $candidate->entries->where('kind', 'plank')->whereNull('plank_id')->values();
        if ($candidate->planks->isEmpty() && $unranked->isEmpty()) {
            return null;
        }

        $tiers = collect(Plank::TIERS)
            ->map(fn (string $title, string $tier) => [
                'tier' => $tier,
                'title' => $title,
                'planks' => PlankResource::collection($candidate->planks->where('tier', $tier)->values())->resolve(),
            ])
            ->filter(fn (array $group) => $group['planks'] !== [])
            ->values()
            ->all();

        $sourceCount = $candidate->entries->where('kind', 'plank')->pluck('source_url')->unique()->count();

        return [
            'tiers' => $tiers,
            'unranked' => EntryResource::collection($unranked)->resolve(),
            'source_count' => $sourceCount,
            'limited_sources' => $sourceCount < 2,
        ];
    }

    /**
     * @param  Collection<int, Entry>  $entries
     * @param  array<string, string>  $topics
     */
    private function byTopic(Collection $entries, array $topics): array
    {
        return collect(array_keys($topics))
            ->map(fn (string $topic) => [
                'topic' => $topic,
                'entries' => EntryResource::collection($entries->where('topic', $topic)->values())->resolve(),
            ])
            ->filter(fn (array $group) => $group['entries'] !== [])
            ->values()
            ->all();
    }
}
