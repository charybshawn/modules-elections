<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Election;
use Cultpantry\Elections\Http\Resources\ArticleResource;
use Cultpantry\Elections\Http\Resources\CandidateResource;
use Cultpantry\Elections\Http\Resources\ElectionEventResource;
use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;

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
            // The server's clock, for the Latest updates times.
            'now' => now()->getTimestamp(),
            'stats' => [
                'candidates' => $candidates->where('status', '!=', 'withdrawn')->count(),
                'entries' => $candidates->sum('entries_count'),
                'articles' => Article::forElection($election)->count(),
            ],
        ];
    }
}
