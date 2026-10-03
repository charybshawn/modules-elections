<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Http\Resources\ArticleResource;
use Cultpantry\Elections\Http\Resources\CandidateResource;
use Cultpantry\Elections\Http\Resources\ElectionEventResource;
use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Candidate;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Models\Entry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The module's landing page: who's running (mayor first), what's coming up,
 * and the latest coverage.
 */
class BuildElectionDashboard
{
    public function handle(): array
    {
        $candidates = Candidate::query()
            ->withCount(['entries', 'articles'])
            ->orderByRaw("case office when 'mayor' then 0 else 1 end")
            ->orderByRaw("case status when 'withdrawn' then 1 else 0 end")
            ->orderBy('name')
            ->get();

        $upcoming = ElectionEvent::upcoming()->orderBy('starts_at')->get();
        $past = ElectionEvent::whereNotIn('id', $upcoming->pluck('id'))->orderByDesc('starts_at')->get();

        return [
            'candidates' => CandidateResource::collection($candidates)->resolve(),
            'upcomingEvents' => ElectionEventResource::collection($upcoming)->resolve(),
            'pastEvents' => ElectionEventResource::collection($past)->resolve(),
            'recentArticles' => ArticleResource::collection(
                Article::with('candidates')->orderByRaw('published_on is null')->orderByDesc('published_on')->limit(8)->get()
            )->resolve(),
            'activity' => $this->activity(),
            'heat' => app(BuildTagHeat::class)->handle(),
            'coverage' => app(BuildSubjectCoverage::class)->handle(),
            // The server's clock, so "seen" marks and comparisons never mix
            // in the browser's.
            'now' => now()->getTimestamp(),
            'stats' => [
                'candidates' => $candidates->where('status', '!=', 'withdrawn')->count(),
                'entries' => $candidates->sum('entries_count'),
                'articles' => Article::count(),
            ],
        ];
    }

    /**
     * When each candidate's items went on file -- entries added and articles
     * linked -- as Unix timestamps keyed by candidate id. The page counts how
     * many are newer than the viewer's last visit (kept in a cookie).
     *
     * @return array<int, array<int, int>>
     */
    private function activity(): array
    {
        $rows = Entry::query()->select(['candidate_id', 'created_at'])->get()
            ->map(fn (Entry $e) => [$e->candidate_id, $e->created_at])
            ->concat(DB::table('elections_article_candidate')->whereNotNull('created_at')->get(['candidate_id', 'created_at'])
                ->map(fn ($row) => [$row->candidate_id, Carbon::parse($row->created_at)]));

        return $rows
            ->filter(fn (array $row) => $row[1] !== null)
            ->groupBy(0)
            ->map(fn ($group) => $group->map(fn (array $row) => $row[1]->getTimestamp())->values()->all())
            ->all();
    }
}
