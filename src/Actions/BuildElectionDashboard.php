<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Election;
use Cultpantry\Elections\Http\Resources\ArticleResource;
use Cultpantry\Elections\Http\Resources\CandidateResource;
use Cultpantry\Elections\Http\Resources\ElectionEventResource;
use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Models\Party;
use Cultpantry\Elections\Models\RidingProjection;

/**
 * The module's landing page: who's running (mayor first), what's coming up,
 * and the latest coverage.
 */
class BuildElectionDashboard
{
    public function handle(?Election $election = null): array
    {
        $election ??= Election::municipal();

        $candidates = Candidate::forElection($election)
            ->withCount(['entries', 'articles'])
            ->orderByRaw(Candidate::OFFICE_ORDER_SQL)
            ->orderByRaw("case status when 'withdrawn' then 1 else 0 end")
            ->orderBy('name')
            ->get();

        $upcoming = ElectionEvent::forElection($election)->upcoming()->orderBy('starts_at')->get();
        $past = ElectionEvent::forElection($election)->whereNotIn('id', $upcoming->pluck('id'))->orderByDesc('starts_at')->get();

        return [
            'candidates' => CandidateResource::collection($candidates)->resolve(),
            'upcomingEvents' => ElectionEventResource::collection($upcoming)->resolve(),
            'pastEvents' => ElectionEventResource::collection($past)->resolve(),
            'recentArticles' => ArticleResource::collection(
                Article::forElection($election)->with('candidates')->orderByRaw('published_on is null')->orderByDesc('published_on')->limit(8)->get()
            )->resolve(),
            // Subject heat and coverage are municipal-only for now.
            'heat' => $election->isProvincial() ? ['headings' => [], 'heatingUp' => []] : app(BuildTagHeat::class)->handle(),
            'coverage' => $election->isProvincial() ? ['tags' => [], 'cells' => (object) []] : app(BuildSubjectCoverage::class)->handle(),
            'latestUpdates' => app(BuildLatestUpdates::class)->handle($election),
            'election' => $election->toPage(),
            // Provincial only: each party's name -> the address of its politics page, for the link under a candidate's card.
            ...($election->isProvincial() ? ['partyPages' => Party::pluck('slug', 'name')->all()] : []),
            // Provincial only: the poll average and list, and the model's projection for each riding (this riding first).
            ...($election->isProvincial() ? [
                'polls' => app(BuildPollAverage::class)->handle(),
                'ridingProjections' => $this->projections($election),
            ] : []),
            // The server's clock, for the Latest updates times.
            'now' => now()->getTimestamp(),
            'stats' => [
                'candidates' => $candidates->where('status', '!=', 'withdrawn')->count(),
                'entries' => $candidates->sum('entries_count'),
                'articles' => Article::forElection($election)->count(),
            ],
        ];
    }

    /** The model's projection for each riding, this election's own riding first and the rest A to Z. */
    private function projections(Election $election): array
    {
        $all = RidingProjection::orderBy('riding')->get();

        return [
            'rows' => $all
                ->sortBy(fn (RidingProjection $r) => ($r->riding === $election->riding ? '0' : '1').$r->riding)
                ->values()
                ->map(fn (RidingProjection $r) => [
                    'id' => $r->id,
                    'riding' => $r->riding,
                    'rating' => $r->rating,
                    'leader' => $r->leader,
                    'shares' => $r->shares,
                    'is_local' => $r->riding === $election->riding,
                ])->all(),
            'source_url' => $all->first()?->source_url ? 'https://338canada.com/bc/districts.htm' : null,
            'as_of' => $all->max('as_of')?->toDateString(),
        ];
    }
}
