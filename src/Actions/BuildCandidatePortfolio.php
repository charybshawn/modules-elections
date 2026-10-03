<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Http\Resources\ArticleResource;
use Cultpantry\Elections\Http\Resources\CandidateResource;
use Cultpantry\Elections\Http\Resources\EntryResource;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Entry;
use Illuminate\Support\Collection;

/**
 * A candidate's portfolio as the page reads it, top to bottom:
 *
 *   About               -- the bio, then background facts grouped by
 *                          Entry::BACKGROUND_TOPICS ('background')
 *   Platform            -- planks, grouped by topic
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
        'platform' => ['title' => 'Platform', 'kinds' => ['plank'], 'grouped' => true],
        'words' => ['title' => 'In their own words', 'kinds' => ['statement', 'qa_answer'], 'grouped' => true],
        'record' => ['title' => 'Prior record', 'kinds' => ['prior_record'], 'grouped' => true],
        'endorsements' => ['title' => 'Endorsements', 'kinds' => ['endorsement'], 'grouped' => false],
        'finance' => ['title' => 'Campaign finance', 'kinds' => ['finance'], 'grouped' => false],
    ];

    public function handle(Candidate $candidate): array
    {
        $candidate->load(['entries', 'articles']);

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
            'sections' => $sections,
            'articles' => ArticleResource::collection($candidate->articles)->resolve(),
            'entryCount' => $candidate->entries->count(),
            'now' => now()->getTimestamp(),
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
