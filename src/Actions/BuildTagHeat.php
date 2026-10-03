<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Article;
use Cultpantry\Elections\Models\Entry;
use Cultpantry\Elections\Models\PulseIssue;
use Cultpantry\Elections\Models\Tag;
use Illuminate\Support\Carbon;

/**
 * The dashboard's subject heat map: how much, and how recently, each tag
 * has been talked about.
 *
 * Every dated mention adds heat to the tags it carries -- a candidate's
 * statement (through its plank's tags, or its own), a news article, or a
 * Community Pulse issue (weighted up by how many residents raised it) --
 * and that heat halves every HALF_LIFE_DAYS, so the map leans toward what's
 * being said now. Scorecard answers are left out on purpose: every
 * respondent answers the same fixed questions on the same day, which says
 * nothing about what anyone chose to raise.
 */
class BuildTagHeat
{
    private const HALF_LIFE_DAYS = 14;

    private const RECENT_DAYS = 14;

    public function handle(): array
    {
        $tags = Tag::orderBy('name')->get()->keyBy('id');
        if ($tags->isEmpty()) {
            return ['headings' => [], 'heatingUp' => []];
        }

        $now = Carbon::now();
        $score = $mentions = $recent = $thisWeek = $lastWeek = [];

        $add = function (iterable $tagIds, ?Carbon $when, float $weight = 1.0) use ($now, &$score, &$mentions, &$recent, &$thisWeek, &$lastWeek) {
            $age = max(0, $when?->diffInDays($now) ?? 0);
            foreach (collect($tagIds)->unique() as $id) {
                $score[$id] = ($score[$id] ?? 0) + $weight * 0.5 ** ($age / self::HALF_LIFE_DAYS);
                $mentions[$id] = ($mentions[$id] ?? 0) + 1;
                if ($age <= self::RECENT_DAYS) {
                    $recent[$id] = ($recent[$id] ?? 0) + 1;
                }
                if ($age < 7) {
                    $thisWeek[$id] = ($thisWeek[$id] ?? 0) + $weight;
                } elseif ($age < 14) {
                    $lastWeek[$id] = ($lastWeek[$id] ?? 0) + $weight;
                }
            }
        };

        // Statements carry their own tags or, filed under a plank, the plank's.
        Entry::with(['tags:id', 'plank.tags:id'])->where('kind', '!=', 'background')->get()
            ->each(fn (Entry $e) => $add(
                $e->tags->pluck('id')->merge($e->plank?->tags->pluck('id') ?? []),
                $e->published_on ?? $e->created_at,
            ));

        Article::with('tags:id')->get()
            ->each(fn (Article $a) => $add($a->tags->pluck('id'), $a->published_on ?? $a->created_at));

        // A resident issue counts once, plus a little for every ten people.
        PulseIssue::with(['tags:id', 'snapshot'])->get()
            ->each(fn (PulseIssue $i) => $add($i->tags->pluck('id'), $i->snapshot->taken_on, 1 + $i->voices / 10));

        $max = max($score ?: [0]);
        $row = fn (Tag $tag) => [
            'slug' => $tag->slug,
            'name' => $tag->name,
            // 0-1 relative to the hottest tag, then five bands for the page.
            'heat' => $max > 0 ? round(($score[$tag->id] ?? 0) / $max, 3) : 0,
            'level' => $max > 0 ? (int) min(4, floor(4.999 * (($score[$tag->id] ?? 0) / $max) ** 0.6)) : 0,
            'mentions' => $mentions[$tag->id] ?? 0,
            'recent' => $recent[$tag->id] ?? 0,
        ];

        $headings = collect(Entry::TOPICS)
            ->map(fn (string $title, string $topic) => [
                'topic' => $topic,
                'title' => $title,
                'tags' => $tags->where('topic', $topic)->map($row)->sortByDesc('heat')->values()->all(),
            ])
            ->filter(fn (array $h) => $h['tags'] !== [])
            ->sortByDesc(fn (array $h) => max(array_column($h['tags'], 'heat')))
            ->values()
            ->all();

        // Biggest rise this week over last week, among tags talked about this week.
        $heatingUp = collect($thisWeek)
            ->map(fn ($n, $id) => ['id' => $id, 'rise' => $n - ($lastWeek[$id] ?? 0)])
            ->filter(fn ($r) => $r['rise'] > 0)
            ->sortByDesc('rise')
            ->take(5)
            ->map(fn ($r) => ['slug' => $tags[$r['id']]->slug, 'name' => $tags[$r['id']]->name])
            ->values()
            ->all();

        return ['headings' => $headings, 'heatingUp' => $heatingUp, 'halfLifeDays' => self::HALF_LIFE_DAYS];
    }
}
