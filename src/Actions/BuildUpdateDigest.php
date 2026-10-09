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
 * What was added to the module in the window (since, until], shaped as a
 * brief newsletter for the update email -- headlines, not the full record --
 * that aims for variety, so no single candidate or source fills it:
 *  - news: each story with a one-sentence summary; a series (same outlet,
 *    same title once the candidates' names are taken out, e.g. Castanet's
 *    profiles) is one item naming its candidates;
 *  - shared sources: one source behind research for SOURCE_GROUP or more
 *    candidates (e.g. a questionnaire) is one item saying who answered;
 *  - highlights: at most one sentence per candidate, their positions before
 *    their background, for up to HIGHLIGHTS candidates;
 *  - also: the other candidates with new research, by name.
 */
class BuildUpdateDigest
{
    /** Stories shown before the rest are counted. */
    public const NEWS = 6;

    /** Candidates given a highlight sentence. */
    public const HIGHLIGHTS = 8;

    /** Candidates one source must cover before it gets its own item. */
    public const SOURCE_GROUP = 3;

    /** Other updated candidates named before "and N more". */
    private const ALSO = 9;

    /** Names listed in a group before "and N more". */
    private const NAMES = 5;

    /** Which new item a candidate's highlight uses first: their positions, then their record, then who they are. */
    private const HIGHLIGHT_ORDER = ['plank', 'qa_answer', 'statement', 'prior_record', 'endorsement', 'finance', 'background'];

    /** Kinds that state a position, ranked ahead of background when choosing whom to highlight. */
    private const POSITIONS = ['plank', 'qa_answer', 'statement'];

    /**
     * @return array{articles: int, events: int, candidates: int, total: int,
     *   news: array<int, array{title: string, url: ?string, outlet: ?string, text: string, links?: array<string, string>}>, more_news: int,
     *   coming_up: array<int, string>,
     *   shared: array<int, array{title: string, url: ?string, outlet: ?string, text: string}>,
     *   highlights: array<int, string>, also: array<int, string>}
     */
    public function handle(Carbon $since, Carbon $until): array
    {
        $window = fn ($query) => $query->where('created_at', '>', $since)->where('created_at', '<=', $until);

        $articles = $window(Article::query())->with('candidates:id,name')->latest('created_at')->get(['id', 'title', 'outlet', 'summary', 'url']);
        $events = $window(ElectionEvent::query())->orderBy('starts_at')->get(['title', 'starts_at']);
        $entries = $window(Entry::query())->with('candidate:id,name')->latest('created_at')
            ->get(['id', 'candidate_id', 'plank_id', 'kind', 'summary', 'source_url', 'source_name'])
            ->filter(fn (Entry $e) => $e->candidate !== null)->values();
        $planks = $window(Plank::query())->with('candidate:id,name')->orderBy('id')
            ->get(['id', 'candidate_id', 'title'])
            ->filter(fn (Plank $p) => $p->candidate !== null)->values();

        // Sources shared by enough candidates are told once; their entries
        // and the planks built only from them leave the candidate highlights.
        $shared = $entries->groupBy('source_url')
            ->filter(fn (Collection $g) => $g->pluck('candidate_id')->unique()->count() >= self::SOURCE_GROUP);
        $sharedUrls = $shared->keys();
        $plankUrls = $entries->whereNotNull('plank_id')->groupBy('plank_id')->map(fn (Collection $g) => $g->pluck('source_url')->unique());
        $plankSource = $planks->mapWithKeys(fn (Plank $p) => [
            $p->id => ($urls = $plankUrls->get($p->id)) && $urls->count() === 1 && $sharedUrls->contains($urls->first()) ? $urls->first() : null,
        ]);

        $news = $this->news($articles, $sharedUrls);
        $stories = Article::query()->whereIn('url', $sharedUrls)->get(['title', 'url', 'outlet'])->keyBy('url');
        $sharedItems = $shared->map(function (Collection $group, string $url) use ($plankSource, $stories) {
            $names = $group->pluck('candidate.name')->unique()->values();
            $newPlanks = $plankSource->filter(fn ($u) => $u === $url)->count();
            $story = $stories->get($url);

            return [
                'title' => $story?->title ?? $group->first()->source_name ?? parse_url((string) $url, PHP_URL_HOST) ?? 'One source',
                'url' => $url ?: null,
                'outlet' => $story?->outlet,
                'text' => $this->count($names->count(), 'candidate').' answered'
                    .($newPlanks > 0 ? ', adding '.$this->count($newPlanks, 'new platform point') : '')
                    .': '.$this->names($names).'.',
            ];
        })->values();

        [$highlights, $also] = $this->highlights(
            $entries->reject(fn (Entry $e) => $sharedUrls->contains($e->source_url)),
            $planks->filter(fn (Plank $p) => $plankSource->get($p->id) === null),
        );

        return [
            'articles' => $articles->count(),
            'events' => $events->count(),
            'candidates' => $entries->pluck('candidate_id')->merge($planks->pluck('candidate_id'))->unique()->count(),
            'total' => $articles->count() + $events->count() + $entries->count() + $planks->count(),
            'news' => $news->take(self::NEWS)->values()->all(),
            'more_news' => max(0, $news->count() - self::NEWS),
            'coming_up' => $events->map(fn (ElectionEvent $e) => $e->title.' ('.$e->starts_at->timezone(config('app.timezone'))->format('l, M j').')')->all(),
            'shared' => $sharedItems->all(),
            'highlights' => $highlights,
            'also' => $also,
        ];
    }

    /** Each story with a one-sentence summary; a series is one item naming its candidates. Stories that are shared sources are told under "shared" instead. */
    private function news(Collection $articles, Collection $sharedUrls): Collection
    {
        return $articles->reject(fn (Article $a) => $sharedUrls->contains($a->url))
            ->groupBy(fn (Article $a) => Str::lower(($a->outlet ?? '').'|'.$this->seriesTitle($a)))
            ->map(function (Collection $group) {
                $first = $group->first();
                $names = $group->flatMap(fn (Article $a) => $a->candidates->pluck('name'))->unique()->values();

                if ($group->count() === 1 || $names->isEmpty()) {
                    return $group->map(fn (Article $a) => [
                        'title' => $a->title,
                        'url' => $a->url,
                        'outlet' => $a->outlet,
                        'text' => $this->excerpt($a->summary, 120),
                    ]);
                }

                return collect([[
                    'title' => $this->seriesTitle($first),
                    'url' => null,
                    'outlet' => null,
                    'text' => ($first->outlet ? $first->outlet.' added '.$group->count().' to its series: ' : $group->count().' new: ').$this->names($names).'.',
                    // Each name links to that candidate's own story in the series.
                    'links' => $group->flatMap(fn (Article $a) => $a->candidates->mapWithKeys(fn ($c) => [$c->name => $a->url]))->all(),
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

    /**
     * One sentence for each of up to HIGHLIGHTS candidates (those who stated
     * positions first, most new material first), and the rest by name.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function highlights(Collection $entries, Collection $planks): array
    {
        $order = array_flip(self::HIGHLIGHT_ORDER);
        $byCandidate = $entries->groupBy('candidate_id');
        $plankGroups = $planks->groupBy('candidate_id');

        $ranked = $byCandidate->keys()->merge($plankGroups->keys())->unique()
            ->map(fn ($id) => [
                'id' => $id,
                'entries' => $byCandidate->get($id, collect()),
                'planks' => $plankGroups->get($id, collect()),
            ])
            ->sortByDesc(fn ($c) => [
                $c['planks']->count() + $c['entries']->whereIn('kind', self::POSITIONS)->count() > 0 ? 1 : 0,
                $c['planks']->count() + $c['entries']->count(),
            ])->values();

        $highlights = $ranked->take(self::HIGHLIGHTS)->map(function ($c) use ($order) {
            $name = ($c['entries']->first() ?? $c['planks']->first())->candidate->name;
            $best = $c['entries']->sortBy(fn (Entry $e) => $order[$e->kind] ?? count($order))->first();
            $sentence = $best ? $this->sentence($name, $best->summary) : '**'.$name.'** has new platform material.';
            if ($c['planks']->isNotEmpty()) {
                $sentence .= ' New on their platform: '.$this->excerpt($c['planks']->pluck('title')->first(), 70).'.';
            }

            return $sentence;
        })->all();

        $rest = $ranked->slice(self::HIGHLIGHTS)
            ->map(fn ($c) => ($c['entries']->first() ?? $c['planks']->first())->candidate->name)
            ->values();
        $also = $rest->count() > self::ALSO
            ? $rest->take(self::ALSO)->push(($rest->count() - self::ALSO).' more')->all()
            : $rest->all();

        return [$highlights, $also];
    }

    /**
     * A summary as a sentence about the candidate. Summaries are written
     * without a subject ("Says the city...", "Was president of..."), so when
     * one starts with a verb the name leads it: "**Jane** says the city...".
     * Otherwise it follows the name: "**Jane:** Toliver Advertising is...".
     */
    private function sentence(string $name, ?string $summary): string
    {
        $text = rtrim($this->excerpt($summary, 130));
        $first = strtok($text, ' ') ?: '';
        if (preg_match('/^[A-Z][a-z]+ing$/', $first) === 1) {
            // "Drawing on his committee work, describes..." -> "**Jane**, drawing on ..."
            return '**'.$name.'**, '.lcfirst($text);
        }
        $verb = preg_match('/^(?:[A-Z][a-z]+(?:s|ed)|Would|Will|Can|Could|Told|Ran|Won|Led|Sat|Met|Took|Gave|Made|Wrote|Spoke|Regularly|Repeatedly|Also|Recently|Co-[a-z]+)$/', $first) === 1;

        return $verb ? '**'.$name.'** '.lcfirst($text) : '**'.$name.':** '.$text;
    }

    private function names(Collection $names): string
    {
        if ($names->count() > self::NAMES) {
            return $names->take(self::NAMES)->implode(', ').' and '.($names->count() - self::NAMES).' more';
        }

        return $names->count() > 1 ? $names->slice(0, -1)->implode(', ').' and '.$names->last() : (string) $names->first();
    }

    private function count(int $n, string $noun): string
    {
        return $n.' '.Str::plural($noun, $n);
    }

    /** Whitespace collapsed and cut at a word boundary. */
    private function excerpt(?string $text, int $limit = 160): string
    {
        $cut = Str::limit(trim(preg_replace('/\s+/', ' ', (string) $text)), $limit, '...', true);

        // No "J.L...." when the cut lands right after punctuation.
        return preg_replace('/[.,;:!?]+\.\.\.$/', '...', $cut);
    }
}
