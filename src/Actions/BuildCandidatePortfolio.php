<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Http\Resources\ArticleResource;
use Cultpantry\Elections\Http\Resources\CandidateResource;
use Cultpantry\Elections\Http\Resources\EntryResource;
use Cultpantry\Elections\Http\Resources\PlankResource;
use Cultpantry\Elections\Http\Resources\TagResource;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Plank;
use Cultpantry\Elections\Models\ScorecardAnswer;
use Cultpantry\Elections\Models\ScorecardResponse;
use Illuminate\Support\Collection;

/**
 * A candidate's portfolio as the page reads it, top to bottom:
 *
 *   About               -- the bio, then background facts grouped by
 *                          Entry::BACKGROUND_TOPICS ('background')
 *   Background &        -- community roles, public service, affiliations
 *   Affiliations           and past activities (Entry::AFFILIATION_TOPICS)
 *                          ('affiliations'; always present, may be empty)
 *   Platform            -- planks in Plank::TIERS, ranked by the candidate's
 *                          own emphasis, each with the statements behind it
 *                          ('platform'; null when there's nothing yet)
 *   Scorecards          -- their stance on each statement of third-party
 *                          questionnaires like Vote4Tomorrow, under the
 *                          publisher's own category headers ('scorecards')
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
    /** The "in their own words" style tabs, by tab id. Shared with UpdateTabs so the update feed files an item under the same tab the page shows it on. */
    public const SECTIONS = [
        'words' => ['title' => 'In their own words', 'kinds' => ['statement', 'qa_answer'], 'grouped' => true],
        'record' => ['title' => 'Prior record', 'kinds' => ['prior_record'], 'grouped' => true],
        'endorsements' => ['title' => 'Endorsements', 'kinds' => ['endorsement'], 'grouped' => false],
        'finance' => ['title' => 'Campaign finance', 'kinds' => ['finance'], 'grouped' => false],
    ];

    public function handle(Candidate $candidate): array
    {
        $candidate->load(['entries.tags', 'articles.tags', 'planks.tags', 'planks.entries.tags', 'planks.rankHistory']);

        $background = $candidate->entries->where('kind', 'background')->values();

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
            'background' => $this->byTopic($background, array_diff_key(Entry::BACKGROUND_TOPICS, array_flip(Entry::AFFILIATION_TOPICS))),
            'affiliations' => $this->byTopic($background, array_intersect_key(Entry::BACKGROUND_TOPICS, array_flip(Entry::AFFILIATION_TOPICS))),
            'platform' => $this->platform($candidate),
            'scorecards' => $this->scorecards($candidate),
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
     * Each scorecard the candidate has a response on file for: the
     * publisher's categories and statements in their order, the candidate's
     * stance on each, how everyone who answered split on it, and our
     * per-category reading. A candidate who didn't answer gets the
     * scorecard with every stance 'no_response' and no reading.
     */
    private function scorecards(Candidate $candidate): array
    {
        $responses = ScorecardResponse::with(['scorecard.categories.items.tags', 'answers', 'takeaways'])
            ->where('candidate_id', $candidate->id)
            ->get()
            ->sortBy(fn (ScorecardResponse $r) => $r->scorecard->title);

        return $responses->map(function (ScorecardResponse $response) {
            $scorecard = $response->scorecard;
            $stances = $response->answers->pluck('stance', 'item_id');
            $takeaways = $response->takeaways->pluck('summary', 'category_id');

            // How every candidate who answered split on each statement.
            $field = ScorecardAnswer::query()
                ->whereHas('response', fn ($q) => $q->where('scorecard_id', $scorecard->id)->where('responded', true))
                ->selectRaw('item_id, stance, count(*) as total')
                ->groupBy('item_id', 'stance')
                ->get()
                ->groupBy('item_id')
                ->map(fn ($rows) => $rows->pluck('total', 'stance')->map(fn ($n) => (int) $n)->all());
            $respondents = ScorecardResponse::where('scorecard_id', $scorecard->id)->where('responded', true)->count();

            // Every other respondent's answer per statement, for the "where
            // this candidate sits in the field" strip.
            $others = ScorecardAnswer::query()
                ->with('response.candidate:id,name,slug')
                ->whereHas('response', fn ($q) => $q->where('scorecard_id', $scorecard->id)->where('responded', true)->where('candidate_id', '!=', $response->candidate_id))
                ->get()
                ->groupBy('item_id')
                ->map(fn ($answers) => $answers
                    ->map(fn (ScorecardAnswer $a) => ['name' => $a->response->candidate->name, 'slug' => $a->response->candidate->slug, 'stance' => $a->stance])
                    ->sortBy('name')
                    ->values()
                    ->all());

            $totals = array_fill_keys(array_keys(ScorecardAnswer::STANCES), 0);
            $categories = $scorecard->categories->map(function ($category) use ($response, $stances, $takeaways, $field, $others, &$totals) {
                $items = $category->items->map(function ($item) use ($response, $stances, $field, $others, &$totals) {
                    $stance = $response->responded ? ($stances[$item->id] ?? 'no_response') : 'no_response';
                    $totals[$stance]++;

                    return [
                        'key' => $item->key,
                        'statement' => $item->statement,
                        'tags' => TagResource::collection($item->tags)->resolve(),
                        'stance' => $stance,
                        'field' => $field[$item->id] ?? [],
                        'others' => $others[$item->id] ?? [],
                    ];
                })->values()->all();

                return [
                    'key' => $category->key,
                    'name' => $category->name,
                    'intro' => $category->intro,
                    'local_context' => $category->local_context,
                    'sources' => $category->sources ?? [],
                    'items' => $items,
                    'takeaway' => $response->responded ? ($takeaways[$category->id] ?? null) : null,
                ];
            })->values()->all();

            return [
                'key' => $scorecard->key,
                'title' => $scorecard->title,
                'publisher' => $scorecard->publisher,
                'url' => $scorecard->url,
                'about' => $scorecard->about,
                'retrieved_on' => $scorecard->retrieved_on?->toDateString(),
                // Latest change to anything this candidate's scorecard rests on,
                // for the "new since your last visit" tab marker.
                'changed_at' => collect([$response->created_at, $response->updated_at, $response->answers->max('updated_at'), $response->takeaways->max('updated_at'), $scorecard->categories->max('updated_at')])
                    ->filter()->max()?->toIso8601String(),
                'source_url' => $response->source_url,
                'responded' => $response->responded,
                'respondents' => $respondents,
                'totals' => $totals,
                'categories' => $categories,
            ];
        })->values()->all();
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
