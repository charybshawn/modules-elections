<?php

namespace Cultpantry\Elections\Actions;

use Cultpantry\Elections\Models\Poll;
use Illuminate\Support\Collection;

/**
 * The provincial dashboard's poll panel: every poll on file, newest first, and a
 * simple average of the current ones. "Current" means each pollster's most recent
 * poll, if it finished in the field within WINDOW_DAYS of the newest poll on file.
 * The average is unweighted; a party a pollster didn't report is averaged over the
 * polls that did. It is an amalgamation of published polls, not a forecast.
 */
class BuildPollAverage
{
    public const WINDOW_DAYS = 14;

    public function handle(): array
    {
        /** @var Collection<int, Poll> $polls */
        $polls = Poll::orderByDesc('fielded_to')->orderByDesc('id')->get();
        if ($polls->isEmpty()) {
            return ['polls' => [], 'average' => null];
        }

        $newest = $polls->first()->fielded_to;
        $current = $polls
            ->filter(fn (Poll $p) => $p->fielded_to->gte($newest->copy()->subDays(self::WINDOW_DAYS)))
            ->unique('pollster')
            ->values();

        $average = [];
        foreach ($current->flatMap(fn (Poll $p) => array_keys($p->results))->unique() as $party) {
            $values = $current->map(fn (Poll $p) => $p->results[$party] ?? null)->filter(fn ($v) => $v !== null);
            $average[$party] = ['percent' => round($values->avg(), 1), 'polls' => $values->count()];
        }
        uasort($average, fn ($a, $b) => $b['percent'] <=> $a['percent']);

        return [
            'polls' => $polls->map(fn (Poll $p) => [
                'id' => $p->id,
                'pollster' => $p->pollster,
                'fielded_from' => $p->fielded_from?->toDateString(),
                'fielded_to' => $p->fielded_to->toDateString(),
                'published_on' => $p->published_on?->toDateString(),
                'sample_size' => $p->sample_size,
                'method' => $p->method,
                'results' => $p->results,
                'source_url' => $p->source_url,
                'source_name' => $p->source_name,
                'note' => $p->note,
                'in_average' => $current->contains('id', $p->id),
            ])->all(),
            'average' => [
                'parties' => $average,
                'polls' => $current->count(),
                'from' => $current->min('fielded_to')?->toDateString(),
                'to' => $newest->toDateString(),
                'window_days' => self::WINDOW_DAYS,
            ],
        ];
    }
}
