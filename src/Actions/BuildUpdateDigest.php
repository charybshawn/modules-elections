<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\ElectionEvent;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\Plank;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What was added to the module in the window (since, until], as a short list
 * of grouped lines for the update email. Like content is grouped so one
 * import reads as a few lines, not dozens:
 *  - a series of news stories (same outlet, same title but for the candidate
 *    named in it, e.g. Castanet's profiles) is one line naming the candidates;
 *  - one source behind research for SOURCE_GROUP or more candidates (e.g. a
 *    questionnaire everyone answered) is one line naming them;
 *  - everything else is one line per candidate with a brief synopsis.
 * News comes first, then events, then shared sources, then candidates.
 */
class BuildUpdateDigest
{
    public const TOP = 15;

    /** Candidates one source must cover before it gets its own line. */
    public const SOURCE_GROUP = 3;

    /** Names listed on a grouped line before "and N more". */
    private const NAMES = 5;

    /**
     * @return array{articles: int, events: int, candidates: int, total: int, top: array<int, array{label: string, text: string, url: ?string}>, more: int}
     */
    public function handle(Carbon $since, Carbon $until): array
    {
        $window = fn ($query) => $query->where('created_at', '>', $since)->where('created_at', '<=', $until);

        $articles = $window(Article::query())->with('candidates:id,name')->latest('created_at')->get(['id', 'title', 'outlet', 'summary', 'url']);
        $events = $window(ElectionEvent::query())->orderBy('starts_at')->get(['title', 'starts_at']);
        $entries = $window(Entry::query())->with('candidate:id,name')->latest('created_at')
            ->get(['id', 'candidate_id', 'plank_id', 'kind', 'source_url', 'source_name'])
            ->filter(fn (Entry $e) => $e->candidate !== null)->values();
        $planks = $window(Plank::query())->with('candidate:id,name')->orderBy('id')
            ->get(['id', 'candidate_id', 'title'])
            ->filter(fn (Plank $p) => $p->candidate !== null)->values();

        // Sources shared by enough candidates get one line; their entries and
        // the planks built only from them leave the per-candidate lines.
        $shared = $entries->groupBy('source_url')
            ->filter(fn (Collection $g) => $g->pluck('candidate_id')->unique()->count() >= self::SOURCE_GROUP);
        $sharedUrls = $shared->keys();
        $plankUrls = $entries->whereNotNull('plank_id')->groupBy('plank_id')->map(fn (Collection $g) => $g->pluck('source_url')->unique());
        $plankSource = $planks->mapWithKeys(fn (Plank $p) => [
            $p->id => ($urls = $plankUrls->get($p->id)) && $urls->count() === 1 && $sharedUrls->contains($urls->first()) ? $urls->first() : null,
        ]);
        $coverage = $shared->map(function (Collection $group, string $url) use ($plankSource) {
            $names = $group->pluck('candidate.name')->unique()->values();

            return $this->count($names->count(), 'candidate').$this->plankNote($plankSource->filter(fn ($u) => $u === $url)->count()).': '.$this->names($names);
        });

        // A shared source that is itself one of the new stories rides on that
        // story's line instead of getting a second one.
        $lines = $this->newsLines($articles, $coverage)
            ->concat($this->eventLines($events));

        $titles = Article::query()->whereIn('url', $sharedUrls)->pluck('title', 'url');
        foreach ($shared as $url => $group) {
            if ($articles->contains('url', $url)) {
                continue;
            }
            $lines->push([
                'label' => $titles->get($url) ?? $group->first()->source_name ?? parse_url((string) $url, PHP_URL_HOST) ?? 'Source',
                'text' => $coverage->get($url),
                'url' => $url ?: null,
            ]);
        }

        $rest = $entries->reject(fn (Entry $e) => $sharedUrls->contains($e->source_url));
        $restPlanks = $planks->filter(fn (Plank $p) => $plankSource->get($p->id) === null);
        $byCandidate = $rest->groupBy('candidate_id');
        $plankIds = $restPlanks->groupBy('candidate_id');
        $order = $byCandidate->keys()->merge($plankIds->keys())->unique();

        foreach ($order as $id) {
            $group = $byCandidate->get($id, collect());
            $candidatePlanks = $plankIds->get($id, collect());
            $name = ($group->first() ?? $candidatePlanks->first())->candidate->name;
            $lines->push(['label' => $name, 'text' => $this->synopsis($group, $candidatePlanks), 'url' => null]);
        }

        $candidateIds = $entries->pluck('candidate_id')->merge($planks->pluck('candidate_id'))->unique();
        $top = $lines->take(self::TOP)->values();

        return [
            'articles' => $articles->count(),
            'events' => $events->count(),
            'candidates' => $candidateIds->count(),
            'total' => $articles->count() + $events->count() + $entries->count() + $planks->count(),
            'top' => $top->all(),
            'more' => max(0, $lines->count() - $top->count()),
        ];
    }

    /**
     * One line per story (its title, plus who it covers when it's a shared
     * research source), except a series (same outlet, same title once the
     * candidates' names are taken out), which is one line naming them.
     */
    private function newsLines(Collection $articles, Collection $coverage): Collection
    {
        $series = $articles->groupBy(fn (Article $a) => Str::lower(($a->outlet ?? '').'|'.$this->seriesTitle($a)));

        return $series->map(function (Collection $group) use ($coverage) {
            $first = $group->first();
            $label = 'News'.($first->outlet ? " ({$first->outlet})" : '');
            $names = $group->flatMap(fn (Article $a) => $a->candidates->pluck('name'))->unique()->values();

            if ($group->count() === 1 || $names->isEmpty()) {
                return $group->map(fn (Article $a) => [
                    'label' => $label,
                    'text' => $a->title.($coverage->has($a->url) ? ' — '.$coverage->get($a->url) : ''),
                    'url' => $a->url,
                ]);
            }

            return collect([[
                'label' => $label,
                'text' => $this->seriesTitle($first).' ('.$group->count().'): '.$this->names($names),
                'url' => null,
            ]]);
        })->flatten(1)->values();
    }

    /** The title with the linked candidates' names (and any joining word before them) removed. */
    private function seriesTitle(Article $article): string
    {
        $title = $article->title;
        foreach ($article->candidates->pluck('name') as $name) {
            $title = preg_replace('/\s*(\b(for|with|of|on|by|meet|from)\s+)?'.preg_quote($name, '/').'\b/i', '', $title);
        }

        return trim(preg_replace('/\s+/', ' ', $title), " \t:,-");
    }

    private function eventLines(Collection $events): Collection
    {
        if ($events->isEmpty()) {
            return collect();
        }
        $fmt = fn (ElectionEvent $e) => $e->title.' ('.$e->starts_at->timezone(config('app.timezone'))->format('M j').')';

        return collect([[
            'label' => $events->count() === 1 ? 'Event' : 'Events',
            'text' => $events->map($fmt)->implode('; '),
            'url' => null,
        ]]);
    }

    /** A brief synopsis of one candidate's additions, e.g. "2 new platform points (Recreation first; ...), 5 statements". */
    private function synopsis(Collection $entries, Collection $planks): string
    {
        $parts = [];
        if ($planks->isNotEmpty()) {
            $parts[] = $this->count($planks->count(), 'new platform point').' ('.$this->excerpt($planks->pluck('title')->take(2)->implode('; '), 70).($planks->count() > 2 ? '; ...' : '').')';
        }
        foreach ($entries->countBy('kind')->sortDesc() as $kind => $n) {
            $parts[] = $n.' '.(BuildLatestUpdates::KIND_LABELS[$kind][$n === 1 ? 0 : 1] ?? $kind);
        }

        $sources = $entries->pluck('source_name')->filter()->countBy()->sortDesc();
        $from = match (true) {
            $sources->isEmpty() || $sources->first() * 2 <= $entries->count() => '',
            $sources->first() === $entries->count() => ', from '.$sources->keys()->first(),
            default => ', mostly from '.$sources->keys()->first(),
        };

        return $this->excerpt(implode(', ', $parts).$from, 200);
    }

    private function plankNote(int $planks): string
    {
        return $planks > 0 ? ', '.$this->count($planks, 'new platform point') : '';
    }

    private function names(Collection $names): string
    {
        $shown = $names->take(self::NAMES)->implode(', ');

        return $names->count() > self::NAMES ? $shown.' and '.($names->count() - self::NAMES).' more' : $shown;
    }

    private function count(int $n, string $noun): string
    {
        return $n.' '.Str::plural($noun, $n);
    }

    private function excerpt(?string $text, int $limit = 160): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', (string) $text)), $limit);
    }
}
