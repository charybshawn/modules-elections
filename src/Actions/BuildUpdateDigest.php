<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Plank;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * What was added to the module in the window (since, until]. Returns the
 * counts of new news stories, events and candidate research, plus the TOP
 * newest excerpts (news first, then events, then research) for the email.
 */
class BuildUpdateDigest
{
    public const TOP = 10;

    /**
     * @return array{articles: int, events: int, candidates: int, total: int, top: array<int, array{label: string, text: string, url: ?string}>}
     */
    public function handle(Carbon $since, Carbon $until): array
    {
        $window = fn ($query) => $query->where('created_at', '>', $since)->where('created_at', '<=', $until);
        $excerpt = fn (?string $text) => Str::limit(trim(preg_replace('/\s+/', ' ', (string) $text)), 160);

        $articles = $window(Article::query())->latest('created_at')->get(['title', 'outlet', 'summary', 'url']);
        $events = $window(ElectionEvent::query())->latest('created_at')->get(['title', 'starts_at']);
        $entries = $window(Entry::query())->with('candidate:id,name')->latest('created_at')->get(['candidate_id', 'summary']);
        $planks = $window(Plank::query())->with('candidate:id,name')->latest('created_at')->get(['candidate_id', 'title']);

        $top = collect()
            ->concat($articles->map(fn ($a) => [
                'label' => 'News'.($a->outlet ? " ({$a->outlet})" : ''),
                'text' => $a->title.($a->summary ? ': '.$excerpt($a->summary) : ''),
                'url' => $a->url,
            ]))
            ->concat($events->map(fn ($e) => [
                'label' => 'Event',
                'text' => $e->title.' ('.$e->starts_at->timezone(config('app.timezone'))->format('M j').')',
                'url' => null,
            ]))
            ->concat($planks->filter(fn ($p) => $p->candidate)->map(fn ($p) => [
                'label' => $p->candidate->name,
                'text' => 'Platform: '.$excerpt($p->title),
                'url' => null,
            ]))
            ->concat($entries->filter(fn ($e) => $e->candidate)->map(fn ($e) => [
                'label' => $e->candidate->name,
                'text' => $excerpt($e->summary),
                'url' => null,
            ]))
            ->take(self::TOP)->values()->all();

        $candidateIds = $entries->pluck('candidate_id')->merge($planks->pluck('candidate_id'))->unique();

        return [
            'articles' => $articles->count(),
            'events' => $events->count(),
            'candidates' => $candidateIds->count(),
            'total' => $articles->count() + $events->count() + $entries->count() + $planks->count(),
            'top' => $top,
        ];
    }
}
